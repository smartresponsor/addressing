#!/usr/bin/env bash
# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
set -euo pipefail

COMMANDING_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
export COMMANDING_DIR

repo_root() {
  if git rev-parse --show-toplevel >/dev/null 2>&1; then
    git rev-parse --show-toplevel 2>/dev/null
    return 0
  fi

  if git -C "$COMMANDING_DIR" rev-parse --show-toplevel >/dev/null 2>&1; then
    git -C "$COMMANDING_DIR" rev-parse --show-toplevel 2>/dev/null
    return 0
  fi

  return 1
}

banner() {
  printf '%s\n' ""
  printf '%s\n' " Commanding"

  local root=""
  root="$(repo_root || true)"
  if [ -n "${root:-}" ]; then
    printf '%s\n' " Repo: $root"
  else
    printf '%s\n' " Repo: not resolved"
  fi

  printf '\n'
}

print_menu() {
  printf '%s\n' " 1 Route      |"
  printf '%s\n' " 2 Server     |"
  printf '%s\n' " 3 Fixture    |"
  printf '%s\n' " 4 Schema     |"
  printf '%s\n' " 5 Patch(zip) |"
  printf '%s\n' " 6 Test       |"
  printf '%s\n' " 7 Docker     |  d) Dot"
  printf '%s\n' " 8 Migration  |  g) Git"
  printf '%s\n' " 9 Composer   |  c) Cache"
  printf '%s\n' " i Inspection |  l) Log"
  printf '%s\n' " 0 Exit       |  r) Repeat"
  printf '%s\n' " --------------------------"
  printf '%s\n' " Enter/space = exit"
}

read_choice() {
  local first="" k="" buf=""
  IFS= read -rsn1 first 2>/dev/null || return 1

  if [[ "$first" == $'\n' || "$first" == $'\r' || "$first" == ' ' ]]; then
    printf ''
    return 0
  fi

  if [[ "$first" =~ [0-9] ]]; then
    buf="$first"
    while IFS= read -rsn1 -t 0.20 k 2>/dev/null; do
      if [[ "$k" =~ [0-9] ]]; then
        buf+="$k"
        continue
      fi
      if [[ "$k" == $'\n' || "$k" == $'\r' ]]; then
        break
      fi
      break
    done
    printf '%s' "$buf"
    return 0
  fi

  printf '%s' "$first"
}

dispatch() {
  local line="${1:-}"

  if [[ "$line" =~ ^[[:space:]]*$ ]]; then
    return 1
  fi

  case "$line" in
    0) return 1 ;;
    r|R) return 0 ;;
  esac

  if [[ "$line" =~ ^[0-9]+$ ]]; then
    bash "$COMMANDING_DIR/run.sh" chain "$line" || true
    return 0
  fi

  bash "$COMMANDING_DIR/run.sh" "$line" || true
  return 0
}

menu_loop() {
  while true; do
    clear || true
    banner
    print_menu
    printf '%s' " Select: "

    local line=""
    line="$(read_choice || true)"
    printf '\n\n'

    dispatch "${line:-}" || break
  done
}

main() {
  if [ $# -ge 1 ]; then
    dispatch "$1" || true
    return 0
  fi

  menu_loop
}

main "$@"
