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
- Aggregate `qa:full` initially exposed two pre-existing QA wiring defects: source-style scanning included generated `config/reference.php`, and PHPMD scripts referenced an uninstalled tool whose stable dependency line cannot coexist with Symfony 8.1.
- Aligned `.php-cs-fixer.php` with the existing dist contract by excluding generated `config/reference.php` rather than mutating the generated file.
- Removed dead PHPMD script wiring after Composer proved stable PHPMD 2.15/PDepend 2.x incompatible with Symfony 8.1; `qa:full` now composes the maintained `qa` contour (PHPStan, Deptrac, trust-surface, Rector) with style and tests.
- Final `composer qa:full`: PASS — lint 201 files, CS 167 files, PHPStan 165 paths, Deptrac 136 paths/0 violations, trust-surface ready, Rector clean, PHPUnit 22 tests/108 assertions/1 intentional skip.
- Final `composer validate --strict --check-lock`: PASS.
- Signed integration commit `c951d89` contains only the first dependency-baseline change set; pre-existing `.gating/**` and `config/reference.php` changes were excluded.
- Initial push attempt was blocked by Console MCP policy because `master` is protected and the worktree still reported 182 entries.

### Dirty-worktree and protected-master closure

- Forensic Git review showed the 182-entry status was not 182 independent code edits: only 27 tracked files had content diffs, consisting of the embedded `.gating/**` distribution plus generated `config/reference.php`; the remaining status entries collapsed after index normalization.
- Canon037 was read directly from Canonization and requires `config/reference.php` to remain outside repository source history. Added `/config/reference.php` to `.gitignore`, removed the file from the Git index while preserving the local generated copy, and committed this as `d11a346` (`Stop tracking generated Symfony reference`).
- The embedded `.gating` changes were verified as the current Gating `0.11.0` distribution: sampled Registry, Cruding profile, Canon043–045 and tooling matched the owner `Gating` repository; staging `.gating` normalized status noise down to 34 real files.
- Embedded Gating calibration tests passed, then the policy-pack sync was committed as `85fd7ae` (`Sync Addressing Gating policy pack`).
- Post-sync worktree became fully clean: `dirtyCount=0`; local `master` was ahead of `origin/master` by four commits and behind by zero.
- Direct push to protected `master` was not bypassed. Created `rc/addressing-rc-closure` at HEAD, pushed it to origin, and opened PR `smartresponsor/addressing#81` against `master`.
- PR #81 is Git-mergeable. GitHub checks currently fail before runner steps start (`steps: []`) across CodeQL, Qodana, Security and Addressing gate workflows. The same pre-runner pattern is present in other `smartresponsor` repositories, including a recent Tagging CodeQL run, so this is an organization/GitHub Actions infrastructure blocker rather than an Addressing code failure.
- Merge remains intentionally stopped while GitHub checks are red and review is required; no branch-protection bypass or force push was used.

## 2026-09-15 — Route evidence and Canon017 parity

### Reconnaissance baseline

- Re-read the Addressing root instructions, README, Composer/runtime metadata, current front controller, HTTP/entity/remediation/OpenAPI documentation, QA configuration, existing CMCP journal, and the active HTTP service split.
- Re-read the required Objecting, Cruding, Viewing, and Interfacing README/Composer/AGENTS surfaces and relevant Objecting responsibility/identity/install, Cruding entrypoint/layout, and Viewing host-integration contracts. Composer resolves Objecting, Cruding, Viewing, and Interfacing from the expected sibling `path` packages at `dev-master`; Addressing declares the complete standalone baseline including Collectioning and Tabling with symlinked local repositories.
- Re-read Gating README/Composer/AGENTS and Canonization README, guard matrix, AGENTS projection, and normative rules `Canon000`, `Canon001`, `Canon002`, `Canon017`, `Canon021`, `Canon022`, and `Canon029`.
- Target-to-canon mapping: `Address*` is the component subject prefix; source remains technical-role-first; typed interface trees mirror implementation roles; current docs must describe the current runtime; generic reusable CRUD remains owned by Cruding while Addressing owns address-specific lifecycle/operational semantics; standalone platform packages remain direct dependencies; standard PHP QA remains repository-owned.
- No repository-local memory/architecture graph artifact was discovered by the current graph/codebase-memory scan; `.codebase-memory/` is not treated as a normative Git surface.

### RC-critical workstream

- Reproduced `composer report:route-inventory` returning empty methods and URI tokens because it parsed retired raw `$_SERVER` routing instead of the current Symfony `Request`-derived `$method`/`$pathInfo` front controller.
- Reworked `AddressRouteInventoryReport.php` to extract and deterministically sort current HTTP methods, exact Addressing URI tokens, and regex-backed dynamic URI patterns.
- Added route-inventory content verification to `AddressTrustSurfaceRunner.php`, so the trust gate now fails if the diagnostic exists but no longer proves the core current runtime surface.
- Updated README and HTTP-surface documentation to the actual split HTTP services and live routes, removed the obsolete `*@dev` consumer example, and established `openapi/address.yaml` as the canonical machine-readable API contract.
- Replaced the obsolete duplicate `docs/openapi.yaml` CRUD-era contract with a thin compatibility entry point referencing the canonical OpenAPI paths; removed the nonexistent `GET /api/address` alias and added the current search, operational PATCH/batch, summary, and governance paths to the canonical specification.

### Growth workstream kept outside RC

- Provider-neutral validation adapters, broader international normalization, richer confidence/precision UX, and additional provider-evidence capabilities remain growth work. External geocoding/postal verification, mapping/routing UI, generic collection infrastructure, generic CRUD mechanics, and final presentation remain outside Addressing ownership.

### Verification status

- `composer report:route-inventory`: green after repair; proves GET/POST/PATCH/DELETE, ten exact URI tokens, and three dynamic URI patterns.
- `composer qa:trust-surface`: green with `route_inventory_ready: true`.
- `composer qa:full`: green; PHP lint passed 201 files, PHP-CS-Fixer found 0 fixable files, PHPStan returned no errors, Deptrac returned 0 violations/warnings/errors, trust-surface is ready, Rector dry-run is clean, and PHPUnit passed 22 tests / 108 assertions with 1 intentional skip.
- The first full QA pass exposed one stale Addressing expectation against the current Objecting identity embeddable (`objectUuid`/`objectSlug`). The test contract was repaired to canonical entity-native Objecting fields `uuid`/`slug`; the public `getObject*` API remains unchanged.
- `composer gating`: green, Canon022–Canon029 8/8 passed with 0 failed, warning, suppressed, or skipped rules.
- `composer validate --strict --check-lock`: green.
- Symfony `lint:yaml` is green for repository config plus `openapi/address.yaml`, `docs/openapi.yaml`, and `docs/AddressAPI.insomnia.yaml`.
- Canon017 cleanup also refreshed the Postman/Insomnia examples to current `/api/address/*` routes and current create/search/page vocabulary; the retired `/address/search-advanced` token is absent from the current tree.
- Signed implementation commit `2041d1e` (`Harden Addressing route diagnostics and runtime docs`) was created on `rc/addressing-rc-closure` and pushed to origin; the journal closure is committed separately.
- PR `smartresponsor/addressing#81` is open and Git-mergeable, but GitHub still requires review. The latest observed Addressing gate and Security jobs fail before runner steps execute (`steps: []`), matching the previously recorded organization/Actions infrastructure pattern rather than a repository gate failure. No merge was attempted while required review and remote checks remain red.

## 2026-09-16 — Persistence-contract documentation hardening

### Reconnaissance baseline

- Re-read Addressing `AGENTS.md`, README, Composer manifest, HTTP/entity/remediation/OpenAPI documentation, Deptrac policy, trust-surface gate, current Doctrine schema/mapper/entity implementation, and Git state.
- Re-read the required Objecting, Cruding, Viewing, Interfacing, Gating, and Canonization root contracts. Addressing still declares the complete first-party dependency contour through pinned `dev-master` path repositories with symlinks.
- Consulted Canonization rules `Canon017`, `Canon021`, `Canon022`, and `Canon044` for current-documentation parity, CRUD ownership, standalone dependency baseline, and Objecting system-field naming.
- Current branch is `rc/addressing-rc-closure`, tracking `origin/rc/addressing-rc-closure`, initially ahead by one commit. The pre-existing untracked `bin/cmcp-generate-current-baseline.ps1` is preserved and excluded from this workstream.
- Baseline verification: `composer gating` passes Canon022–029 8/8; PHPUnit passes 22 tests / 108 assertions with one intentional skip; lint, CS, PHPStan, Deptrac, trust-surface, and Rector stages are green. The aggregate `qa:full` Console invocation reached PHPUnit after all preceding stages passed but the wrapper returned without a final exit code, so PHPUnit was re-run independently and passed.

### Target-to-canon mapping and selected RC work

- `Canon017`: `docs/addressing-entity-boundary-contract.md` still described removed `AddressPdoFactory` / `AddressSchemaManager` / `sql/postgres` schema authority. Current runtime instead uses Doctrine attributes, `AddressDoctrineSchemaManager`, `AddressEntityMapper`, and the configured Doctrine entity manager.
- `Canon021`: no generic CRUD engine is introduced; Addressing-specific lifecycle/operational HTTP remains inside Addressing while reusable CRUD stays in Cruding.
- `Canon022`: no dependency change is required; the standalone baseline is already complete and executable Gating is green.
- `Canon044`: active Objecting-backed taxonomy entities use canonical field packs. A broader lifecycle migration of the main persistence entities is deliberately not folded into this documentation repair because it changes schema semantics and requires an explicit data-parity wave.
- RC-critical implementation: make the entity-boundary document describe the current Doctrine runtime, mark the old remediation plan as historical context, and extend `qa:trust-surface` with a deterministic documentation-runtime parity assertion so removed PDO topology cannot silently return to authoritative current documentation.

### Growth workstream kept outside RC

- Provider-neutral postal-validation/geocoding adapters, broader international normalization, richer confidence/precision UX, and external provider observability remain post-RC capability work. Mapping/routing UI, generic CRUD, generic collection infrastructure, and final rendering remain outside Addressing ownership.

### Verification plan

- Run targeted trust-surface and PHP lint first, then `qa:full`, `gating`, Composer validation/audit, Doctrine/container/runtime smokes, and final Git/PR integration checks.

### Verification and repair results

- `qa:trust-surface` is green and now reports `documentation_runtime_ready: true` in addition to route readiness.
- The first aggregate `qa:full` retry exposed stale/transient PHPStan/Symfony cache state referencing the old Cruding normalizer namespace. Addressing contains no such reference; current Cruding owns `App\\Cruding\\Normalizer\\Resource\\CrudRouteValueNormalizer`. After the repository-provided `phpstan:clear`, `qa:phpstan` and the complete `qa:full` gate pass.
- Composer strict validation passes and `composer audit` reports no security advisories.
- Container and runtime smokes pass.
- The pre-existing Doctrine smoke was factually broken because it did not load Composer autoload and therefore always reported ORM/entities missing while returning success. It now loads the installed runtime, checks all three schema-managed Doctrine entities (`AddressEntity`, `AddressEvidenceSnapshotEntity`, `AddressOutboxEntity`), and fails hard on incomplete evidence. The repaired smoke reports `status: ready` with ORM and all entity mappings present.
