# Address — E4 Free-form Parser → Structured

Generated: 2025-10-28T06:34:11

Pipeline:

- Tokenizer: split by newline/comma; trim; drop empties
- CountryLexicon: aliases → ISO2 (US/CA/GB/DE/FR/IT/ES/PL/UA/MX)
- Parser: heuristics around postal/region (US/CA 2-letter, GB city←postal, EU city before postal); calls Normalizer

Artifacts:

- src/Service/Parse/CountryLexicon.php
- src/Service/Parse/Tokenizer.php
- src/Service/Parse/Parser.php
- tools/parse/parse.php (CLI)
- tests/Parse/ParserTest.php

Run:
php tools/parse/parse.php "123 Main St, Houston, TX 77002, USA"
composer run test

Notes:

- Фикстуры покрывают US/CA/GB/DE/FR/IT/ES/PL/UA/MX (≈17 примеров).
- Для production потребуется добить edge-cases (apt/unit, building, attention, company), это E4'→E4+ или отдельный E4B.
