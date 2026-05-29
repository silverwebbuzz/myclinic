-- ============================================================
-- Extra catalog columns on `drugs` to capture the full richness of
-- the A-Z India medicines dataset (and future imports). The app's
-- prescription search still uses name/generic_name/strength/form;
-- these are additive and used by pharmacy/billing and display.
-- ============================================================

-- NOTE: `usage_count` + idx_drugs_usage already exist on live DBs
-- (added in install.sql), so they are intentionally NOT re-added here.
ALTER TABLE drugs
  ADD COLUMN manufacturer   VARCHAR(120) NULL AFTER drug_class,
  ADD COLUMN composition    VARCHAR(255) NULL AFTER strength,   -- raw "Amoxycillin (500mg), Clavulanic Acid (125mg)"
  ADD COLUMN pack_size      VARCHAR(80)  NULL AFTER form,        -- "strip of 10 tablets"
  ADD COLUMN medicine_type  VARCHAR(20)  NULL AFTER pack_size,   -- "allopathy" / "ayurvedic" / etc.
  ADD COLUMN mrp            DECIMAL(10,2) NULL AFTER medicine_type, -- pack price (₹) from source
  ADD COLUMN source_ref     VARCHAR(40)  NULL AFTER mrp;         -- original dataset id, for traceability

-- Helpful index for filtering by manufacturer.
ALTER TABLE drugs
  ADD INDEX idx_drugs_manufacturer (manufacturer);
