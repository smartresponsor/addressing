address-sketch31-report-compile-repair

What changed
- Fixed PHP parse errors and made SQL blocks valid (replaced invalid triple-quote strings with nowdoc heredoc).
- Introduced App\\Entity\\Address\\AddressData implementing App\\EntityInterface\\Address\\AddressInterface.
- Updated PDO repository and MySQL projection to use interface getters (no property access).
- Normalized Value classes to avoid leading whitespace before '<?php' (prevents strict_types load-time fatal).

Files delivered (overlay)
- src/Entity/Address/AddressData.php (replaces broken src/Entity/Address/Address.php)
- src/Repository/Address/AddressRepository.php
- src/Service/Address/AddressProjection.php
- src/Value/CountryCode.php
- src/Value/GeoPoint.php
- src/Value/PostalCode.php
- src/Value/StreetLine.php
- src/Value/Subdivision.php

How to apply
- Extract this zip.
- Run tools/address-apply-overlay.ps1 (dry-run by default):
  - .\\tools\\address-apply-overlay.ps1 -RepoRoot C:\\path\\to\\Address
  - .\\tools\\address-apply-overlay.ps1 -RepoRoot C:\\path\\to\\Address -Apply

Validation
- Run: php -l on the touched files (or the whole src tree).
- Optional: run your existing test suite.
