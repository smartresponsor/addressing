[CmdletBinding()]
param(
    [string]$SecretId = '',
    [string[]]$QodanaArguments = @('scan', '--linter', 'qodana-php', '--within-docker', 'false')
)

$ErrorActionPreference = 'Stop'

function Import-UserAwsEnvironment {
    foreach ($name in @('AWS_PROFILE', 'AWS_DEFAULT_PROFILE', 'AWS_REGION', 'AWS_DEFAULT_REGION')) {
        if (-not [string]::IsNullOrWhiteSpace([System.Environment]::GetEnvironmentVariable($name, 'Process'))) {
            continue
        }

        $value = [System.Environment]::GetEnvironmentVariable($name, 'User')
        if ([string]::IsNullOrWhiteSpace($value)) {
            $value = [System.Environment]::GetEnvironmentVariable($name, 'Machine')
        }

        if (-not [string]::IsNullOrWhiteSpace($value)) {
            Set-Item -Path "Env:$name" -Value $value.Trim()
        }
    }
}

Import-UserAwsEnvironment

function Resolve-RepositoryRoot {
    $gitRoot = (& git rev-parse --show-toplevel 2>$null | Out-String).Trim()
    if ([string]::IsNullOrWhiteSpace($gitRoot)) {
        throw 'Unable to resolve repository root with git rev-parse --show-toplevel.'
    }

    return $gitRoot
}

function Resolve-QodanaSecretId {
    param(
        [Parameter(Mandatory = $true)][string]$RepositoryRoot,
        [string]$ExplicitSecretId = ''
    )

    $candidate = if ([string]::IsNullOrWhiteSpace($ExplicitSecretId)) {
        Split-Path -Leaf $RepositoryRoot
    } else {
        $ExplicitSecretId.Trim()
    }

    if ([string]::IsNullOrWhiteSpace($candidate) -or $candidate -notmatch '^[A-Za-z0-9._-]+$' -or $candidate.Contains('..')) {
        throw 'Unsafe Qodana secret id. Use the repository folder name only, without path separators.'
    }

    return $candidate
}

function Get-RequiredCommand {
    param([Parameter(Mandatory = $true)][string]$Name)

    $command = Get-Command $Name -ErrorAction SilentlyContinue
    if ($null -eq $command) {
        throw "Required command is not available on PATH: $Name"
    }

    return $command.Source
}

function Get-QodanaSecret {
    param([Parameter(Mandatory = $true)][string]$SecretId)

    $aws = Get-RequiredCommand -Name 'aws'
    $secretText = (& $aws secretsmanager get-secret-value --secret-id $SecretId --query SecretString --output text 2>&1 | Out-String).Trim()
    if ($LASTEXITCODE -ne 0) {
        throw "Unable to read Qodana secret '$SecretId' from AWS Secrets Manager. $secretText"
    }

    if ([string]::IsNullOrWhiteSpace($secretText) -or $secretText -eq 'None') {
        throw "Qodana secret '$SecretId' has no SecretString value."
    }

    try {
        $json = $secretText | ConvertFrom-Json
    } catch {
        throw "Qodana secret '$SecretId' must be JSON with QODANA_TOKEN."
    }

    if (-not ($json.PSObject.Properties.Name -contains 'QODANA_TOKEN') -or [string]::IsNullOrWhiteSpace([string]$json.QODANA_TOKEN)) {
        throw "Qodana secret '$SecretId' must contain non-empty QODANA_TOKEN."
    }

    return $json
}

$repositoryRoot = Resolve-RepositoryRoot
$resolvedSecretId = Resolve-QodanaSecretId -RepositoryRoot $repositoryRoot -ExplicitSecretId $SecretId
$secret = Get-QodanaSecret -SecretId $resolvedSecretId
$qodana = Get-RequiredCommand -Name 'qodana'

$env:QODANA_TOKEN = [string]$secret.QODANA_TOKEN
if ($secret.PSObject.Properties.Name -contains 'QODANA_ENDPOINT' -and -not [string]::IsNullOrWhiteSpace([string]$secret.QODANA_ENDPOINT)) {
    $env:QODANA_ENDPOINT = [string]$secret.QODANA_ENDPOINT
}

try {
    Write-Host "Running Qodana for repository '$resolvedSecretId'."
    & $qodana @QodanaArguments
    exit $LASTEXITCODE
} finally {
    Remove-Item Env:QODANA_TOKEN -ErrorAction SilentlyContinue
    Remove-Item Env:QODANA_ENDPOINT -ErrorAction SilentlyContinue
}
