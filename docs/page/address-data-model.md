# Address data model

This page documents the `address_entity` table in Postgres. It captures both user-supplied address data and normalization/validation metadata.

## Columns

| Column | Type | Meaning | Invariant / notes |
| --- | --- | --- | --- |
| `id` | `CHAR(26)` | ULID primary key for the address record. | Required, primary key; 26 characters. |
| `owner_id` | `VARCHAR(64)` | Owning account/customer identifier. | Optional. |
| `vendor_id` | `VARCHAR(64)` | Upstream vendor identifier for the address record. | Optional. |
| `line1` | `VARCHAR(256)` | Primary street line as provided. | Required. |
| `line2` | `VARCHAR(256)` | Secondary address line as provided. | Optional. |
| `city` | `VARCHAR(128)` | Locality/city as provided. | Required. |
| `region` | `VARCHAR(128)` | Region/state/province as provided. | Optional. |
| `postal_code` | `VARCHAR(32)` | Postal/ZIP code as provided. | Optional. |
| `country_code` | `CHAR(2)` | ISO 3166-1 alpha-2 country code. | Required; length enforced to 2 characters. |
| `line1_norm` | `VARCHAR(256)` | Normalized street line. | Optional; used for dedupe key generation. |
| `city_norm` | `VARCHAR(128)` | Normalized city. | Optional; used for dedupe key generation. |
| `region_norm` | `VARCHAR(128)` | Normalized region. | Optional; used for dedupe key generation. |
| `postal_code_norm` | `VARCHAR(32)` | Normalized postal code. | Optional; used for dedupe key generation. |
| `latitude` | `DOUBLE PRECISION` | Latitude for the address. | Optional; set by validation/geo providers. |
| `longitude` | `DOUBLE PRECISION` | Longitude for the address. | Optional; set by validation/geo providers. |
| `geohash` | `VARCHAR(32)` | Geohash derived from coordinates. | Optional. |
| `validation_status` | `VARCHAR(16)` | Validation lifecycle status. | Required; allowed values: `unknown`, `normalized`, `validated`. |
| `validation_provider` | `VARCHAR(64)` | Provider that produced normalized/validated data. | Optional. |
| `validated_at` | `TIMESTAMPTZ` | Timestamp when validation was applied. | Optional; set when validation is applied. |
| `dedupe_key` | `VARCHAR(128)` | Canonical key for de-duplication. | Optional; unique when present; auto-filled from normalized fields when null and possible. |
| `created_at` | `TIMESTAMPTZ` | Creation timestamp for the row. | Required; defaults to `now()` on insert. |
| `updated_at` | `TIMESTAMPTZ` | Last update timestamp. | Optional; auto-touched by trigger on update. |
| `deleted_at` | `TIMESTAMPTZ` | Soft-delete marker. | Optional; non-null indicates deleted. |
| `validation_fingerprint` | `VARCHAR(64)` | Fingerprint of the latest validation payload. | Optional; used for idempotency checks. |
| `validation_raw` | `JSONB` | Raw provider response payload. | Optional; stored when provider raw data is supplied. |
| `validation_verdict` | `JSONB` | Normalized provider verdict payload. | Optional; stored when a verdict is supplied. |
| `validation_deliverable` | `BOOLEAN` | Deliverability verdict. | Optional; set from verdict when present. |
| `validation_granularity` | `VARCHAR(64)` | Granularity of the verdict (e.g., rooftop, street). | Optional; set from verdict when present. |
| `validation_quality` | `INT` | Numeric quality score from validation verdict. | Optional; set from verdict when present. |

## Invariants and derived behavior

- `country_code` is constrained to exactly two characters, and `validation_status` is constrained to `unknown`, `normalized`, or `validated`.
- `dedupe_key` is auto-generated from normalized fields (`line1_norm`, `city_norm`, `region_norm`, `postal_code_norm`, `country_code`) when it is `NULL` and the normalized fields allow a stable canonical key.
- `updated_at` is updated automatically on every row update via trigger.
- `validation_fingerprint` is used to short-circuit repeated validation payloads (idempotent apply).

