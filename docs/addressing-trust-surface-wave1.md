# Addressing trust-surface wave 1

This note documents the first trust-surface batch for `smartresponsor/addressing`.

## Goal

Reduce runtime drift by adding executable smoke entrypoints and a shared runtime bootstrap helper that reflect the actual current slice.

## Included in this wave

- Shared runtime bootstrap helper under `tools/support/AddressRuntimeBootstrap.php`
- Smoke entrypoints for runtime, fixture sanity, container boot, fixture load, Doctrine-mapping posture, and GraphQL posture
- Composer smoke script surface report
- Trust-surface file presence report

## Why this wave matters

The repository already advertises several smoke scripts in `composer.json`. Before this wave, the corresponding executable files were missing. That made the QA surface less trustworthy than the actual domain code deserved.

This wave does not attempt a full runtime normalization yet. It only restores a factual executable surface for the currently advertised smoke entrypoints and makes two intentional absences explicit:

- Doctrine ORM mapping is not the active runtime surface in the current slice
- GraphQL is not part of the active runtime surface in the current slice

## Expected next steps

Follow-up work should reconcile the remaining drift in existing bootstrap and reporting files, then continue with Symfony runtime normalization and persistence/reporting responsibility split.
