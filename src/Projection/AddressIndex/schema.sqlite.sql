CREATE TABLE IF NOT EXISTS address_index (
  digest TEXT PRIMARY KEY,
  line1 TEXT NOT NULL,
  line2 TEXT NULL,
  city TEXT NOT NULL,
  region TEXT NOT NULL,
  postal TEXT NOT NULL,
  country TEXT NOT NULL,
  lat REAL NULL,
  lon REAL NULL,
  display TEXT NULL,
  provider TEXT NULL,
  confidence REAL NULL,
  geo_key TEXT DEFAULT '',
  created_at TEXT NOT NULL,
  updated_at TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_country_postal ON address_index(country, postal);
CREATE INDEX IF NOT EXISTS idx_city ON address_index(city);
CREATE INDEX IF NOT EXISTS idx_region ON address_index(region);
CREATE INDEX IF NOT EXISTS idx_geo ON address_index(geo_key);
