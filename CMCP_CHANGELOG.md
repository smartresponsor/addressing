# CMCP Orchestration Journal

## Task

- Task ID: `engine-20260712060343-addressing-84a5a3`
- Component: `Addressing`
- Mode: `AUTONOMOUS_REPOSITORY_RC`
- Workspace: `D:\\PhpstormProjects\\www\\Addressing`

## Iteration 1 — Reconnaissance and baseline

### Read and inspected

- Local `AGENTS.md`, `README.md`, `composer.json`, Deptrac configuration, Git status, and Composer script inventory.
- Canonization normative rules: `Canon000ComponentPrefixRule`, `Canon001TechnicalRoleFirstRule`, `Canon002InterfaceTreeMirrorsImplementationRule`, and `Canon022StandaloneApplicationDependencyBaselineRule`.
- Gating contract and executable-rule posture.
- Mandatory dependency contour documentation for Objecting, Cruding, Viewing, and Interfacing.
- Objecting state-field contract and the current Addressing entity mappings that consume it.

### Current repository state

- Worktree was already materially dirty before this RC pass, with 104 Git status entries covering an in-progress role-first migration. Existing changes are preserved and treated as pre-existing work.
- Initial quick baseline did not identify the standalone surface from Composer metadata alone. Later direct inspection proved `bin/console`, `src/Kernel.php`, `public/index.php`, and `AddressingBundle` are present; Canon022–029 therefore apply and were handled in Iteration 4.
- Canon001/Canon002 map directly to the in-progress migration from nested persistence/contract trees to role-first `Repository`, `RepositoryInterface`, `Event`, `Message`, `Policy`, `Plan`, `Factory`, and mirrored interface paths.

### Gate baseline

- `composer lint`: PASS — 201 PHP files.
- `composer qa:deptrac`: PASS — 0 violations, 0 uncovered, 0 errors.
- `composer qa:phpstan`: BLOCKED before analysis because `phpstan.neon.dist` scans missing generated directory `var/cache/dev/Symfony/Config`.
- `composer test`: FAIL — Doctrine duplicate `enabled` column mapping on `AddressCountryEntity`; Objecting `ObjectStateEmbeddableTrait` already owns canonical `enabled` state while Addressing also maps a local `enabled` property.

### Selected RC-critical workstream

1. Remove duplicate local Doctrine ownership of canonical Objecting `enabled` state while preserving the existing Addressing compatibility methods by delegating them to Objecting.
2. Make PHPStan configuration independent of an optional generated Symfony config directory so static analysis reaches source code reliably.
3. Re-run lint, tests, Deptrac, PHPStan, and focused runtime/Doctrine checks; fix only factual in-scope failures.

## Iteration 2 — Runtime and persistence repair

- Removed duplicate Doctrine `enabled` mappings from country/city/province entities and delegated compatibility accessors to Objecting-owned state.
- Aligned Objecting identity assertions with canonical `uuid`/`slug` mappings.
- Repaired role-first migration callers/imports, DQL evidence ordering, outbox payload key, typed evidence summary boundary, Symfony Form generic, and runtime smoke support paths.
- Reworked smoke harnesses to resolve explicit public application/tooling roots rather than private/inlined Symfony or Doctrine services.
- Added deterministic local SQLite bootstrap under `var/addressing.sqlite` when no explicit database path is supplied.

## Iteration 3 — Static-analysis and QA modernization

- Upgraded PHPStan, PHPStan Symfony/Doctrine extensions, and Rector to current 2.x-compatible tooling.
- Removed PHPStan 1-only configuration and stale generated-cache/container assumptions; PHPStan now analyses source and tests directly.
- Corrected stale aggregate-repository tests to protect the documented split-repository architecture instead of referencing the retired composite repository.
- Fixed factual PHPStan findings in command typing, repository DQL, HTTP form typing, evidence payloads, lifecycle tests, and test bootstrap.
- Added reproducible PHPStan result-cache clearing for ORM metadata refactors.

## Iteration 4 — Canon022–029 standalone/dual-runtime closure

- Proven standalone surfaces: `bin/console`, `src/Kernel.php`, `public/index.php`, and `src/AddressingBundle.php`.
- Development Composer manifest now declares direct Cruding, Viewing, Interfacing, Objecting, EasyAdmin, Security, and CSRF runtime dependencies with local SmartResponsor path repositories using `symlink: true`.
- Added path-independent `composer.prod.json` for packaged production dependencies.
- Added `config/bundles.php` and made Kernel consume it as the runtime bundle registry.
- Added minimal standalone Security/CSRF/session configuration required by EasyAdmin/Cruding without introducing an application authentication model.
- Raised Symfony constraints to the Canon026 8.1+ baseline.
- Reworked Doctrine SQLite persistence to the canonical `infra` connection role and explicitly bound the default entity manager to it.
- Added a profile-independent `tools/qa/addressing-platform-canon.yaml` rule set and direct Composer Gating command; no Addressing-specific Gating profile was introduced.
- Platform Gating result: Canon022–029, 8/8 passed, 0 failed, 0 warnings, 0 skipped.

## Iteration 5 — RC acceptance and regression closure

- Applied repository-owned Rector modernization, then caught and repaired its Doctrine association rename regression; protected ORM identity-sensitive files from generic Rector renaming.
- Restored parenthesized constructor chaining in four files where Deptrac's parser did not accept PHP 8.4 `new Foo()->method()` syntax, while native PHP did; Rector exclusions prevent reintroduction.
- Fixed PHP-CS-Fixer risky-rule configuration and excluded generated `config/reference.php` from source formatting.
- Updated `symfony/dom-crawler` to 8.1.5 after Composer audit reported CVE-2026-45071; final audit is clean.

### Final acceptance evidence

- `composer validate --strict --check-lock`: PASS.
- `composer audit`: PASS — no security vulnerability advisories.
- `composer lint`: PASS — 201 PHP files.
- `composer qa:phpstan`: PASS — 165 analysed paths, 0 errors.
- `composer qa:cs`: PASS — 167 files, 0 fixable.
- `composer qa:rector`: PASS.
- `composer qa:deptrac`: PASS — 0 violations, 0 uncovered, 0 errors, with no parser diagnostics.
- `composer test`: PASS — 22 tests, 108 assertions, 1 intentional skip.
- `composer gating`: PASS — Canon022–029, 8/8.
- `composer smoke:container`: PASS.
- `composer smoke:runtime`: PASS.
- `composer smoke:fixtures`: PASS.
- `composer smoke:fixture-load`: PASS — schema reset/write path loaded 1/1 fixture.
- `composer smoke:doctrine`: exit 0 with its explicit `warn` diagnostic contract that Addressing entities are the schema authority for host applications.
- `composer qa:trust-surface`: PASS.

### Integration disposition

- The repository was already materially dirty before this task and `master` was already ahead of `origin/master`; many files touched by this RC pass also contained pre-existing migration edits.
- No broad stage/commit/push is permitted until provenance is reviewed, because doing so would fold unrelated pre-existing work into this RC integration unit.

## 2026-09-14 — Addressing RC dependency-contract pass

### Reconnaissance baseline

- Read repository `AGENTS.md`, `README.md`, `composer.json`, Addressing architecture/boundary documentation, standalone boot surfaces, and the available QA/gate inventory.
- Read the mandatory contracts for Objecting, Cruding, Viewing, Interfacing, Collectioning, and Tabling plus Gating and Canonization.
- Consulted authoritative Canonization rules `Canon001`, `Canon010`, `Canon017`, `Canon021`, `Canon022`, `Canon031`, `Canon043`, `Canon044`, and `Canon045`.
- Market/open-source baseline: mature address platforms separate parsing/normalization from validation/provider evidence, governance/deduplication, and geocoding. Provider integration/geocoding remains outside this RC workstream.
- Pre-existing worktree contained 182 status entries, overwhelmingly under `.gating`, plus `config/reference.php`; these are preserved and excluded from this patch.

### Target-to-canon mapping

- `Canon021`: generic CRUD stays in Cruding.
- `Canon022`: standalone Addressing (`bin/console` + `config/bundles.php`) requires Collectioning and Tabling as direct runtime dependencies.
- `Canon043`: locally linked first-party packages use exact `dev-master` plus `options.versions` pins.
- `Canon045`: root Composer exposes the complete first-party local path-repository closure.
- `Canon044`: Objecting-backed persisted fields stay entity-native; no field rename is required in this pass.

### Selected RC-critical implementation

- Add direct `collectioning/collection` and `tabling/table` dependencies.
- Add canonical local path repositories/version pins for the complete first-party baseline.
- Register Collectioning and Tabling bundles in standalone runtime and mirror the dependencies in `composer.prod.json`.

### Evidence and gates

- Pre-change `composer gating`: FAIL only `Canon022`, reporting missing Collectioning and Tabling direct dependencies; Canon023–029 pass.
- Post-change Composer resolution: PASS; Collectioning and Tabling resolve as local junction/path packages and the lock was updated deterministically.
- `composer gating`: PASS — Canon022–029, 8/8.
- `composer validate --strict --check-lock`: PASS.
- `composer qa:phpstan`: PASS — 165 analysed paths, 0 errors.
- `composer qa:deptrac`: PASS — 0 violations, 0 uncovered, 0 errors.
- `composer test`: PASS — 22 tests, 108 assertions, 1 intentional skip.
- `composer smoke:container`: PASS.
- `composer smoke:runtime`: PASS.
- `composer audit`: PASS — no security advisories.
