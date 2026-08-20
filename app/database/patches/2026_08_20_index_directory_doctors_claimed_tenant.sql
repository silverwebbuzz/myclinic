-- Idempotent patch: index directory_doctors.claimed_tenant_id.
--
-- The dashboard checklist calls ClinicSettingsService::consultationFeeForClinic()
-- on every load, which runs:
--   SELECT * FROM directory_doctors WHERE claimed_tenant_id = ? AND is_active = 1
-- directory_doctors has ~77k rows and no index on claimed_tenant_id, so this was
-- a full table scan (~17s), making the whole dashboard take ~11-14s to render.
--
-- A composite (claimed_tenant_id, is_active) index covers the exact predicate.
--
-- Run on a live DB with:
--   mysql -u USER -p DB < app/database/patches/2026_08_20_index_directory_doctors_claimed_tenant.sql

SET @idx_exists := (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'directory_doctors'
      AND INDEX_NAME = 'idx_claimed_tenant_active'
);

SET @ddl := IF(
    @idx_exists = 0,
    'ALTER TABLE `directory_doctors`
        ADD KEY `idx_claimed_tenant_active` (`claimed_tenant_id`, `is_active`)',
    'SELECT 1'
);
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
