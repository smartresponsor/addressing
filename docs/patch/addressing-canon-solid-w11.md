# Addressing canon solid W11

## Purpose

Fix Symfony Console shortcut conflict detected by PHPStan console application loader.

## Changes

- Removed `-q` shortcut from Addressing query options because Symfony Console reserves `-q` for global quiet mode.
- Updated commands to keep long option `--query` only.
- Added W11 RC proof script.

## Affected files

- `src/Command/AddressSearchCommand.php`
- `src/Command/AddressPortfolioSummaryCommand.php`
- `src/Command/AddressQueueSummaryCommand.php`

## Expected proof

- PHPUnit may still report one skipped test.
- PHPStan should no longer fail with `An option with shortcut "q" already exists`.
