-- ============================================================================
-- States & Cities catalog for directory / "Listed on eClinicPro".
--
-- Creates directory_states and binds directory_cities via state_id.
-- Safe to re-run (idempotent column/table guards).
--
-- Deploy:
--   mysql -u USER -p DB < app/database/patches/2026_07_10_directory_states_cities.sql
-- ============================================================================

-- 1) directory_states -------------------------------------------------------
CREATE TABLE IF NOT EXISTS `directory_states` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(80) NOT NULL,
  `slug` VARCHAR(80) NOT NULL,
  `country_code` CHAR(2) NOT NULL DEFAULT 'IN',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `sort_order` INT NOT NULL DEFAULT 100,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_state_slug_country` (`slug`, `country_code`),
  KEY `idx_state_active` (`is_active`, `sort_order`, `name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2) directory_cities.state_id -----------------------------------------------
SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'directory_cities'
      AND COLUMN_NAME = 'state_id'
);
SET @ddl := IF(
    @col_exists = 0,
    'ALTER TABLE `directory_cities`
        ADD COLUMN `state_id` INT UNSIGNED DEFAULT NULL AFTER `id`,
        ADD KEY `idx_city_state` (`state_id`)',
    'SELECT 1'
);
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 3) directory_cities.is_active ---------------------------------------------
SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'directory_cities'
      AND COLUMN_NAME = 'is_active'
);
SET @ddl := IF(
    @col_exists = 0,
    'ALTER TABLE `directory_cities`
        ADD COLUMN `is_active` TINYINT(1) NOT NULL DEFAULT 1 AFTER `is_featured`',
    'SELECT 1'
);
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 4) directory_cities.sort_order --------------------------------------------
SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'directory_cities'
      AND COLUMN_NAME = 'sort_order'
);
SET @ddl := IF(
    @col_exists = 0,
    'ALTER TABLE `directory_cities`
        ADD COLUMN `sort_order` INT NOT NULL DEFAULT 100 AFTER `is_active`',
    'SELECT 1'
);
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 5) Prefer unique (state_id, slug) so same city name can exist in 2 states --
SET @idx_exists := (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'directory_cities'
      AND INDEX_NAME = 'uq_state_city_slug'
);
SET @ddl := IF(
    @idx_exists = 0,
    'ALTER TABLE `directory_cities` ADD UNIQUE KEY `uq_state_city_slug` (`state_id`, `slug`)',
    'SELECT 1'
);
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 6) Seed Gujarat (+ major Maharashtra cities for convenience) --------------
INSERT INTO `directory_states` (`name`, `slug`, `country_code`, `is_active`, `sort_order`)
SELECT 'Gujarat', 'gujarat', 'IN', 1, 10
WHERE NOT EXISTS (SELECT 1 FROM `directory_states` WHERE `slug` = 'gujarat' AND `country_code` = 'IN');

INSERT INTO `directory_states` (`name`, `slug`, `country_code`, `is_active`, `sort_order`)
SELECT 'Maharashtra', 'maharashtra', 'IN', 1, 20
WHERE NOT EXISTS (SELECT 1 FROM `directory_states` WHERE `slug` = 'maharashtra' AND `country_code` = 'IN');

-- Gujarat cities
INSERT INTO `directory_cities`
  (`state_id`, `name`, `slug`, `state`, `country_code`, `lat`, `lng`, `doctor_count`, `is_featured`, `is_active`, `sort_order`)
SELECT s.id, v.name, v.slug, 'Gujarat', 'IN', v.lat, v.lng, 0, 0, 1, v.sort_order
FROM `directory_states` s
JOIN (
    SELECT 'Ahmedabad' AS name, 'ahmedabad' AS slug, 23.02250000 AS lat, 72.57140000 AS lng, 10 AS sort_order UNION ALL
    SELECT 'Surat', 'surat', 21.17020000, 72.83110000, 20 UNION ALL
    SELECT 'Vadodara', 'vadodara', 22.30720000, 73.18120000, 30 UNION ALL
    SELECT 'Rajkot', 'rajkot', 22.30390000, 70.80220000, 40 UNION ALL
    SELECT 'Bhavnagar', 'bhavnagar', 21.76450000, 72.15190000, 50 UNION ALL
    SELECT 'Gandhinagar', 'gandhinagar', 23.21560000, 72.63690000, 60 UNION ALL
    SELECT 'Jamnagar', 'jamnagar', 22.47070000, 70.05770000, 70 UNION ALL
    SELECT 'Junagadh', 'junagadh', 21.52220000, 70.45790000, 80 UNION ALL
    SELECT 'Surendranagar', 'surendranagar', 22.71960000, 71.63690000, 90 UNION ALL
    SELECT 'Anand', 'anand', 22.56450000, 72.92890000, 100 UNION ALL
    SELECT 'Bharuch', 'bharuch', 21.70510000, 72.99590000, 110 UNION ALL
    SELECT 'Mehsana', 'mehsana', 23.58790000, 72.36930000, 120 UNION ALL
    SELECT 'Nadiad', 'nadiad', 22.69170000, 72.86340000, 130 UNION ALL
    SELECT 'Navsari', 'navsari', 20.94670000, 72.95200000, 140 UNION ALL
    SELECT 'Morbi', 'morbi', 22.82520000, 70.84230000, 150 UNION ALL
    SELECT 'Vapi', 'vapi', 20.38930000, 72.91060000, 160 UNION ALL
    SELECT 'Valsad', 'valsad', 20.59920000, 72.93420000, 170 UNION ALL
    SELECT 'Porbandar', 'porbandar', 21.64170000, 69.62930000, 180 UNION ALL
    SELECT 'Gandhidham', 'gandhidham', 23.07530000, 70.13370000, 190 UNION ALL
    SELECT 'Bhuj', 'bhuj', 23.24200000, 69.66690000, 200 UNION ALL
    SELECT 'Patan', 'patan', 23.84930000, 72.12660000, 210 UNION ALL
    SELECT 'Palanpur', 'palanpur', 24.17220000, 72.43170000, 220 UNION ALL
    SELECT 'Veraval', 'veraval', 20.90770000, 70.36650000, 230 UNION ALL
    SELECT 'Godhra', 'godhra', 22.77880000, 73.61430000, 240 UNION ALL
    SELECT 'Himatnagar', 'himatnagar', 23.59800000, 72.95720000, 250
) v
WHERE s.slug = 'gujarat' AND s.country_code = 'IN'
  AND NOT EXISTS (
      SELECT 1 FROM `directory_cities` c
      WHERE c.state_id = s.id AND c.slug = v.slug
  );

-- Maharashtra cities (starter set)
INSERT INTO `directory_cities`
  (`state_id`, `name`, `slug`, `state`, `country_code`, `lat`, `lng`, `doctor_count`, `is_featured`, `is_active`, `sort_order`)
SELECT s.id, v.name, v.slug, 'Maharashtra', 'IN', v.lat, v.lng, 0, 0, 1, v.sort_order
FROM `directory_states` s
JOIN (
    SELECT 'Mumbai' AS name, 'mumbai' AS slug, 19.07600000 AS lat, 72.87770000 AS lng, 10 AS sort_order UNION ALL
    SELECT 'Pune', 'pune', 18.52040000, 73.85670000, 20 UNION ALL
    SELECT 'Nagpur', 'nagpur', 21.14580000, 79.08820000, 30 UNION ALL
    SELECT 'Nashik', 'nashik', 19.99750000, 73.78980000, 40 UNION ALL
    SELECT 'Thane', 'thane', 19.21830000, 72.97810000, 50 UNION ALL
    SELECT 'Navi Mumbai', 'navi-mumbai', 19.03300000, 73.02970000, 60 UNION ALL
    SELECT 'Aurangabad', 'aurangabad', 19.87620000, 75.34330000, 70 UNION ALL
    SELECT 'Kolhapur', 'kolhapur', 16.70500000, 74.24330000, 80
) v
WHERE s.slug = 'maharashtra' AND s.country_code = 'IN'
  AND NOT EXISTS (
      SELECT 1 FROM `directory_cities` c
      WHERE c.state_id = s.id AND c.slug = v.slug
  );
