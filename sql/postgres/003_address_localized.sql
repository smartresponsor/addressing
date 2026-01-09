-- Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
-- Localized address fields (Postgres)

CREATE TABLE IF NOT EXISTS address_localization (
  address_id CHAR(26) NOT NULL REFERENCES address_entity(id) ON DELETE CASCADE,
  locale VARCHAR(16) NOT NULL,
  line1 VARCHAR(256) NULL,
  city VARCHAR(128) NULL,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at TIMESTAMPTZ NULL,
  PRIMARY KEY (address_id, locale)
);

CREATE INDEX IF NOT EXISTS address_localization_locale_idx ON address_localization(locale);
