# Address — E3 Normalization & Validation (10 countries)

Generated: 2025-10-28T06:31:18

Countries: US, CA, GB, DE, FR, IT, ES, PL, UA, MX

Includes:

- src/Service/Normalize/CountryRules.php (postal regexes, canonicalizers)
- src/Service/Normalize/Normalizer.php (normalize → VOs + digest)
- tests/Normalize/NormalizeTest.php (>=200 parameterized cases: 130 total)

How to run:
composer install
composer run test

Acceptance snapshot:

- Postal canonicalizers: US 5/9 with dash, CA with space, GB outward/inward space, PL NN-NNN
- Region/city/lines trimmed & collapsed
- Digest SHA-256 of canonical tuple
