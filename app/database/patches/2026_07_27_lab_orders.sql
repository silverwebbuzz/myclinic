-- Lab booking orders — the "Book Now" form on lab-detail.php.
--
-- Before this patch the booking form was a client-side no-op: it validated,
-- showed a toast and reset itself. Nothing was ever persisted or emailed.
-- These tables are the system of record for a booking request.
--
-- THREE tables, because the three things have different lifetimes:
--   lab_orders            — one row per booking request (the money + logistics)
--   lab_order_items       — the itemised bill (package, add-on tests, fees)
--   lab_order_beneficiaries — who the samples are actually for
--
-- MONEY IS STORED IN PAISE (BIGINT), never rupees-as-float. Every amount the
-- patient sees is an integer number of rupees today, but storing paise means a
-- future ₹99.50 test can't silently round. Divide by 100 for display.
--
-- Amounts are SERVER-RECOMPUTED (see ecp_lab_price_order() in
-- partials/lab_orders.php) — the browser's numbers are only kept in
-- `client_total_paise` so a mismatch can be spotted. Never bill from that column.
--
-- Text SNAPSHOTS (product_name, city, state…) rather than foreign keys only:
-- a lab product can be renamed, repriced or deleted (the catalog cleanup
-- routinely deletes unavailable Thyrocare items — see
-- 2026_07_27_lab_remove_unavailable_products.sql). An order must still read
-- correctly years later regardless of what happened to the catalog.
--
-- Fresh-install style (no foreign keys) to match the live-server family and
-- the patient_lab_reports / patient_prescriptions patches, avoiding errno 150
-- when parent column types differ.
--
--   phpMyAdmin: select database → Import → this file ONLY.

CREATE TABLE IF NOT EXISTS `lab_orders` (
    `id`                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `order_ref`           VARCHAR(24)     NOT NULL,           -- human-quotable: ECPL-7K3M2Q (never expose the id)
    `patient_identity_id` BIGINT UNSIGNED NOT NULL,           -- booking requires login; always set

    -- What was booked (snapshot; product_id is audit-only, NOT a hard FK)
    `product_id`          BIGINT UNSIGNED DEFAULT NULL,
    `product_type`        VARCHAR(20)     NOT NULL DEFAULT 'package',  -- package | test
    `product_slug`        VARCHAR(190)    DEFAULT NULL,
    `product_name`        VARCHAR(255)    NOT NULL,

    -- Contact + logistics
    `contact_name`        VARCHAR(160)    NOT NULL,
    `contact_email`       VARCHAR(190)    NOT NULL,
    `contact_phone`       VARCHAR(20)     NOT NULL,           -- 10-digit as entered
    `address`             TEXT            NOT NULL,
    `pincode`             VARCHAR(10)     NOT NULL,
    `city`                VARCHAR(120)    DEFAULT NULL,
    `state`               VARCHAR(120)    DEFAULT NULL,
    `persons`             SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    `appointment_date`    DATE            NOT NULL,
    `time_slot`           VARCHAR(60)     NOT NULL,
    `notes`               TEXT            DEFAULT NULL,       -- patient instructions, optional
    `hard_copy`           TINYINT(1)      NOT NULL DEFAULT 0, -- ₹75 courier opted in

    -- Money, all in PAISE, all server-recomputed
    `package_paise`       BIGINT UNSIGNED NOT NULL DEFAULT 0, -- package line × persons
    `addons_paise`        BIGINT UNSIGNED NOT NULL DEFAULT 0, -- add-on tests × persons
    -- Coupon savings, summed ACROSS LINES. Each line is discounted by its own
    -- lab_product_pricing.max_discount_pct, so an order mixing a 25%-capped
    -- package with a 5%-capped add-on saves 25% and 5% respectively. The
    -- per-line breakdown lives in lab_order_items.
    `discount_paise`      BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `collection_paise`    BIGINT UNSIGNED NOT NULL DEFAULT 0, -- ₹200 when under threshold, else 0
    `courier_paise`       BIGINT UNSIGNED NOT NULL DEFAULT 0, -- ₹75 when hard_copy = 1
    `total_paise`         BIGINT UNSIGNED NOT NULL DEFAULT 0, -- the amount actually payable
    `client_total_paise`  BIGINT UNSIGNED DEFAULT NULL,       -- what the browser showed; DIAGNOSTIC ONLY
    `coupon_code`         VARCHAR(40)     DEFAULT NULL,
    -- The rate GRANTED on the package line (agrees with the SAVEnn code beside
    -- it). Add-on lines may have been discounted at their own lower rates.
    `coupon_pct`          TINYINT UNSIGNED NOT NULL DEFAULT 0,

    -- Order-level reporting figures. Both are DERIVED — they can be recomputed
    -- from the columns above — and are stored anyway because ops and finance
    -- filter and sort on them constantly ("show me every order over 20% off",
    -- "what did we give away last month?"). Deriving those in SQL on every
    -- query means a scan; a stored column can be indexed.
    --
    -- Written once at booking time and never recalculated on read, so an old
    -- order keeps the discount it was actually given even if a coupon, a
    -- product ceiling, or the fee rules change later.
    --
    -- effective_discount_pct is the BLENDED rate across the whole order
    -- (discount ÷ (package + addons) × 100) — NOT the coupon's headline %, and
    -- NOT coupon_pct. With mixed ceilings it lands between them: a ₹1000
    -- package at 25% plus a ₹500 add-on at 5% saves ₹275 of ₹1500 = 18%.
    -- Stored ×100 as basis points so 18.33% survives as 1833 rather than
    -- rounding to 18 — the rounding error would otherwise show up in any
    -- month-level average.
    `effective_discount_pct` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    -- Total the patient saved vs. undiscounted price: the coupon savings plus
    -- the MRP markdown already baked into offer_rate. This is the "you saved
    -- ₹X" figure, and it is deliberately NOT equal to discount_paise — that one
    -- is coupon-only. Fees are excluded from both; they are never discounted.
    `savings_paise`       BIGINT UNSIGNED NOT NULL DEFAULT 0,

    -- Lifecycle. Starts 'pending': no lab-partner API call and no payment step
    -- exists yet, so nothing is confirmed at submit time. The patient email says
    -- "request received" to match.
    `status`              ENUM('pending','confirmed','collected','reported','cancelled')
                          NOT NULL DEFAULT 'pending',
    `payment_status`      ENUM('unpaid','paid','refunded') NOT NULL DEFAULT 'unpaid',
    `admin_notes`         TEXT            DEFAULT NULL,       -- ops-only; never shown to the patient
    `cancelled_at`        TIMESTAMP       NULL DEFAULT NULL,

    -- Delivery audit: mail can fail silently (ecp_send_mail returns false), so
    -- record what actually went out rather than assuming.
    `patient_email_sent`  TINYINT(1)      NOT NULL DEFAULT 0,
    `admin_email_sent`    TINYINT(1)      NOT NULL DEFAULT 0,

    `source`              VARCHAR(40)     NOT NULL DEFAULT 'web',  -- web | mobile (future façade)
    `created_ip`          VARBINARY(16)   DEFAULT NULL,       -- inet_pton form; abuse triage
    `created_at`          TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`          TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_lo_ref` (`order_ref`),
    KEY `idx_lo_patient` (`patient_identity_id`, `created_at`),   -- the patient-panel list query
    KEY `idx_lo_status` (`status`, `appointment_date`),           -- ops worklist
    KEY `idx_lo_phone` (`contact_phone`),                         -- support lookup by phone
    -- "heaviest discounts first, this month" — the reason the derived
    -- discount columns are stored rather than computed on read.
    KEY `idx_lo_discount` (`created_at`, `effective_discount_pct`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Itemised bill lines. Rendered verbatim in the patient email and the panel's
-- order detail, so the patient always sees the same breakdown we stored.
-- Fee lines live here too (kind='collection'|'courier') so the bill reads top
-- to bottom without the display layer re-deriving anything.
CREATE TABLE IF NOT EXISTS `lab_order_items` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `order_id`      BIGINT UNSIGNED NOT NULL,
    `kind`          ENUM('package','addon','collection','courier','discount') NOT NULL,
    `product_id`    BIGINT UNSIGNED DEFAULT NULL,     -- audit only; NOT a hard FK
    `label`         VARCHAR(255)    NOT NULL,         -- snapshot of the name at booking time
    `unit_paise`    BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `qty`           SMALLINT UNSIGNED NOT NULL DEFAULT 1,  -- persons for package/addon, 1 for fees
    `amount_paise`  BIGINT UNSIGNED NOT NULL DEFAULT 0,    -- unit × qty (discount stored positive, shown as −)
    `sort_order`    SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    KEY `idx_loi_order` (`order_id`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Who the samples are for. Separate table because "Number of Persons" is a
-- 1..10 repeater on the form — cramming it into JSON would make the ops view
-- and any future per-person barcode/report mapping much harder.
--
-- NOT linked to family_member_identities: the form collects free-typed
-- name/age/gender and most beneficiaries won't exist as family records. A
-- future "book for a family member" flow can add a nullable link column.
CREATE TABLE IF NOT EXISTS `lab_order_beneficiaries` (
    `id`        BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `order_id`  BIGINT UNSIGNED NOT NULL,
    `name`      VARCHAR(160)    NOT NULL,
    `age`       SMALLINT UNSIGNED DEFAULT NULL,
    `gender`    VARCHAR(20)     DEFAULT NULL,       -- Male | Female | Other
    `position`  SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    PRIMARY KEY (`id`),
    KEY `idx_lob_order` (`order_id`, `position`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
