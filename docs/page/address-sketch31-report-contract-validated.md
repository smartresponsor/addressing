Address-sketch31-7 (contract boundary)

What changed
- Added stable payload contract: App\Contract\Address\AddressValidated
- AddressValidatedApplierInterface now accepts AddressValidated instead of raw array
- Outbox event name standardized: address.validated.applied (v1)
- Added HTTP endpoint: POST /api/address/validated/apply

Request example
{
  "id": "01HF...ULID...",
  "validated": {
    "line1Norm": "10 downing st",
    "cityNorm": "london",
    "postalCodeNorm": "sw1a2aa",
    "countryCode": "GB",
    "latitude": 51.5033,
    "longitude": -0.1276,
    "validationProvider": "locator",
    "validatedAt": "2025-12-30T00:00:00Z"
  }
}

Notes
- This repo still defaults to sqlite for index search if DB_DSN is not set.
- ApplyValidated must point DB_DSN to Postgres where address_entity/address_outbox exist.
