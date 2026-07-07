-- Drop the unused `prescription_shares` table.
--
-- This was a scaffolded token-based "share a clinic Rx with a patient" design
-- that was NEVER wired to any code. Its purpose is now served by
-- `patient_prescriptions` + PatientPrescriptionShareService (source='clinic'),
-- so this table is dead weight.
--
-- SAFETY: nothing references prescription_shares (its FKs point OUT to
-- prescriptions + patient_identities; no table points into it), so dropping it
-- breaks nothing. The guard below aborts if the table somehow holds rows —
-- if that ever fires, inspect the data before forcing the drop.
--
--   phpMyAdmin: select database → Import → this file ONLY.

-- Guard: refuse to drop if it unexpectedly contains data.
SET @row_count := (
    SELECT COUNT(*) FROM information_schema.TABLES t
    WHERE t.TABLE_SCHEMA = DATABASE()
      AND t.TABLE_NAME = 'prescription_shares'
);
SET @has_rows := IF(
    @row_count = 0,
    0,
    (SELECT COUNT(*) FROM `prescription_shares`)
);

-- If rows exist, this SIGNAL stops the import so nothing is lost silently.
SET @msg := CONCAT('prescription_shares has ', @has_rows, ' row(s) — inspect before dropping');
DELIMITER //
DROP PROCEDURE IF EXISTS `ecp_drop_prescription_shares`//
CREATE PROCEDURE `ecp_drop_prescription_shares`()
BEGIN
    IF @has_rows > 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = @msg;
    ELSE
        DROP TABLE IF EXISTS `prescription_shares`;
    END IF;
END//
DELIMITER ;
CALL `ecp_drop_prescription_shares`();
DROP PROCEDURE IF EXISTS `ecp_drop_prescription_shares`;
