#!/usr/bin/env bash
# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
set -euo pipefail

COMMANDING_DIR="${COMMANDING_DIR:-"$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)"}"
export COMMANDING_DIR

# shellcheck source=/dev/null
source "$COMMANDING_DIR/lib/ui.sh"

INSPECTION_DIR="$COMMANDING_DIR/logs/inspection"

show_file() {
  local path="${1:-}"
  if [ ! -f "$path" ]; then
    printf '%s\n' "File not found: $path"
    ui_pause_any
    return 0
  fi

  if command -v less >/dev/null 2>&1; then
    less "$path"
  else
    cat "$path"
    printf '\n'
    ui_pause_any
  fi
}

ui_clear
ui_banner "Log"

printf '%s\n' "Logs Menu"
printf '%s\n' "---------"
printf '%s\n' "1) Symfony server logs"
printf '%s\n' "2) Docker logs"
printf '%s\n' "3) Inspection latest log"
printf '%s\n' "4) Inspection summary"
printf '%s\n' "5) Inspection chat report"
printf '%s\n' "6) Inspection compare report"
printf '%s\n' "7) Inspection findings ndjson"
printf '%s\n' "Space) Exit"

read -r -n 1 -s -p "Choice: " action
printf '\n'

case "${action:-}" in
  1) exec symfony server:log ;;
  2) exec docker compose logs -f ;;
  3) show_file "$INSPECTION_DIR/latest.log" ;;
  4) show_file "$INSPECTION_DIR/latest.summary.txt" ;;
  5) show_file "$INSPECTION_DIR/latest.chat.txt" ;;
  6) show_file "$INSPECTION_DIR/latest.compare.txt" ;;
  7) show_file "$INSPECTION_DIR/latest.findings.ndjson" ;;
  *) exit 0 ;;
esac
