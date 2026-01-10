#!/usr/bin/env bash
set -euo pipefail

PR="$1"
BRANCH="pr-$PR"
BUNDLE="addressing-pr-$PR.bundle"

git fetch origin "pull/$PR/head:$BRANCH"
git bundle create "$BUNDLE" "master..$BRANCH"

echo "Created $BUNDLE"
