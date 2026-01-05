-- Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
-- Address read projection (MySQL)

CREATE TABLE IF NOT EXISTS address_projection (
  id CHAR(26) PRIMARY KEY,
  owner_id VARCHAR(64) NULL,
  vendor_id VARCHAR(64) NULL,
  line1 VARCHAR(256) NOT NULL,
  line2 VARCHAR(256) NULL,
  city VARCHAR(128) NOT NULL,
  region VARCHAR(128) NULL,
  postal_code VARCHAR(32) NULL,
  country_code CHAR(2) NOT NULL,
  validation_status VARCHAR(16) NOT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NULL,
  deleted_at DATETIME NULL,
  KEY address_projection_owner_idx (owner_id),
  KEY address_projection_vendor_idx (vendor_id),
  KEY address_projection_country_city_idx (country_code, city)
);
