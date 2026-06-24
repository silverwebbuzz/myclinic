-- Speed up prefix autocomplete (name LIKE 'althro%') on the 250k+ India catalog.
-- FULLTEXT handles most cases; this btree prefix index helps LIKE fallback.

SET @has_idx := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'drugs'
       AND INDEX_NAME = 'idx_drugs_name_prefix'
);

SET @sql := IF(
    @has_idx = 0,
    'ALTER TABLE drugs ADD INDEX idx_drugs_name_prefix (name(40))',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
