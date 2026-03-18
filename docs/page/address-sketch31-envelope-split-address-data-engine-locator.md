address-sketch31-envelope-split-address-data-engine-locator

Decision boundary

- AddressData: storage + CRUD + normalization + projections + outbox. No external verification.
- AddressEngine: formatting/validation/parsing/normalization logic (pure library). No DB.
- AddressLocator: provider routing + geocoding/validation integrations. No storage of canonical records.

Envelope A (ATOM)
Goal

- Freeze component boundaries and produce a machine-readable map for the split.
  Slice
- ATOM
  Limits
- files_max: 5
- loc_max: 600
  Canon
- singular naming, mirror interface layers, EN-only comments, copyright header
  Inputs
- current Address repo
  Paths
- docs/**
- tools/**
  Outputs
- docs/address-split-map.md
- tools/address-classify.ps1 (report generator)
- report/address-split-candidate.csv
  Acceptance Criteria
- Script runs on Windows PowerShell and produces a CSV without errors.
- No TODO/stub placeholders.
  Notes
- This envelope does not move code yet; it makes the split reproducible.

Envelope B (BUCKET)
Goal

- Extract AddressEngine into a standalone component (pure library).
  Slice
- BUCKET
  Limits
- files_max: 16
- loc_max: 1200
  Canon
- singular naming, mirror interface layers, EN-only comments, copyright header
  Inputs
- src/Utility/AddressEngine/**
- src/Service/Parse/**
- src/Service/Normalize/**
  Paths
- src/Utility/AddressEngine/**
- src/Service/Parse/**
- src/Service/Normalize/**
  Outputs
- new repo: AddressEngine (package name address-engine)
- composer.json minimal library autoload
- tests moved with namespace update
  Acceptance Criteria
- phpunit passes for engine tests.
- AddressData repo no longer imports engine classes directly (use adapter interface).
  Notes
- If engine code depends on Symfony services, wrap them behind interfaces in AddressEngine.

Envelope C (BUCKET)
Goal

- Move provider routing and external verification into AddressLocator (or Locator component).
  Slice
- BUCKET
  Limits
- files_max: 16
- loc_max: 1200
  Canon
- singular naming, mirror interface layers, EN-only comments, copyright header
  Inputs
- any integration/provider classes (HTTP clients, geocode adapters)
  Paths
- src/Integration/**
- src/Util/** (locator-related)
  Outputs
- new repo: AddressLocator (or fold into existing Locator component)
- interface contracts: AddressLocateServiceInterface + AddressVerifyServiceInterface
  Acceptance Criteria
- AddressData exposes events/outbox for "AddressValidated" and consumes results via adapter.
  Notes
- If Locator already exists, prefer moving this logic into Locator and keeping AddressLocator repo empty.

Envelope D (BUCKET)
Goal

- Harden AddressData as a dedicated storage domain: entities, repository, projection, outbox.
  Slice
- BUCKET
  Limits
- files_max: 16
- loc_max: 1200
  Canon
- singular naming, mirror interface layers, EN-only comments, copyright header
  Inputs
- sql/postgres/**
- sql/mysql/**
- src/Entity/**
- src/Repository/**
- src/Service/Application/Address/AddressProjection.php
  Paths
- sql/**
- src/Entity/**
- src/Repository/**
- src/Service/**
  Outputs
- AddressData repo: clean namespaces, no duplicate root-level classes
- a single AddressRepositoryInterface in src/RepositoryInterface/Address
- smoke script (tools/smoke.ps1)
  Acceptance Criteria
- php -l clean for src/**
- no duplicate class name, no InterfaceInterface, no backup files
  Notes
- This is where we delete/move the root src/Address*.php duplicates.

Envelope E (ATOM)
Goal

- Safe purge of "(1)" and ".bak" files and duplicate interface shells.
  Slice
- ATOM
  Limits
- files_max: 5
- loc_max: 600
  Canon
- singular naming, mirror interface layers, EN-only comments, copyright header
  Inputs
- current Address repo
  Paths
- **/*.bak
- **/* (1).*
  Outputs
- tools/address-purge-duplicate.ps1 (dry-run + apply)
- report/address-sketch31-report-duplicate-candidate.csv
  Acceptance Criteria
- Dry-run prints planned deletions; apply deletes only the listed files.
  Notes
- Run this before any large refactor to reduce merge noise.
