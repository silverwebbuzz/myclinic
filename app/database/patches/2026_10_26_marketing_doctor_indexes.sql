-- Idempotent patch: indexes for the /admin/marketing doctor list filters.
--
--   idx_dd_mkt_spec  — specialty filter + "unclaimed" + default quality sort:
--                      MySQL walks one specialty in sort order and stops after a page.
--   idx_dd_mkt_state — state / city filter and the state → city dropdown.
--
-- Run on a live DB with:
--   mysql -u USER -p DB < app/database/patches/2026_10_26_marketing_doctor_indexes.sql

SET @has := (SELECT COUNT(*) FROM information_schema.STATISTICS
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'directory_doctors'
                AND INDEX_NAME = 'idx_dd_mkt_spec');
SET @ddl := IF(@has = 0,
    'ALTER TABLE `directory_doctors` ADD KEY `idx_dd_mkt_spec` (`specialty`,`is_claimed`,`quality_score`,`rating`)',
    'SELECT 1');
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has := (SELECT COUNT(*) FROM information_schema.STATISTICS
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'directory_doctors'
                AND INDEX_NAME = 'idx_dd_mkt_state');
SET @ddl := IF(@has = 0,
    'ALTER TABLE `directory_doctors` ADD KEY `idx_dd_mkt_state` (`state`,`city`,`is_claimed`,`quality_score`)',
    'SELECT 1');
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
