# Address data model

This page documents the canonical `address_entity` contract as it exists across the current Address runtime slice. It is the field-level bridge between:

- `src/Entity/Record/AddressEntity.php`
- `src/EntityInterface/Record/AddressInterface.php`
- `src/Integration/Persistence/AddressSchemaManager.php` (SQLite runtime authority)
- `sql/postgres/001_address.sql`
- `sql/postgres/002_address_validation_verdict.sql`
- `sql/postgres/002_address_entity_contract_sync.sql`

The host application does **not** derive this schema from Doctrine metadata. The runtime schema is supplied by the Address persistence layer and the ordered Postgres SQL migrations.

## Columns

| Column | Type | Meaning | Invariant / notes |
| --- | --- | --- | --- |
| `id` | `CHAR(26)` | ULID primary key for the address record. | Required; 26 characters. |
| `owner_id` | `VARCHAR(64)` | Owning account/customer identifier. | Optional. |
| `vendor_id` | `VARCHAR(64)` | Upstream vendor identifier. | Optional. |
| `line1` | `VARCHAR(256)` | Primary street line as provided. | Required. |
| `line2` | `VARCHAR(256)` | Secondary address line as provided. | Optional. |
| `city` | `VARCHAR(128)` | Locality/city as provided. | Required. |
| `region` | `VARCHAR(128)` | State/province/region as provided. | Optional. |
| `postal_code` | `VARCHAR(32)` | Postal code as provided. | Optional. |
| `country_code` | `CHAR(2)` | ISO 3166-1 alpha-2 country code. | Required; exact length 2. |
| `line1_norm` | `VARCHAR(256)` | Normalized street line. | Optional; used in dedupe derivation. |
| `city_norm` | `VARCHAR(128)` | Normalized city. | Optional; used in dedupe derivation. |
| `region_norm` | `VARCHAR(128)` | Normalized region. | Optional; used in dedupe derivation. |
| `postal_code_norm` | `VARCHAR(32)` | Normalized postal code. | Optional; used in dedupe derivation. |
| `latitude` | `DOUBLE PRECISION` | Latitude for the address. | Optional. |
| `longitude` | `DOUBLE PRECISION` | Longitude for the address. | Optional. |
| `geohash` | `VARCHAR(32)` | Geohash derived from coordinates. | Optional. |
| `validation_status` | `VARCHAR(16)` | Current validation lifecycle status. | Required; allowed values: `unknown`, `pending`, `normalized`, `validated`, `rejected`, `uncertain`, `overridden`. |
| `validation_provider` | `VARCHAR(64)` | Provider that produced the latest validation state. | Optional. |
| `validated_at` | `TIMESTAMPTZ` | Timestamp when validation was applied. | Optional. |
| `source_system` | `VARCHAR(64)` | Source system for the current canonical record or validated apply. | Optional. |
| `source_type` | `VARCHAR(32)` | Source category for the current record. | Optional; normalized by `AddressRecordPolicy`. |
| `source_reference` | `VARCHAR(128)` | Upstream/reference identifier for the source event. | Optional. |
| `normalization_version` | `VARCHAR(64)` | Version/tag for normalization strategy used. | Optional. |
| `raw_input_snapshot` | `JSONB` | Raw input snapshot for provenance. | Optional. |
| `normalized_snapshot` | `JSONB` | Canonical normalized snapshot. | Optional. |
| `provider_digest` | `VARCHAR(64)` | Digest of provider/source payload lineage. | Optional. |
| `governance_status` | `VARCHAR(16)` | Governance state of the address row. | Required; allowed values: `canonical`, `duplicate`, `superseded`, `alias`, `conflict`. |
| `duplicate_of_id` | `CHAR(26)` | Canonical row that this row duplicates. | Optional. |
| `superseded_by_id` | `CHAR(26)` | Row that superseded this row. | Optional. |
| `alias_of_id` | `CHAR(26)` | Canonical row that this row aliases. | Optional. |
| `conflict_with_id` | `CHAR(26)` | Row that this row conflicts with. | Optional. |
| `validation_fingerprint` | `VARCHAR(64)` | Fingerprint of latest validation payload. | Optional; idempotency/support for short-circuiting. |
| `validation_raw` | `JSONB` | Raw provider response payload. | Optional. |
| `validation_verdict` | `JSONB` | Normalized provider verdict payload. | Optional. |
| `validation_deliverable` | `BOOLEAN` | Deliverability verdict. | Optional. |
| `validation_granularity` | `VARCHAR(64)` | Granularity of the verdict. | Optional. |
| `validation_quality` | `INT` | Numeric quality score from the verdict. | Optional. |
| `revalidation_due_at` | `TIMESTAMPTZ` | Timestamp when the row should be revalidated. | Optional. |
| `revalidation_policy` | `VARCHAR(16)` | Revalidation policy assigned to the row. | Optional; allowed values: `manual`, `on-change`, `daily`, `weekly`, `monthly`, `quarterly`, `semiannual`, `annual`. |
| `last_validation_provider` | `VARCHAR(64)` | Provider from the most recent validation attempt. | Optional. |
| `last_validation_status` | `VARCHAR(16)` | Status from the most recent validation attempt. | Optional; allowed values: `normalized`, `validated`, `rejected`, `uncertain`, `overridden`. |
| `last_validation_score` | `INT` | Score from the most recent validation attempt. | Optional. |
| `dedupe_key` | `VARCHAR(128)` | Canonical key for de-duplication. | Optional; unique when present; derived from normalized fields when possible. |
| `created_at` | `TIMESTAMPTZ` | Creation timestamp. | Required; defaulted on insert. |
| `updated_at` | `TIMESTAMPTZ` | Last update timestamp. | Optional; auto-touched by trigger on update. |
| `deleted_at` | `TIMESTAMPTZ` | Soft-delete marker. | Optional; non-null means logically deleted. |

## Invariants and derived behavior

- `country_code` is constrained to exactly two characters.
- `validation_status`, `governance_status`, `revalidation_policy`, and `last_validation_status` are normalized through `AddressRecordPolicy` and guarded by the schema surface.
- `dedupe_key` is auto-generated from normalized fields (`line1_norm`, `city_norm`, `region_norm`, `postal_code_norm`, `country_code`) when it is `NULL` and the normalized fields allow a stable canonical key.
- `updated_at` is touched automatically on update.
- `validation_fingerprint` and `provider_digest` are used for idempotency/provenance rather than as external-facing identifiers.
- `AddressEntity` remains the persistence record, but responsibility is now read through explicit state clusters (`validationState`, `governanceState`, `revalidationState`) rather than by treating the row as an unstructured field bag.
