# Addressing Route Inventory

## Purpose

This document provides the current endpoint inventory for the Addressing HTTP surface at the controller-method level.

It is meant to support RC hardening by making the public surface auditable. Where the exact route path is defined outside this document, the controller method should still be treated as part of the public contract inventory.

## Inventory fields

Each endpoint should be tracked with the following attributes:

- controller method
- primary purpose
- write or read classification
- tenant-scope expectation
- idempotency posture
- response-shape class
- compatibility sensitivity

## Current controller inventory

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

## RC contract priorities by endpoint

### Highest-risk write endpoints

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

### Highest-risk read endpoints

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

## Idempotency target posture

The following target posture is recommended for RC hardening:

| Endpoint | Current posture | RC target posture |
|---|---|---|
| `create()` | not explicit | explicit non-idempotent or request-key idempotent |
| `markDeleted()` | likely naturally idempotent | explicit idempotent by record identity |
| `patchOperational()` | partial/implicit | explicit idempotent by resulting state or patch fingerprint |
| `patchOperationalBatch()` | partial batch semantics | explicit per-record idempotency and stable partial-failure contract |
| `applyValidated()` | partial/implicit via validation fingerprint and dedupe ideas | explicit idempotent-by-validation-identity posture |

## Compatibility notes

### Response-shape stability

The following response elements should be treated carefully because they can become consumer dependencies quickly:

- `items`
- `nextCursor`
- `failed`
- `patchedIds`
- derived flags such as evidence/review/normalization status fields

### Error-shape stability

The following machine-readable error codes already appear or are strongly implied by the controller surface and should be normalized rather than allowed to drift:

- `not_found`
- `not_found_or_not_patched`
- `invalid_governance_transition`
- invalid JSON / invalid field / missing field families

## Suggested next step

A follow-up wave should enrich this inventory with:

- exact route path
- exact HTTP method
- exact request schema reference
- exact response schema reference
- exact error list per endpoint
- exact compatibility owner
