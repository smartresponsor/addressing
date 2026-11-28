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

## Batch 7A — historical data invariants and deferred feature contracts

- `9f09c08127fb82305e02ee6a791920aa1396a8ab`: the four legacy PDO test files cover CRUD and three outbox event shapes, Unicode field round-trip and normalized-field stability. Recovered their behavioral intent in `tests/Unit/Quality/AddressHistoricalPersistenceContractTest.php` using the actual Doctrine repositories and an in-memory database. The historical dual owner/vendor fixture conflicts with current mutually exclusive read scope; the recovered test explicitly uses owner scope only, preserves the nullable vendor payload key and does not weaken the scope contract. Timestamp comparison uses the current DATE_ATOM representation. This is a successor regression, not byte-for-byte restoration of every historical fixture.
- `aa7df603a7f2916275cb5c820765366126af023e`: preserve the localization feature design and original objects. Historical PostgreSQL table `address_localization` uses primary key (address_id, locale), FK cascade on physical deletion, locale length 16, nullable line1/city and timestamps, locale index. Locale-specific normalization examples include US Latin, Cyrillic Ukrainian/Russian and Japanese CJK. Do not copy the historical prefix-based script inference or restrictive regex as current validation: these omit legitimate mixed-script addresses and combining marks.
- `4d1993fcd490d2a667fb49f8ab4bda6f3d2233b5`: preserve historical localization CRUD APIs and transaction semantics. Required future acceptance: localized maps `line1Localized`/`cityLocalized` round-trip including Japanese Unicode and explicit empty strings, replacement with null/null clears all rows, non-string localized values fail atomically (address and outbox), scoped access and additive Doctrine migrations. Resolve conflicting locale lengths (historical schema 16, SQLite test 32), normalization/BCP 47 policy, absent vs explicit null vs empty, and soft-delete behavior before implementation. No localized-map persistence is claimed by the new Unicode test.
- `2b8ffbf24d9e334e88ff2bdfcbe6459ab840d71e`: preserve the contact/raw feature proposal, idempotent historical DDL and round-trip test. Legacy nullable fields: tag 64, name/company/email 128, phone 64, raw JSONB. Require explicit data ownership decision across Addressing and its consumers, scoped access, PII minimization/retention and migration/backfill design. Current raw validation evidence is not equivalent to arbitrary imported contact payload. Do not add these fields or expose them via OpenAPI until ownership and consumer compatibility are established.

These four tips are proposed for ancestry preservation. The tested invariants are active; localized-map and contact-field capabilities remain deferred, not implemented. Earlier tracked Host App grep found no localized-map callers; external consumers and existing database content are unverified. Keep the historical commits reachable and do not remove branch refs as part of this batch. No database migration or production operation is authorized. The separate strict outbox generation rules tip and three remaining RC/Operator tips still require review.

## Batch 7B — strict outbox generation

Historical tip `58739159e2f89608b1afe294dbd3559c13402aca` includes a four-event OpenAPI envelope, payload rules, PDO writer integration and unit fixtures. Its supported event names, ID, timestamps, SHA-256 and quality invariants are retained in the current message registry, persisted-event decoration and validated payload factory. The active registry also supports AddressOperationalPatched. Actual Doctrine writers and validated applier now call decoratePersistedPayload; metadata-only decoratePayload remains compatible with diagnostic callers. Persisted events require a nonblank ID, nonblank optional owner/vendor/provider/granularity when supplied, valid timezone-bearing DATE_ATOM or existing SQL-style event timestamp (canonicalized to DATE_ATOM) and two ASCII letters for created country code (uppercase normalization). Identity strings are not rewritten at the event boundary to avoid changing persisted identity. The current factory retains SHA-256 checks and direct verdict quality range 0–100; providerDigest remains opaque. Historical schema is not republished as the current OpenAPI contract; versioned consumer schema parity and external delivery acceptance remain open.

Regression verifies lowercase country and UTC timestamp normalization, missing/blank identity, invalid country, invalid date/calendar date, blank owner/provider and missing update timestamp. Historical PDO repository and applier are superseded by the current Doctrine transaction paths. No claim of exactly-once transport, lock-owner fencing or crash recovery. Preserve this tip by ancestry after native signing. The three RC/Operator tips remain open.

## Batch 7C — RC and Operator reconciliation, hosted checkout preparation

Historical scope is the original native 32-tip inventory (receipt SHA256 0ad7a35cf4d2e2ce43569217722ce7fb36081997bd38c54f1810779e0bf45f68). The currently published 28 tips are not a runtime completion percentage.

- September RC `7fc8a515248f5345e49bf482b87a1ce754d06ae0`: original 584-path comparison recorded 80 identical, 23 relocated, 108 different and 373 missing. The copied .gating tree and generated cache/config files are retired, not reintroduced. Exact old journal body (7,079 characters including heading) already exists in the current journal; no duplicate append. Verified successors include Doctrine schema manager delegating to schema repository, entity mapper, read/write/evidence/governance/operational/queue/portfolio/index repositories, rate-limit entity, geocode result, request factories and role-first index normalizer. Legacy record/interface names must not be restored. Current security middleware is retained, but host enforcement and real external database parity are separate gates. Older Doctrine and QA shapes are not automatically equivalent. Inventory-only paths and remaining unreviewed intermediate commits remain historical evidence, not claimed individually reconciled.
- Red Remediation `3354a8f6c4583b89a00bcb8f9096cdebee814a34`: original comparison 39 identical and 49 different, no missing paths. Exact old journal body (52,134 characters including heading) already exists in current journal. Repository/service interfaces and application read/write/evidence behavior, operational/read HTTP, index projector, ULID and schema tests retain the old source statements with current refinements. Current evidence history cursor uses last included row rather than first excluded row and clamps the effective limit; retain that improvement. Review flags now use typed arrays, not redundant boolean coercions. Form DTO assertions, fixture behavior, OpenAPI required/contact fields and changed policy/config contracts are not asserted universally equivalent. Do not restore older locks or stale tooling.
- Operator Wave `d39dc1f42e9240403e9e80d8aad5917b44a1951b`: old metadata registry, PDO writers and composite test harness superseded by current Doctrine/event paths. Outbox JSON and reservation defects were reproduced and repaired in Batch 6; strict generation in Batch 7B. Current trust surface reports ready with 13 route URI tokens and all 14 expected reports; Deptrac 147 source files zero violations, uncovered, warnings or errors. Current runtime/test-support reports ready, but these are structural reports, not execution receipts. Historical category host/container/fixture and browser readiness, PHPMD and execution-readiness tooling are retained in original objects only. Do not restore checked-in Windows chromedriver binary, obsolete PDO bootstrap or old controller routes. Actual isolated fixture-load, browser E2E, local/full security tooling and hosted execution are still acceptance obligations.

The last three tips are retained as ancestry-only preservation candidates with explicit limitations above; this is not a claim that every residual source diff has been individually semantically resolved. Keep branch references and original objects. Canonical GREEN requires a separate disposition of remaining obligations and validated release gates.

### Hosted dependency checkout

All eight locked path package references resolved via GitHub at the exact commits in the existing lock. Tabling's real remote is smartresponsor/tabling-; the other repository names correspond to the component name. Gating is private, unlike the seven public sibling repositories. New CI manifest validates exact path, allowlisted package/repository, SHA-1-shaped ref, uniqueness and completeness before any fetch. The hosted shell provisioner refuses existing sibling paths and missing credential, fetches exact commit without tags and verifies detached HEAD, package name and clean worktree. Both GitHub gates and GitLab run it before Composer; vendor cache removed. Master gate path filters include tools and config instead of retired Domain.

The proposed variable ADDRESSING_GATING_READ_TOKEN must be a repository-scoped, least-privilege read credential configured by the authorized CI owner for trusted workflows only. No credential was created/read, no real hosted checkout or authenticated private fetch has been exercised. Forks/untrusted contexts without credentials fail closed; do not enable pull_request_target to obtain secrets. Runner parent directory must be writable and disposable. No production configuration changes. Unit manifest positive/negative tests and bash syntax/missing-credential/existing-directory refusal pass; synthetic credential never used for network access. Hosted install, coverage, parity, browser and private credential acceptance remain open.

### Local quality disposition (current unsigned consolidation candidate)

Unit 165 tests / 940 assertions, integration 78 / 722 with isolated test DB and restored original PHPUnit XML, functional 10 / 116, security 7 / 27. Lint 240 files, PHPStan 201 files, CS Fixer 203 files, Rector dry-run 140 files and Deptrac 147 files passed. Gating 16 rules: 0 failures, 2 warnings for missing Canon040 text coverage and Canon042 behavioral UI evidence. Local exit 0 from Gating does not satisfy those missing-evidence gates.

Psalm independently failed (exit 2, durable run 2835c653-211c-4307-bc69-60f2189e7b94): 762 errors and 147 other issues, including 299 MissingPureAnnotation, 127 MissingAbstractPureAnnotation, 97 UnusedBaselineEntry, 55 UnusedClass, 38 PossiblyUnusedMethod, 27 MissingInterfaceImmutableAnnotation and 22 MissingOverrideAttribute diagnostics. Some issues relate to new/changed methods and discovered PHPUnit classes; static contract/type diagnostics also include InvalidDocblock, InvalidArgument, PossiblyNullArrayAccess and UndefinedInterfaceMethod. No baseline suppression, tool downgrade, or claim that these are all false positives. Full Psalm remediation and baseline reconciliation are a separate open quality gate before canonical merge. This candidate is preservation-ready, not release-ready.

## Local release remediation — Canon040 and Canon042

Baseline published checkpoint: `9d4908bf38e89f996fe385512acb9bfaa4f4b586`. Current unsigned successor changes are not yet present in that Git tree.

- Actual Xdebug non-browser run: 274 tests, 1,922 assertions, exit 0; 16 PHPUnit notices and one deprecation. Lines 3476/3873 (89.75%), methods 745/918 (81.15%), branches 2135/2577 (82.85%). Thresholds unchanged; browser Panther suite not included in this measurement. External test database variables forced empty for isolated tests; original Composer and PHPUnit configuration restored byte-identically.
- Reproduced SQLSTATE string exception-code defect in validated applier: previous code caused a TypeError during exception wrapping. Preserve original throwable as previous cause, use integer code when supplied and zero otherwise. Read nullable evidence raw array once before accessing issues. Regression also covers Outbox entity reload, normalization, IP policy, transport verdict and actual scoped CLI create/read/summary operations.
- Initial actual Playwright run reproduced read-only system session directory failure at HTTP page load. Standalone Kernel now uses Symfony ContainerConfigurator and configures native file session storage inside the selected runtime directory (or project var), separately per environment. Browser runner forces a fresh loopback server and unique SQLite path, no inherited external base URL, database path or server reuse. Test screenshots remain within local Playwright test output. Existing explicit port and Chromium executable settings retained; previous no-sandbox browser launch posture unchanged.
- Actual Playwright 1/1 PASS after correction; browser test submits manage form, verifies persisted address through owner-scoped API, foreign owner collection empty, foreign owner record HTTP 404. New v2 marker requires exact route and assertion tokens; npm pretest clears the preceding browser marker. Synthetic marker rejection and hosted workflow ordering tests use temporary directories and do not create admission evidence.
- GitHub master and GitLab coverage pipelines prepare unique SQLite server runtime, clear old functional/aggregate receipts, wait for HTTP readiness, run Playwright with locked npm install and Chromium installation, then aggregate actual evidence and run Gating. GitLab canon evaluation moved from separate smoke job to evidence-producing test job; PostgreSQL parity remains in smoke job. These hosted changes are locally parsed/tested, not remotely executed. GitHub private Gating credential and hosted runner admission remain open.
- Gating actual run: 16 rules, 0 failures, 0 warnings, 0 suppressed, 0 skipped. Canon040 passed; Canon042 functional 16/16, behavioral 2/2, UI 2/2, critical 1/1; these denominators describe declared inventory, not exhaustive feature coverage. Trust surface ready; PHPStan and CS Fixer passed.
- Durable local evidence SHA256: `var/coverage/summary.txt` ba5137f8f5cdee3f50bf313250f2b51c530b82bb0b0b5c23504fe4a4ea90c0ad; `var/coverage/address-http-functional.json` d1fa7da75b2f25baf0ca651a384ae8c061f870d435f026bf780005673adb8acb; `var/coverage/address-manage-playwright.json` 31182350561c9d6760982255f4561f8596fa502e70ac4c995b2ad5aaa2993251; `var/coverage/behavioral-ui.json` 1773ea2648e740b3d84149b99eb287fc49ff7892598108b90b8de3402c60ab11. Browser screenshot `test-results/address-ui-manage-page-ren-aae8f-an-create-an-address-record/address-manage-created.png` SHA256 5606571a39c1ef722c78466e1ffe4a93b20d68a59fd90b43944527b64fb5723d. These are generated local artifacts, not committed or immutable by themselves.
- Psalm rerun before final two CI receipt tests: durable run `7ec59343-d3f6-4042-890b-6e76eed6e96f`, exit 2, 759 errors and 142 other issues. The original SQLSTATE code mismatch and Kernel configureContainer signature mismatch no longer appear. Baseline unchanged. No claim of latest-final-tree Psalm success or canonical readiness. Panther independent execution, hosted CI, PostgreSQL parity/concurrency and remaining Psalm diagnostics are release blockers.

### Subsequent Psalm/type-contract remediation

- Reproduced queue cutoff defect: an address due at 12:00 UTC was not counted as due at equivalent 07:00 -05:00. Compare immutable instants instead of formatted strings. Regression covers UTC, negative and positive timezone offsets and one-second-before cutoff.
- Replaced unsupported array/object intersection annotation on grouped portfolio with explicitly typed metadata and integer counter pairs. Flatten only at the public repository boundary and test probe; retain counter precedence, row shape and ordering. Removed four existing local PHPStan ignores; constrain internal counter keys to initialized fields. Existing portfolio and CLI integration regressions pass.
- Made nullable raw evidence access and decoded cursor parts explicit; positive bounded pagination index and explicit ULID arithmetic types. Preserved the non-RuntimeException string-code regression using a deliberately synthetic exception fixture. Native PDOException is a RuntimeException and follows the separate passthrough path; its wrapping is not claimed to have failed.
- Psalm suggested Override on configureContainer, but actual PHP rejected the attribute on this private trait hook with a fatal error. Reverted the attempted attribute immediately; PHPStan passed afterward. Do not blindly annotate vendor hook methods.
- Unit 180/1062 and isolated integration 78/722 passed, PHPStan and CS Fixer passed, original PHPUnit XML restored byte-identically. Prior Canon040/042 receipts belong to the preceding unsigned source state and require a fresh final acceptance run.
- New Psalm execution refused by Ubuntu runtime capacity admission: RUNTIME_CAPACITY_DRAIN / RESOURCE_PRESSURE_CRITICAL, no process started. Last measured count remains 759, before this remediation. No baseline or policy changes; do not bypass resource admission.

### Psalm remediation pass 2

Runtime admission recovered. Actual Psalm measurements: 741 after preceding type fixes, 642 after read-contract annotation, 598 after repository I/O annotation, 564 after valid override and remaining interface contracts, 427 after verified direct entity accessors, and final 418 errors / 152 other issues (durable run 3f48d932-7b70-41af-afa3-d4126ce889b5, exit 2). Both psalm.xml and the entire psalm-baseline.xml compare identically against published 9d4908bf. No new suppressions. All missing abstract purity/interface mutability diagnostics resolved; 21 actual overrides added. Kernel private trait hook Override remains a verified PHP fatal if added. Symfony form generic annotation is required by PHPStan despite Psalm reporting TooManyTemplateParams; retain it. PCRE normalization explicitly acknowledges last-error state despite Psalm requesting pure. These conflicts need tool-compatible resolution, not false annotations. Address and evidence read contracts allow mutable legacy records and declare mutation-free accessors; 12 repository interfaces explicitly permit DB I/O; marker interfaces permit mutable implementations. Event metadata is immutable; identifier generation is impure due to entropy/time. Verified 137 direct entity setters/accessors against their exact bodies before annotating. No mapped fields or DI classes removed for unused-symbol findings. Portfolio legacy-compatible optional staleNormalization key now accurately reflects emitted shape. Reproduced NaN coordinate acceptance and added finite coordinate checks, with NaN/INF regression and explicit exception messages.

Fresh diagnostic Xdebug run 31d5ff46-9ab8-4720-a390-adc8a088db23: 275 tests / 1931 assertions, exit 0, 16 notices and one deprecation; lines 3493/3889 (89.82%), methods 746/918 (81.26%), branches 2137/2578 (82.89%). Coverage summary SHA256 c89fe0a26a8b3f791d4356ffccb1a94e575847366f3576546b8e549d1684d055. Original Composer SHA256 0dce8ca5ab1f4c074ef94716c0d0290ec674adcd9e172ad7faf584bdc441ca5e and PHPUnit XML SHA256 5b608f5e38e041ec2176ebaa172dfed3d0d642fd257229df97e23eb52850d3c8 restored exactly. A prior synchronous attempt exceeded its execution budget and was not admitted as evidence. PHPStan, CS and lint 242 files passed. Non-browser scope only; Psalm, Panther, hosted CI and actual PostgreSQL acceptance remain open.

Playwright durable run 700ba3be-f193-4d58-af91-08ce8035b7ad passed 1/1, and aggregate functional 16/16, behavioral/UI 2/2, critical 1/1. Browser receipt SHA256 6bbb607fcf6c4f665c154bdc2ee61dcb78875599572f3d5ba1993a26b467114f; aggregate SHA256 90c9ac4a8abc3e0a85ea1e37b7db1443f334fcf8f50eee9a2e27aca2599bef22. Initial Gating found Canon031 semantic PHPDoc 354/516 (68.6%). Completed semantic documentation on five value/criteria files, with no executable changes; final Canon031 368/516 (71.3%), class documentation 138/148 (93.2%), PHPStan and CS passed. Final Gating 16 rules, 0 failures, 2 warnings: Canon040 and Canon042 evidence predates these documentation-only edits. Do not refresh timestamps artificially; fresh final executions are required. Psalm 418 is the last completed measurement before the documentation-only edits. Candidate remains unsigned and release admission blocked.
