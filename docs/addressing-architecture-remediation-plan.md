# Addressing architecture remediation plan

> Historical remediation record. This document preserves the reasoning and planned waves that led to the current component shape; it is not the authoritative description of the active runtime. For current contracts use `README.md`, `docs/addressing-http-surface.md`, `docs/addressing-entity-boundary-contract.md`, and `config/openapi/address_openapi.yaml`.

## Goal
Bring Addressing from a mixed transitional state to a coherent Symfony-oriented `App\Addressing\\` component with trustworthy QA wiring, lower structural drift, and clearer separation between HTTP, application, persistence, and reporting concerns.

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
- Keep the default `App\Addressing\\` namespace only.
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
- Keep the component under `App\Addressing\\` with a clearer config and runtime surface.
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

## Recovered historical API governance requirements

Source: `7f44236cbacbf30d0260c05cfd64f07516c4cc43` (three original documents: `docs/addressing-api-contract-posture.md`, `docs/addressing-rc-readiness-roadmap.md`, `docs/addressing-route-inventory.md`). The text below is preserved historical design intent, not a current endpoint inventory, implementation promise or release acceptance. Current contracts remain Symfony controller attributes, canonical OpenAPI, and tested runtime behavior. Reconcile each outstanding requirement before marking it implemented.

### Addressing API contract posture

#### Purpose

This note defines the intended public-contract posture for the current Addressing slice so that future work can harden the component toward RC without accidental route, payload, or schema drift.

It is intentionally architecture-first. It does not attempt to describe every current endpoint in code-level detail. It defines the rules that should govern endpoint inventory, payload evolution, error-shape stability, and backward-compatible change discipline.

#### Current posture

The current Addressing slice already exposes a meaningful HTTP API surface and uses dedicated HTTP factories to decode payloads, build filter structures, and shape output arrays. That is a strong base, but contract governance is still weaker than the code organization itself.

The main risk is not that the API is absent. The risk is that route shapes, field names, and write semantics could evolve faster than downstream consumers can safely absorb.

#### Public surface categories

The public surface should be treated as four separate contract zones:

1. **Route contract**
   - URI shape
   - HTTP method
   - success status codes
   - error status codes

2. **Request payload contract**
   - required fields
   - optional fields
   - normalization rules
   - field deprecation policy

3. **Response payload contract**
   - field names
   - nullability rules
   - stability of derived booleans and review flags
   - pagination structure where applicable

4. **Write-semantics contract**
   - idempotency expectations
   - duplicate replay behavior
   - tenant-scope expectations
   - governance transition rules

These zones should be versioned and reviewed independently even when they ship together.

#### Recommended contract inventory

A maintained route inventory should exist in documentation and should include at minimum:

- route name
- method
- path
- purpose
- request contract owner
- response contract owner
- idempotency posture
- tenant scope requirements
- backward-compatibility expectations

#### Versioning posture

The current slice can remain on a single API generation for now, but the following rules should apply immediately:

- additive fields are allowed when they do not change existing meaning
- field removal is not allowed without an explicit deprecation window
- changing a field type is a breaking change
- changing nullability from nullable to required is a breaking change
- changing governance or validation semantics behind an unchanged field name must be treated as a breaking behavioral change unless feature-gated
- changing default filtering behavior must be treated as a contract change

If a future route or media-type version is introduced, these rules should become the migration baseline.

#### Error contract posture

Error payloads should converge on a stable shape:

- `error`: short stable machine-readable code
- `message`: optional human-oriented explanation
- `details`: optional structured validation or field-level detail
- `correlationId`: optional but strongly recommended for RC hardening

Errors should avoid free-form drift. If one endpoint returns `not_found` and another returns `missing_record`, that should be treated as avoidable contract drift unless the distinction is intentionally semantic.

#### Derived-field posture

The component already exposes domain-derived semantics such as review reasons, evidence flags, governance linkage, and normalization freshness. Those are useful, but they require discipline.

Derived fields should be classified explicitly as either:

- **stable derived contract**: safe for consumers to use directly
- **advisory derived contract**: useful but allowed to evolve with caution
- **internal-only derived helper**: should not be exposed publicly

Without this classification, the API can become difficult to evolve because every convenience field becomes de facto permanent.

#### Tenant-scope contract posture

Tenant scoping is a real part of the business surface and should not remain only an implementation concern.

The API contract should explicitly state:

- whether `ownerId`, `vendorId`, or both are required per operation
- whether an absent tenant scope is valid, transitional, or forbidden
- whether tenant scope participates in uniqueness and dedupe expectations
- how tenant mismatch is represented in the error surface

#### Idempotency posture

RC hardening should treat write-semantics as part of the public contract.

At minimum, each write endpoint should be classified as one of:

- **idempotent by natural key**
- **idempotent by explicit request key**
- **idempotent only under exact payload replay**
- **not guaranteed idempotent**

This classification should be written down before further external integrations rely on retries.

#### Schema evolution posture

HTTP contract evolution and storage evolution must move together, but not be conflated.

Recommended rules:

- new columns should be additive first
- new response fields should be optional first
- dual-read or dual-shape support should be used when semantic migration is non-trivial
- cleanup/removal should happen only after the contract deprecation window closes
- outbox payload evolution should follow the same backward-compatibility discipline as HTTP payload evolution

#### RC target state

The Addressing API contract can be considered RC-ready when the following are all true:

- route inventory is documented and maintained
- request and response payload rules are explicit
- error shapes are stable across endpoints
- versioning and deprecation rules are written down
- idempotency posture is documented per write operation
- tenant-scope expectations are explicit
- outbox/event payload evolution follows the same compatibility discipline

#### Suggested next wave

The next contract-hardening wave should focus on:

1. documenting the route inventory
2. normalizing the error envelope
3. classifying public response fields by stability
4. writing endpoint-by-endpoint idempotency notes
5. linking schema evolution rules to the public contract

### Addressing RC Readiness Roadmap

#### Goal

Move the Addressing component from a strong engineering state (~7.5/10) to RC-ready by closing the remaining gaps in contract stability, observability, reliability semantics, and deployment discipline.

This roadmap is intentionally architecture-first and is designed to be executed in waves.

---

#### Wave 1 — Contract Hardening

Focus: eliminate API drift risk.

- produce a route inventory
- define request/response payload contracts
- normalize error envelope
- define idempotency per write endpoint
- define backward-compatibility rules
- define schema evolution rules (expand/contract)

Outcome:
- API surface becomes stable and predictable
- downstream consumers are protected from silent breakage

---

#### Wave 2 — Observability

Focus: make the component diagnosable in production.

- introduce correlation ID
- add structured domain logs
- add latency and error metrics
- instrument validation and governance flows
- define alert conditions (error rate, latency, outbox backlog)

Outcome:
- production behavior becomes observable
- incidents become debuggable

---

#### Wave 3 — Idempotency & Reliability

Focus: safe retries and predictable write behavior.

- classify all write endpoints by idempotency type
- define retry-safe vs retry-unsafe operations
- document failure-mode matrix
- align outbox semantics with transaction boundaries

Outcome:
- safe integration with retrying clients and queues
- reduced risk of duplicate or inconsistent state

---

#### Wave 4 — Validation Flow Decoupling

Focus: remove orchestration hotspots.

- isolate orchestration layer
- isolate mutation planning
- isolate evidence snapshot writing
- isolate outbox event construction
- isolate governance transition policy

Outcome:
- reduced hidden coupling
- improved maintainability and testability

---

#### Wave 5 — Testing to RC

Focus: close business-risk gaps.

- add contract tests for HTTP payloads
- add contract tests for outbox payloads
- add behavioral scenarios for governance and validation
- expand E2E coverage for critical paths

Outcome:
- tests validate real business guarantees, not only structure

---

#### Wave 6 — Deployment & Migration Discipline

Focus: safe rollout.

- introduce zero-downtime migration rules
- define rollback strategy
- classify release risk levels
- introduce feature flags for risky changes

Outcome:
- safe evolution under continuous deployment

---

#### RC Acceptance Criteria

The component can be considered RC-ready when:

- API contract is documented and stable
- schema evolution is controlled and backward-compatible
- validation flow is no longer a coupling hotspot
- outbox/event semantics are explicit and test-covered
- observability is implemented (logs, metrics, correlation)
- idempotency is defined for all write operations
- rollback and migration strategies are documented

---

#### Notes

The remaining work is not about adding more business features.
It is about making the existing system:

- predictable
- observable
- evolvable

This is the final step from "strong component" to "RC-grade system".

### Addressing Route Inventory

#### Purpose

This document provides the current endpoint inventory for the Addressing HTTP surface at the controller-method level.

It is meant to support RC hardening by making the public surface auditable. Where the exact route path is defined outside this document, the controller method should still be treated as part of the public contract inventory.

#### Inventory fields

Each endpoint should be tracked with the following attributes:

- controller method
- primary purpose
- write or read classification
- tenant-scope expectation
- idempotency posture
- response-shape class
- compatibility sensitivity

#### Current controller inventory

| Controller method | Purpose | Class | Tenant scope | Idempotency posture | Notes |
|---|---|---|---|---|---|
| `manage()` | Render management form page | read/ui | none or operator-facing | n/a | HTML surface, not primary JSON contract |
| `create()` | Create a new address record | write | should be explicit | not yet formally defined | high compatibility sensitivity |
| `get()` | Fetch a single address record | read | owner/vendor filter aware | n/a | stable single-record response candidate |
| `markDeleted()` | Soft-delete or mark an address deleted | write | owner/vendor filter aware | should be idempotent by record identity | currently returns simple ok payload |
| `page()` | Paginated address search/page result | read | owner/vendor filter aware | n/a | pagination contract should be frozen carefully |
| `queueSummary()` | Operational queue summary | read | owner/vendor filter aware | n/a | summary shape should be documented |
| `countryPortfolioSummary()` | Country portfolio summary | read | owner/vendor filter aware | n/a | aggregation response |
| `sourcePortfolioSummary()` | Source portfolio summary | read | owner/vendor filter aware | n/a | aggregation response |
| `validationPortfolioSummary()` | Validation portfolio summary | read | owner/vendor filter aware | n/a | aggregation response |
| `normalizationPortfolioSummary()` | Normalization portfolio summary | read | owner/vendor filter aware | n/a | aggregation response |
| `governanceClusterSummary()` | Governance cluster summary by record | read | owner/vendor filter aware | n/a | not-found behavior is contract-relevant |
| `patchOperational()` | Apply operational/governance patch to one record | write | owner/vendor filter aware | not yet formally defined | invalid governance transition is explicit error path |
| `patchOperationalBatch()` | Apply operational/governance patch to many records | write | owner/vendor filter aware | partial retry safety only until documented otherwise | failure shape should be stabilized |
| `applyValidated()` | Apply validation payload to an address | write | owner/vendor filter aware | not yet formally defined | highest write-path sensitivity |

#### RC contract priorities by endpoint

##### Highest-risk write endpoints

The following endpoints should be treated as first-priority RC contract targets:

1. `create()`
2. `patchOperational()`
3. `patchOperationalBatch()`
4. `applyValidated()`

These endpoints need explicit documentation for:

- required vs optional fields
- duplicate replay behavior
- tenant mismatch behavior
- not-found behavior
- invalid transition behavior
- stable success response shape

##### Highest-risk read endpoints

The following read endpoints have the highest compatibility sensitivity because consumers can build dashboards or workflows around them:

1. `page()`
2. `queueSummary()`
3. `validationPortfolioSummary()`
4. `governanceClusterSummary()`

These endpoints need explicit documentation for:

- filter semantics
- missing-filter defaults
- pagination semantics
- response field stability
- derived boolean stability

#### Idempotency target posture

The following target posture is recommended for RC hardening:

| Endpoint | Current posture | RC target posture |
|---|---|---|
| `create()` | not explicit | explicit non-idempotent or request-key idempotent |
| `markDeleted()` | likely naturally idempotent | explicit idempotent by record identity |
| `patchOperational()` | partial/implicit | explicit idempotent by resulting state or patch fingerprint |
| `patchOperationalBatch()` | partial batch semantics | explicit per-record idempotency and stable partial-failure contract |
| `applyValidated()` | partial/implicit via validation fingerprint and dedupe ideas | explicit idempotent-by-validation-identity posture |

#### Compatibility notes

##### Response-shape stability

The following response elements should be treated carefully because they can become consumer dependencies quickly:

- `items`
- `nextCursor`
- `failed`
- `patchedIds`
- derived flags such as evidence/review/normalization status fields

##### Error-shape stability

The following machine-readable error codes already appear or are strongly implied by the controller surface and should be normalized rather than allowed to drift:

- `not_found`
- `not_found_or_not_patched`
- `invalid_governance_transition`
- invalid JSON / invalid field / missing field families

#### Suggested next step

A follow-up wave should enrich this inventory with:

- exact route path
- exact HTTP method
- exact request schema reference
- exact response schema reference
- exact error list per endpoint
- exact compatibility owner

## Preservation audit: unresolved engineering values (2026-11-27)

- Localization (`aa7df603a7f2`, `4d1993fcd490`, `9f09c08127fb`): historical locale-specific normalization and localized line/city persistence, replacement/clear semantics and atomic rollback on invalid input. Current AddressFormatEntity represents format taxonomy; it is not equivalent to per-record localized values. Current source has no `line1Localized`, `cityLocalized` or `address_localization` contract. Determine canonical Doctrine model, tenant scope, API compatibility and migrations before admission; do not restore PDO/SQL authority.
- Contact data (`2b8ffbf24d9e`): historical tag/name/company/phone/email/raw fields. Current rawInputSnapshot is not proof of typed contact-field equivalence. Establish component ownership, privacy constraints, and migration/API compatibility; do not add contact PII blindly to Addressing.
- Outbox (`58739159e2f8`): current event registry and validated payload retain historical fields, but strict historical SHA-256, nonempty value and quality constraints require explicit boundary-equivalence review and targeted negative tests.
- CI: legacy strict/soft Domain gates are not current acceptance evidence. Current soft workflow requires separate remediation; a CI waiver for Collectioning is not an Addressing waiver.
- Operations: historical Postgres/MySQL migration, snapshot recovery, projection rebuild and rollback requirements require modern Doctrine-specific runbooks; obsolete commands are not authorized for execution.

## Host application critical-path disposition (2026-11-27)

- The current Ubuntu Host App tracked-source grep returned no callers of `line1Localized`, `cityLocalized` or `address_localization`. This is scoped evidence, not proof about external consumers or deployed databases. Preserve the localization and contact-data requirements as separate functional/ownership work; do not treat them as demonstrated prerequisites for the local Host App bootstrap.
- Host PR #681 at `1e7d87b7eb53854a904eed895cae8ddfd29b9782` has an obsolete `App\Addressing\EntityInterface\Record\AddressInterface` import in `src/Service/Shipping/ShipmentAddressService.php`. Addressing `AddressReadService::get()` returns `App\Addressing\Contract\AddressInterface`. Fix the consumer in the Host admission track and verify scoped shipment creation; no legacy alias in Addressing.
- September RC `7fc8a515248f5345e49bf482b87a1ce754d06ae0`: historical IP guard, CORS, request ID and security-header classes now exist under `src/Middleware/` and are called by the standalone `public/index.php`. Host-level enforcement is a distinct integration concern; this observation does not close the remaining September RC residuals.
- Legacy automation tip `e49940a445c2d8ee8e4bba99533cbcd1b384dbb5`: AI plan and Codex prompt generators in Domain/Tool; retain the historical objects, do not restore component-local orchestration scripts.
- Legacy automation tip `7ab4197d1e3ccac2d6b4c17941f79a493a474a17`: Start-Process command parsing and log redaction changes in AI apply, scan and canon check scripts; preserve the implementation in history, leave command execution ownership to the shared orchestration tooling. No claim that the current shared tooling reproduces every historical behavior.
- Legacy automation tip `769229104971433363fa5d5032005d716a2949e1`: deterministic report JSON sorting and validation; retain historical implementation, do not reactivate obsolete Domain overlay. Current workflow/template references to removed Domain tools require separate CI modernization.
- Admission of those three tooling tips is history preservation only, not a claim that obsolete automation is operational. The component runtime uses the current Symfony/Gating contracts; no source alias or duplicated Domain layer is introduced.

## Batch 5 — historical CI and operational disposition

The original commits below are retained as immutable historical evidence. Admission is an ancestry-preservation decision, not a certification of every historical capability. No external publishing, recovery operation, database mutation or hosted workflow execution is authorized by this record.

| Source commit | Disposition |
| --- | --- |
| `6179f286f935ae39ae8e29a07949d5b8388a5a52` | Historical PostgreSQL/MySQL migration and projection smoke intent: current Doctrine mapping/parity checks and in-memory AddressIndex projection repository/projector tests replace the supported development checks. Full projection rebuild/recovery remains an open operational capability. |
| `7f139c8683d347c31707fd35166316c4061c6755` | Mandatory Domain gate intent retained by current Composer trust-surface and Gating execution; missing legacy scripts cannot silently pass these replacement steps. Coverage warnings remain explicitly outstanding. |
| `dfd43cd1fb480c077f7594b925799853b468d28a` | PHPStan, Psalm, Rector, PHPUnit and evidence artifacts reconciled to current PHP 8.4 workflows. Historical max-level PHPStan target remains an improvement requirement; current verified configuration is level 8, not max. |
| `9d362173a516b22ce887f8405d72767deef1f3cc` | CI quality/evidence intent retained; historical dispatch/reusable agent PR workflows are preserved in history only. Do not reactivate unsigned commit, broad write-token, shell-interpolated input or Domain-overlay automation. |
| `c2ccde96a369864ccba9df204a23dcf703dd738a` | Fail-closed 80% line coverage threshold retained; restore Sonar quality-gate wait setting and JUnit/Clover evidence publication. Historical Sonar total/new-code coverage >=80% and zero blocker/critical requirements remain pending external configuration verification. |
| `ed443de3d633f606a86daebe1190dd38d4061bd4` | Current CI checks replace obsolete commands; preserve migration/recovery/rollback intent in the operational requirements below. |
| `79e12d173e845ad1d874c21f87a2a752cfb26286` | Historical GA hardening roadmap preserved; current supported runtime remains Doctrine entity-first. Do not equate the presence of old CLI files with operational readiness. |

### Evidence and deferred integrations

- GitHub and GitLab PHPUnit jobs now produce fresh JUnit, Clover and canonical coverage summary files; publish evidence on success or failure. Clover is not a Cobertura report. Sonar wait is configured, but the project still has a placeholder organization and no validated external scanner admission. No Sonar/Codecov credentials or external upload is configured by this change.
- Historical Behat/Allure, Codecov and Sonar integrations are preserved as optional external tooling requirements, not silently declared implemented. Do not restore remote shell downloads or unpinned scanner images as an admission shortcut.
- Hosted CI sibling Composer path provisioning, real PostgreSQL parity, full coverage and browser execution remain separate acceptance gates. No prior Collectioning CI waiver applies to Addressing.

### Operational preservation requirements (not an executable production runbook)

The old migration, recovery and rollback documents require coordinated writer/outbox quiescence, verified backups, restoration in dependency order, projection rebuild and validation of row counts, samples, timestamps and backlog before resuming consumers. Keep these invariants in the current PostgreSQL data / SQLite infrastructure topology. Restore and rehearse only on isolated databases; destructive production restoration requires explicit operational authorization and a verified recovery point.

Historical GA and RC requirements also include audit retention TTL, soft-delete cleanup, dedupe backfill, metrics export, durable incremental projection watermark, index-policy control and on-premises installation. Their presence in a historical README or surviving script does not prove a working current capability. In particular, current `bin/address-projection-common.php` references absent `App\Addressing\Service\Application\AddressProjectionService` and a retired MySQL connection. Do not execute its runner or daemon until reconciled to the Doctrine projector and validated with durable cursor/replay/rollback evidence. Audit and cleanup commands must not be run as part of admission.

The historical source objects remain available through the admitted commits; these deferred capabilities must be tracked explicitly and must not be used as release-readiness evidence.

## Batch 6 — RC closure preservation and outbox reservation integrity

- RC closure tip `eabd82a31be8634524d38478e1ea030eafe407bb`: recover its two journal entries (previous preservation checkpoint), retain the identical Doctrine baseline migration and mapping smoke, expanded object-identity regression and route-inventory/trust-surface successors. The old OpenAPI documentation entry point and canonical path are retired in favor of `config/openapi/address_openapi.yaml`; old required-field and contact/raw payload differences remain explicit compatibility questions under the still-open red-remediation and localization/contact review. No external compatibility guarantee follows from ancestry admission.
- The historical RC lock contains 170 distinct packages, all present by name in the current 177-package lock. Six local component reference pins changed; their own history and admission must be verified in their respective repositories. Keep the security-updated lock and present license, do not restore the old MIT declaration, copied .gating tree, historical max-level PHPStan setting or legacy fixer configuration. Current shared Gating and Commanding-managed tooling are the active authority; exact historical policy equivalence is not asserted.
- Preserve the RC closure as history-only after native signing; this does not close the still-open September RC, red-remediation, Operator, or feature-branch semantic review.
- Outbox dispatch now rejects malformed JSON, null, lists (including empty lists), and numeric-key payloads before invoking a sender; rejected rows are recorded as failed and the following valid row can proceed.
- Reservation now uses Doctrine DQL conditional updates guarded by unpublished/unlocked state and returns only rows with one affected row. Completion/failure transitions are conditional on currently locked, unpublished state and increment attempts in the database, without relying on managed entity snapshots. Isolated SQLite regression simulates a lost write and aborts the second claim to prove transaction rollback; repeated completion/failure after successful publication is a no-op. Bulk DQL bypasses Doctrine's identity map, so readers requiring fresh state must reload or refresh.
- This is not a claim of exactly-once delivery, PostgreSQL concurrent acceptance, lock-owner fencing or lease/crash recovery. The existing completion API accepts only a row ID; stale-owner acknowledgments across re-reservation and crashed-worker recovery remain separate contracts to resolve. SQLite contention may fail the batch and require retry; no webhook is sent in the added tests.
