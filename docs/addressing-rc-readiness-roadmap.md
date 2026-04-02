# Addressing RC Readiness Roadmap

## Goal

Move the Addressing component from a strong engineering state (~7.5/10) to RC-ready by closing the remaining gaps in contract stability, observability, reliability semantics, and deployment discipline.

This roadmap is intentionally architecture-first and is designed to be executed in waves.

---

## Wave 1 — Contract Hardening

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

## Wave 2 — Observability

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

## Wave 3 — Idempotency & Reliability

Focus: safe retries and predictable write behavior.

- classify all write endpoints by idempotency type
- define retry-safe vs retry-unsafe operations
- document failure-mode matrix
- align outbox semantics with transaction boundaries

Outcome:
- safe integration with retrying clients and queues
- reduced risk of duplicate or inconsistent state

---

## Wave 4 — Validation Flow Decoupling

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

## Wave 5 — Testing to RC

Focus: close business-risk gaps.

- add contract tests for HTTP payloads
- add contract tests for outbox payloads
- add behavioral scenarios for governance and validation
- expand E2E coverage for critical paths

Outcome:
- tests validate real business guarantees, not only structure

---

## Wave 6 — Deployment & Migration Discipline

Focus: safe rollout.

- introduce zero-downtime migration rules
- define rollback strategy
- classify release risk levels
- introduce feature flags for risky changes

Outcome:
- safe evolution under continuous deployment

---

## RC Acceptance Criteria

The component can be considered RC-ready when:

- API contract is documented and stable
- schema evolution is controlled and backward-compatible
- validation flow is no longer a coupling hotspot
- outbox/event semantics are explicit and test-covered
- observability is implemented (logs, metrics, correlation)
- idempotency is defined for all write operations
- rollback and migration strategies are documented

---

## Notes

The remaining work is not about adding more business features.
It is about making the existing system:

- predictable
- observable
- evolvable

This is the final step from "strong component" to "RC-grade system".
