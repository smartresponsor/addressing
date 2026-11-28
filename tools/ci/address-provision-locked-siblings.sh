#!/usr/bin/env bash
set -euo pipefail
set +x

# Disposable CI checkout only; never replace existing sibling directories.
[[ "${CI:-}" == "true" ]] || { echo "Locked sibling provisioning requires CI=true" >&2; exit 1; }
ROOT="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/../.." && pwd -P)"
PARENT="$(dirname -- "$ROOT")"
[[ -n "${ADDRESSING_GATING_READ_TOKEN:-}" ]] || { echo "Missing repository-scoped Gating read credential" >&2; exit 1; }

scratch="$(mktemp -d)"
trap 'rm -rf -- "$scratch"' EXIT
php "$ROOT/tools/ci/AddressLockedSiblingManifest.php" > "$scratch/manifest.tsv"
[[ "$(wc -l < "$scratch/manifest.tsv")" -eq 8 ]]

# Preflight every destination before any network or checkout operation.
while IFS=$'\t' read -r package directory repository ref; do
    [[ ! -e "$PARENT/$directory" && ! -L "$PARENT/$directory" ]] || {
        echo "Existing sibling path refused: $directory" >&2
        exit 1
    }
done < "$scratch/manifest.tsv"

umask 077
cat > "$scratch/askpass" <<'ASKPASS'
#!/usr/bin/env bash
case "$1" in
    Username*) printf '%s\n' 'x-access-token' ;;
    Password*) printf '%s\n' "${ADDRESSING_GATING_READ_TOKEN:?}" ;;
    *) exit 1 ;;
esac
ASKPASS
chmod 700 "$scratch/askpass"

export GIT_TERMINAL_PROMPT=0
while IFS=$'\t' read -r package directory repository ref; do
    target="$PARENT/$directory"
    url="https://github.com/smartresponsor/$repository.git"
    git init --quiet "$target"
    if [[ "$repository" == "gating" ]]; then
        GIT_ASKPASS="$scratch/askpass" git -C "$target" -c credential.helper= -c http.followRedirects=false -c protocol.file.allow=never fetch --quiet --no-tags --depth=1 "$url" "$ref"
    else
        env -u ADDRESSING_GATING_READ_TOKEN -u GIT_ASKPASS git -C "$target" -c credential.helper= -c core.askPass= -c http.followRedirects=false -c protocol.file.allow=never fetch --quiet --no-tags --depth=1 "$url" "$ref"
    fi
    env -u ADDRESSING_GATING_READ_TOKEN git -C "$target" -c core.hooksPath=/dev/null checkout --quiet --detach FETCH_HEAD
    [[ "$(git -C "$target" rev-parse HEAD)" == "$ref" ]]
    php -r '$p = json_decode((string) file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR); exit(($p["name"] ?? null) === $argv[2] ? 0 : 1);' "$target/composer.json" "$package"
    [[ -z "$(git -C "$target" status --porcelain)" ]]
    printf 'Locked sibling verified: %s %s\n' "$package" "$ref"
done < "$scratch/manifest.tsv"
