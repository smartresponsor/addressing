#!/usr/bin/env bash
set -euo pipefail

cmd="${1:-}"
if [[ -z "$cmd" ]]; then
  echo "Usage: $0 <gitleaks|semgrep>" >&2
  exit 2
fi

run_gitleaks() {
  if command -v gitleaks >/dev/null 2>&1; then
    gitleaks git --redact --verbose --exit-code 1 .
    return
  fi

  if command -v docker >/dev/null 2>&1; then
    docker run --rm -v "$(pwd):/path" zricethezav/gitleaks:latest git --redact --verbose --exit-code 1 /path
    return
  fi

  echo "gitleaks is required. Install gitleaks or Docker to run this check." >&2
  exit 1
}

run_semgrep() {
  if command -v semgrep >/dev/null 2>&1; then
    semgrep scan --config p/php --config p/secrets --error src tests
    return
  fi

  if command -v docker >/dev/null 2>&1; then
    docker run --rm -v "$(pwd):/src" returntocorp/semgrep semgrep scan --config p/php --config p/secrets --error /src/src /src/tests
    return
  fi

  echo "semgrep is required. Install semgrep or Docker to run this check." >&2
  exit 1
}

case "$cmd" in
  gitleaks) run_gitleaks ;;
  semgrep) run_semgrep ;;
  *)
    echo "Unsupported command: $cmd" >&2
    exit 2
    ;;
esac
