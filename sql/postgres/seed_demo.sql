-- Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
-- Demo seed data for Address domain (Postgres)

SET TIME ZONE 'UTC';

INSERT INTO address_entity (
    id,
    owner_id,
    vendor_id,
    line1,
    line2,
    city,
    region,
    postal_code,
    country_code,
    line1_norm,
    city_norm,
    region_norm,
    postal_code_norm,
    latitude,
    longitude,
    geohash,
    validation_status,
    validation_provider,
    validated_at,
    created_at,
    validation_fingerprint
) VALUES
      (
          '01J0DEMOADDR00000000000001',
          'demo-owner-1',
          'demo-vendor-1',
          '123 Market St',
          NULL,
          'San Francisco',
          'CA',
          '94105',
          'US',
          '123 market st',
          'san francisco',
          'ca',
          '94105',
          37.7890,
          -122.3940,
          '9q8yyk8',
          'validated',
          'demo',
          '2025-01-02T10:00:00Z',
          '2025-01-01T08:00:00Z',
          'demo-fp-1'
      ),
      (
          '01J0DEMOADDR00000000000002',
          'demo-owner-2',
          NULL,
          '500 Elm Ave',
          'Suite 200',
          'Austin',
          'TX',
          '78701',
          'US',
          '500 elm ave',
          'austin',
          'tx',
          '78701',
          30.2670,
          -97.7430,
          '9v6xj6f',
          'normalized',
          'demo',
          '2025-01-03T09:30:00Z',
          '2025-01-01T09:00:00Z',
          'demo-fp-2'
      );

INSERT INTO address_outbox (
    stream,
    event_name,
    event_version,
    payload,
    created_at,
    published_at,
    published_attempt,
    last_error
) VALUES
      (
          'address',
          'address.created',
          1,
          '{"address_id":"01J0DEMOADDR00000000000001","owner_id":"demo-owner-1","status":"validated"}'::jsonb,
          '2025-01-01T08:00:01Z',
          NULL,
          0,
          NULL
      ),
      (
          'address',
          'address.normalized',
          1,
          '{"address_id":"01J0DEMOADDR00000000000002","owner_id":"demo-owner-2","status":"normalized"}'::jsonb,
          '2025-01-01T09:00:01Z',
          NULL,
          0,
          NULL
      );
