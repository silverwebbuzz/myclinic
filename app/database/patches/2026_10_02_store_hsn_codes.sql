-- /store marketplace — Batch 7 — HSN master (admin-controlled GST rate per HSN) · `2026_10_02_store_hsn_codes.sql`
-- Source of truth: document/store-marketplace-plan.md, Appendix A (keep in sync).
-- Safe to re-run (CREATE TABLE IF NOT EXISTS / INSERT IGNORE).
--   phpMyAdmin: select database → Import → this file.
--
-- Sellers pick an HSN code from this list; the GST rate comes from here and can't be
-- typed freely. Matching is by longest prefix, so "3004" covers "30049011".
-- alt_rates: set ONLY for codes whose rate depends on the exact product (e.g. "0,500");
-- the seller then chooses among those rates and admin checks it at product review.

CREATE TABLE IF NOT EXISTS `store_hsn_codes` (
    `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `code`         VARCHAR(8)      NOT NULL,            -- 4, 6 or 8 digits
    `description`  VARCHAR(300)    NOT NULL,
    `gst_bp`       SMALLINT UNSIGNED NOT NULL,          -- default rate, 500 = 5%
    `alt_rates`    VARCHAR(40)     DEFAULT NULL,        -- "0,500" when the rate varies by product
    `notes`        VARCHAR(300)    DEFAULT NULL,
    `is_active`    TINYINT(1)      NOT NULL DEFAULT 1,
    `updated_by`   BIGINT UNSIGNED DEFAULT NULL,
    `created_at`   TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_shc_code` (`code`),
    KEY `idx_shc_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- DRAFT starting list for common health-store products (rates after the Sept 2025 GST
-- changes). ⚠ Every row must be CONFIRMED BY YOUR CA before go-live; edit in
-- /admin/store/hsn. Rates here are not tax advice.
INSERT IGNORE INTO `store_hsn_codes` (`code`, `description`, `gst_bp`, `alt_rates`, `notes`) VALUES
    ('3004', 'Medicaments in measured doses (tablets, capsules, syrups, ointments), incl. Ayurvedic / Homoeopathic', 500, '0,500', 'Draft: some life-saving drugs are 0%. Confirm with CA.'),
    ('3003', 'Medicaments not put up in measured doses', 500, NULL, 'Draft: confirm with CA.'),
    ('3005', 'Bandages, gauze, cotton wool, adhesive dressings', 500, NULL, 'Draft: confirm with CA.'),
    ('3006', 'First-aid boxes / kits and other pharmaceutical goods', 500, NULL, 'Draft: confirm with CA.'),
    ('3822', 'Diagnostic and laboratory reagents, test kits', 500, NULL, 'Draft: confirm with CA.'),
    ('4015', 'Surgical and examination gloves (rubber)', 500, NULL, 'Draft: confirm with CA.'),
    ('9018', 'Medical / surgical instruments (e.g. BP monitors, nebulisers, syringes, pulse oximeters)', 500, NULL, 'Draft: confirm with CA.'),
    ('9019', 'Massage, mechano-therapy and respiratory therapy apparatus', 500, '500,1800', 'Draft: massage devices may be 18%. Confirm with CA.'),
    ('9021', 'Orthopaedic appliances, supports, braces, hearing aids', 500, '0,500', 'Draft: hearing aids may be 0%. Confirm with CA.'),
    ('9025', 'Thermometers', 500, NULL, 'Draft: confirm with CA.'),
    ('9027', 'Instruments for analysis (e.g. glucometers)', 500, NULL, 'Draft: confirm with CA.'),
    ('9004', 'Spectacles and corrective goggles', 500, NULL, 'Draft: confirm with CA.'),
    ('8713', 'Wheelchairs and carriages for disabled persons', 500, NULL, 'Draft: confirm with CA.'),
    ('9619', 'Sanitary napkins, diapers, tampons and similar', 500, '0,500', 'Draft: sanitary napkins may be exempt (0%). Confirm with CA.'),
    ('1901', 'Infant food preparations, malt-based food', 500, NULL, 'Draft: confirm with CA. Infant food = no promotion (IMS Act).'),
    ('2106', 'Food preparations n.e.s. (protein and nutritional supplements, health drinks)', 1800, '500,1800', 'Draft: rate depends on the product. Confirm with CA.'),
    ('2202', 'Non-alcoholic beverages (electrolyte / energy drinks)', 1800, '500,1800,4000', 'Draft: sweetened/caffeinated drinks may be 40%. Confirm with CA.'),
    ('0902', 'Tea, incl. green and herbal tea blends', 500, NULL, 'Draft: confirm with CA.'),
    ('0409', 'Natural honey', 500, '0,500', 'Draft: confirm with CA.'),
    ('3304', 'Skin care, sunscreen, make-up, talcum powder', 1800, '500,1800', 'Draft: some items (e.g. talcum powder) may be 5%. Confirm with CA.'),
    ('3305', 'Hair care: shampoo, hair oil', 500, NULL, 'Draft: confirm with CA.'),
    ('3306', 'Oral care: toothpaste, tooth powder, dental floss', 500, NULL, 'Draft: confirm with CA.'),
    ('3307', 'Deodorants, shaving and bath preparations', 1800, NULL, 'Draft: confirm with CA.'),
    ('3401', 'Soap, body wash, liquid hand wash', 500, NULL, 'Draft: confirm with CA.'),
    ('3808', 'Disinfectants and hand sanitisers', 1800, NULL, 'Draft: confirm with CA.'),
    ('6307', 'Face masks and other made-up textile articles', 500, NULL, 'Draft: confirm with CA.');

-- Category default HSN (suggested on the product form) already exists:
-- store_categories.default_hsn — editable in /admin/store/categories.
