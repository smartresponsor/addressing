# Addressing API contract posture

## Purpose

This note defines the intended public-contract posture for the current Addressing slice so that future work can harden the component toward RC without accidental route, payload, or schema drift.

It is intentionally architecture-first. It does not attempt to describe every current endpoint in code-level detail. It defines the rules that should govern endpoint inventory, payload evolution, error-shape stability, and backward-compatible change discipline.

## Current posture

The current Addressing slice already exposes a meaningful HTTP API surface and uses dedicated HTTP factories to decode payloads, build filter structures, and shape output arrays. That is a strong base, but contract governance is still weaker than the code organization itself.

The main risk is not that the API is absent. The risk is that route shapes, field names, and write semantics could evolve faster than downstream consumers can safely absorb.

## Public surface categories

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

## Recommended contract inventory

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

## Versioning posture

The current slice can remain on a single API generation for now, but the following rules should apply immediately:

- additive fields are allowed when they do not change existing meaning
- field removal is not allowed without an explicit deprecation window
- changing a field type is a breaking change
- changing nullability from nullable to required is a breaking change
- changing governance or validation semantics behind an unchanged field name must be treated as a breaking behavioral change unless feature-gated
- changing default filtering behavior must be treated as a contract change

If a future route or media-type version is introduced, these rules should become the migration baseline.

## Error contract posture

Error payloads should converge on a stable shape:

- `error`: short stable machine-readable code
- `message`: optional human-oriented explanation
- `details`: optional structured validation or field-level detail
- `correlationId`: optional but strongly recommended for RC hardening

Errors should avoid free-form drift. If one endpoint returns `not_found` and another returns `missing_record`, that should be treated as avoidable contract drift unless the distinction is intentionally semantic.

## Derived-field posture

The component already exposes domain-derived semantics such as review reasons, evidence flags, governance linkage, and normalization freshness. Those are useful, but they require discipline.

Derived fields should be classified explicitly as either:

- **stable derived contract**: safe for consumers to use directly
- **advisory derived contract**: useful but allowed to evolve with caution
- **internal-only derived helper**: should not be exposed publicly

Without this classification, the API can become difficult to evolve because every convenience field becomes de facto permanent.

## Tenant-scope contract posture

Tenant scoping is a real part of the business surface and should not remain only an implementation concern.

The API contract should explicitly state:

- whether `ownerId`, `vendorId`, or both are required per operation
- whether an absent tenant scope is valid, transitional, or forbidden
- whether tenant scope participates in uniqueness and dedupe expectations
- how tenant mismatch is represented in the error surface

## Idempotency posture

RC hardening should treat write-semantics as part of the public contract.

At minimum, each write endpoint should be classified as one of:

- **idempotent by natural key**
- **idempotent by explicit request key**
- **idempotent only under exact payload replay**
- **not guaranteed idempotent**

This classification should be written down before further external integrations rely on retries.

## Schema evolution posture

HTTP contract evolution and storage evolution must move together, but not be conflated.

Recommended rules:

- new columns should be additive first
- new response fields should be optional first
- dual-read or dual-shape support should be used when semantic migration is non-trivial
- cleanup/removal should happen only after the contract deprecation window closes
- outbox payload evolution should follow the same backward-compatibility discipline as HTTP payload evolution

## RC target state

The Addressing API contract can be considered RC-ready when the following are all true:

- route inventory is documented and maintained
- request and response payload rules are explicit
- error shapes are stable across endpoints
- versioning and deprecation rules are written down
- idempotency posture is documented per write operation
- tenant-scope expectations are explicit
- outbox/event payload evolution follows the same compatibility discipline

## Suggested next wave

The next contract-hardening wave should focus on:

1. documenting the route inventory
2. normalizing the error envelope
3. classifying public response fields by stability
4. writing endpoint-by-endpoint idempotency notes
5. linking schema evolution rules to the public contract
