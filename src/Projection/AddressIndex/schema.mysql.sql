-- Address Index Read-Model (MySQL)
CREATE TABLE IF NOT EXISTS `address_index` (
  `digest` char(64) NOT NULL,
  `line1` varchar(160) NOT NULL,
  `line2` varchar(160) DEFAULT NULL,
  `city` varchar(160) NOT NULL,
  `region` varchar(32) NOT NULL,
  `postal` varchar(32) NOT NULL,
  `country` char(2) NOT NULL,
  `lat` decimal(9,6) DEFAULT NULL,
  `lon` decimal(9,6) DEFAULT NULL,
  `display` varchar(255) DEFAULT NULL,
  `provider` varchar(64) DEFAULT NULL,
  `confidence` decimal(4,3) DEFAULT NULL,
  `geo_key` varchar(64) DEFAULT '',
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`digest`),
  KEY `idx_country_postal` (`country`,`postal`),
  KEY `idx_city` (`city`),
  KEY `idx_region` (`region`),
  KEY `idx_geo` (`geo_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
