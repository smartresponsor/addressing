#!/usr/bin/env bash
set -euo pipefail

PR="${1:-}"

if [[ -z "$PR" ]]; then
  echo "Usage: ./tool/git-pr-bundle.sh <PR_NUMBER>"
  exit 1
fi

BRANCH="pr-$PR"
BUNDLE="addressing-pr-$PR.bundle"

echo "Fetching PR #$PR into branch $BRANCH"
git fetch origin "pull/$PR/head:$BRANCH"

echo "Creating bundle $BUNDLE (master..$BRANCH)"
git bundle create "$BUNDLE" "master..$BRANCH"

echo "OK: bundle created -> $BUNDLE"
