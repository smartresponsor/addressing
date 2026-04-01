#!/usr/bin/env bash
# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
set -euo pipefail

COMMANDING_DIR="${COMMANDING_DIR:-"$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)"}"
export COMMANDING_DIR

# shellcheck source=/dev/null
source "$COMMANDING_DIR/lib/ui.sh"

detect_project_root() {
  local root=""
  root="$(repo_root || true)"
  if [ -n "${root:-}" ]; then
    printf '%s' "$root"
    return 0
  fi

  if [ "$(basename "$COMMANDING_DIR")" = ".commanding" ]; then
    dirname "$COMMANDING_DIR"
    return 0
  fi

  printf '%s' "$COMMANDING_DIR"
}

PROJECT_ROOT="$(detect_project_root)"
LOG_DIR="$COMMANDING_DIR/logs"
LOG_FILE="$LOG_DIR/action.log"
ERR_FILE="$LOG_DIR/error.log"

mkdir -p "$LOG_DIR"

timestamp() {
  date '+%Y-%m-%d %H:%M:%S'
}

has_composer_script() {
  local script_name="${1:-}"
  [ -f "$PROJECT_ROOT/composer.json" ] || return 1
  command -v php >/dev/null 2>&1 || return 1

  php -r '
    $file = $argv[1];
    $script = $argv[2];
    if (!is_file($file)) { exit(1); }
    $data = json_decode(file_get_contents($file), true);
    if (!is_array($data)) { exit(2); }
    if (!isset($data["scripts"]) || !is_array($data["scripts"])) { exit(3); }
    exit(array_key_exists($script, $data["scripts"]) ? 0 : 4);
  ' "$PROJECT_ROOT/composer.json" "$script_name" >/dev/null 2>&1
}

run_logged() {
  local label="${1:-Command}"
  shift || true

  local started
  started="$(timestamp)"
  printf '[%s] %s\n' "$started" "$label" >> "$LOG_FILE"

  local exit_code=0
  set +e
  (
    cd "$PROJECT_ROOT"
    "$@"
  ) >> "$LOG_FILE" 2>> "$ERR_FILE"
  exit_code=$?
  set -e

  printf '[%s] Exit code: %s\n' "$(timestamp)" "$exit_code" >> "$LOG_FILE"
  return "$exit_code"
}

ui_clear
ui_banner "Composer"

printf '%s\n' "Composer Menu"
printf '%s\n' "-------------"
printf '%s\n' "1) Install"
printf '%s\n' "2) Update"
printf '%s\n' "3) Dump autoload"
printf '%s\n' "4) QA (composer qa)"
printf '%s\n' "5) Inspection run"
printf '%s\n' "6) Inspection latest"
printf '%s\n' "Space) Exit"

read -r -n 1 -s -p "Choice: " action
printf '\n'

EXIT_CODE=0

case "${action:-}" in
  1)
    run_logged "Composer install" composer install || EXIT_CODE=$?
    ;;
  2)
    run_logged "Composer update" composer update || EXIT_CODE=$?
    ;;
  3)
    run_logged "Composer dump-autoload" composer dump-autoload || EXIT_CODE=$?
    ;;
  4)
    if has_composer_script "qa"; then
      run_logged "Composer qa" composer qa || EXIT_CODE=$?
    else
      printf '%s\n' "composer script 'qa' not found"
      ui_pause_any
      exit 0
    fi
    ;;
  5)
    if has_composer_script "inspection:run"; then
      run_logged "Composer inspection:run" composer inspection:run || EXIT_CODE=$?
    elif has_composer_script "inspection"; then
      run_logged "Composer inspection" composer inspection || EXIT_CODE=$?
    else
      printf '%s\n' "inspection composer scripts not found"
      ui_pause_any
      exit 0
    fi
    ;;
  6)
    if has_composer_script "inspection:latest"; then
      run_logged "Composer inspection:latest" composer inspection:latest || EXIT_CODE=$?
    else
      printf '%s\n' "composer script 'inspection:latest' not found"
      ui_pause_any
      exit 0
    fi
    ;;
  *)
    exit 0
    ;;
esac

printf '%s\n' "Exit code: $EXIT_CODE"
ui_pause_any
exit 0
