# Addressing W06 — RC gate cleanup

This pass removes stale patch helper folders, normalizes smoke tool names to the Addressing prefix, repairs Doctrine mapping smoke coverage, and removes legacy-token noise from live inspection tools.

## Scope

- Removed old per-wave patch helper folders from `tools/`.
- Renamed smoke scripts from cross-component inherited names to Addressing-specific names.
- Updated Composer smoke scripts and trust-surface report paths.
- Repaired Doctrine mapping smoke to verify actual Doctrine entity classes.
- Kept deleted legacy service checks without exposing old class tokens as live grep hits.

## Verification

- PHP lint: clean.
- Empty folders: none.
- Cache artifacts: none.
- Live tree references for removed application/http façade tokens: none in `src`, `config`, `tests`, `bin`, `tools`, `public`.
