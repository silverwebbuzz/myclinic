-- 025_session_refresh_grace.sql
-- Problem: refresh-token rotation has no grace window. The doctor panel fires
--          several parallel requests (page nav + queue poll + autosave + symptom
--          save). When the access token expires, they all arrive with the same
--          mc_refresh; the FIRST rotates it (invalidating it) and the rest find
--          no session -> intermittent "Clinic not found" / 401 / 500.
-- Fix: remember the PREVIOUS token hash for a few seconds after rotation so
--      in-flight concurrent requests still authenticate.
-- Safe to re-run: information_schema guards.
-- phpMyAdmin: select your app database in the left sidebar first.

SET @col_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS
                    WHERE TABLE_SCHEMA = DATABASE()
                      AND TABLE_NAME = 'user_sessions'
                      AND COLUMN_NAME = 'prev_refresh_token_hash');
SET @ddl := IF(@col_exists = 0,
    'ALTER TABLE user_sessions
       ADD COLUMN prev_refresh_token_hash VARCHAR(64) NULL AFTER refresh_token_hash,
       ADD COLUMN prev_token_expires_at TIMESTAMP NULL AFTER prev_refresh_token_hash,
       ADD KEY idx_prev_refresh_hash (prev_refresh_token_hash)',
    'SELECT 1');
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Verify:
SELECT COLUMN_NAME FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_sessions'
  AND COLUMN_NAME IN ('prev_refresh_token_hash', 'prev_token_expires_at');
