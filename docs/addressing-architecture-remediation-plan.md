# Addressing architecture remediation plan

## Goal
Bring `smartresponsor/addressing` from a mixed transitional state to a coherent Symfony-oriented `App\\` component with trustworthy QA wiring, lower structural drift, and clearer separation between HTTP, application, persistence, and reporting concerns.

## Current architectural findings

### Confirmed strengths
- The component already behaves as an address operations engine rather than a raw CRUD table.
- It supports tenant scope, validation lifecycle, governance links, evidence snapshots, operational queues, and portfolio summaries.
- The repository has a pragmatic persistence story with PDO plus explicit schema management.

### Confirmed weaknesses
- Structural drift remains in package metadata, composer scripts, QA wiring, and test/bootstrap paths.
- Parts of the QA surface do not appear aligned with the current runtime surface.
- The repository carries too many responsibilities at once: write persistence, reporting, evidence, and outbox support.
- The runtime still relies on a manual front controller and custom routing path rather than a cleaner Symfony-oriented surface.
- Interface layering appears heavier than the current implementation justifies.

## Working rules for remediation
- Keep the default `App\\` namespace only.
- Prefer Symfony-oriented structure and naming.
- Prioritize factual repository state over assumptions.
- Remove legacy drift instead of preserving it by default when it no longer serves an active runtime path.
- Keep changes grouped by wave so each commit has a clear architectural purpose.

## Commit waves

### Wave 1 — Trust surface and drift cleanup
Purpose: make the repository self-descriptive and make QA metadata closer to reality.

Planned actions:
- Normalize package naming and description in `composer.json`.
- Remove or rename drifted composer scripts that still point to non-addressing assets.
- Reconcile PHPUnit suite references with actual addressing tests and paths.
- Reconcile PHPStan and helper loaders with the real runtime/container surface.
- Reconcile Deptrac layer config with actual namespaces used in the component.
- Fix broken fixture/bootstrap entrypoints such as demo reset wiring.

Expected result:
- The repository stops lying about what it is and how it is tested.

### Wave 2 — Symfony runtime normalization
Purpose: reduce custom bootstrap burden and move the component closer to a stable Symfony-oriented runtime.

Planned actions:
- Review `public/index.php`, routing entrypoints, and service loading.
- Reduce manual dispatch where practical.
- Keep the component under `App\\` with a clearer config and runtime surface.
- Trim unnecessary custom glue when Symfony-native mechanisms can own the responsibility.

Expected result:
- The runtime becomes easier to reason about, test, and extend.

### Wave 3 — Responsibility split inside persistence/reporting
Purpose: break apart the current overgrown persistence class.

Planned actions:
- Separate write persistence from read/reporting responsibilities.
- Isolate evidence snapshot operations.
- Isolate outbox/event append support if it stays in-process.
- Keep tenant-scope and policy rules explicit.

Expected result:
- Smaller units with clearer responsibility and lower regression risk.

### Wave 4 — Contract and layer simplification
Purpose: remove architectural ceremony that no longer pays for itself.

Planned actions:
- Audit `EntityInterface`, `RepositoryInterface`, and `ServiceInterface` usage.
- Collapse interfaces that have no meaningful substitution value.
- Keep abstractions only where they serve real runtime or testing needs.

Expected result:
- Less noise and a more honest component boundary.

### Wave 5 — Functional hardening
Purpose: lock in the final architecture with realistic runtime checks.

Planned actions:
- Revisit functional and integration tests after earlier waves land.
- Verify fixture/demo flows.
- Recheck operational endpoints and portfolio summaries.
- Ensure QA scripts reflect the real component shape rather than legacy template assumptions.

Expected result:
- The component becomes a stronger release candidate.

## Immediate next target
Start with Wave 1 because every later code change depends on a trustworthy repository surface.
