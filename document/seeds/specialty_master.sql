-- =============================================================
-- eClinicPro — specialty_master (single source of truth)
-- =============================================================
-- One catalog table for ALL specialties, shared by the doctor portal,
-- public directory, SEO pages, and admin. Replaces the three hardcoded
-- PHP lists (config/specialties.php, ecp_specialty_map, mapSpecialty).
--
-- Custom clinical behaviour (homeopathy case-taking, dental chart, etc.)
-- still lives in code (App\Support\SpecialtyAdapter); has_custom_form
-- flags which slugs have it so the app knows to load the bespoke form.
--
-- MariaDB-native + idempotent: safe to re-run (IF NOT EXISTS + INSERT IGNORE).
-- =============================================================

CREATE TABLE IF NOT EXISTS `specialty_master` (
    `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `slug`              VARCHAR(40)  NOT NULL,            -- stored in tenants.specialty
    `label`             VARCHAR(80)  NOT NULL,            -- display name everywhere
    `plural_label`      VARCHAR(100) NULL,               -- "Cardiologists" (directory/SEO)
    `category`          VARCHAR(60)  NOT NULL DEFAULT 'General & specialists',
    `icon`              VARCHAR(16)  NULL,                -- emoji
    `prescription_mode` ENUM('allopathic','homeopathic','dental','both') NOT NULL DEFAULT 'allopathic',
    `has_custom_form`   TINYINT(1)   NOT NULL DEFAULT 0, -- 1 = bespoke visit form in code
    `is_active`         TINYINT(1)   NOT NULL DEFAULT 1, -- show in portal/directory pickers
    `seo_safe`          TINYINT(1)   NOT NULL DEFAULT 1, -- show on marketing surfaces (sexology=0)
    `sort_order`        SMALLINT     NOT NULL DEFAULT 100,
    `created_at`        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_specialty_slug` (`slug`),
    KEY `idx_specialty_active` (`is_active`, `category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---- Seed (INSERT IGNORE on slug → idempotent) ----
INSERT IGNORE INTO `specialty_master`
    (`slug`, `label`, `plural_label`, `category`, `icon`, `prescription_mode`, `has_custom_form`, `seo_safe`, `sort_order`)
VALUES
-- Custom clinical layouts (has_custom_form = 1)
('gp',              'General Practice',  'General physicians',  'General & specialists',       '🩺', 'allopathic',  1, 1, 1),
('homeopathy',      'Homeopathy',        'Homeopathy doctors',  'AYUSH & alternative',         '🌿', 'homeopathic', 1, 1, 2),
('dental',          'Dental',            'Dentists',            'Dental',                      '🦷', 'dental',      1, 1, 3),
('derma',           'Dermatology',       'Dermatologists',      'General & specialists',       '✨', 'both',        1, 1, 4),
('peds',            'Pediatrics',        'Pediatricians',       'Women, child, eye & ENT',     '👶', 'allopathic',  1, 1, 5),
('physio',          'Physiotherapy',     'Physiotherapists',    'Therapy & nutrition',         '🤸', 'allopathic',  1, 1, 6),
-- General physicians & specialists
('family_medicine', 'Family Medicine',   'Family medicine doctors','General & specialists',    '🏠', 'allopathic',  0, 1, 10),
('cardio',          'Cardiologist',      'Cardiologists',       'General & specialists',       '❤️', 'allopathic',  0, 1, 11),
('neuro',           'Neurologist',       'Neurologists',        'General & specialists',       '🧠', 'allopathic',  0, 1, 12),
('pulmonology',     'Pulmonologist',     'Pulmonologists',      'General & specialists',       '🫁', 'allopathic',  0, 1, 13),
('gastro',          'Gastroenterologist','Gastroenterologists', 'General & specialists',       '🍽️', 'allopathic',  0, 1, 14),
('hepatology',      'Hepatologist',      'Hepatologists',       'General & specialists',       '🩸', 'allopathic',  0, 1, 15),
('endocrinology',   'Endocrinologist',   'Endocrinologists',    'General & specialists',       '⚖️', 'allopathic',  0, 1, 16),
('diabetology',     'Diabetologist',     'Diabetologists',      'General & specialists',       '🩸', 'allopathic',  0, 1, 17),
('nephrology',      'Nephrologist',      'Nephrologists',       'General & specialists',       '💧', 'allopathic',  0, 1, 18),
('oncology',        'Oncologist',        'Oncologists',         'General & specialists',       '🎗️', 'allopathic',  0, 1, 19),
('hematology',      'Hematologist',      'Hematologists',       'General & specialists',       '🩸', 'allopathic',  0, 1, 20),
('urologist',       'Urologist',         'Urologists',          'General & specialists',       '🚹', 'allopathic',  0, 1, 21),
('andrology',       'Andrologist',       'Andrologists',        'General & specialists',       '🚹', 'allopathic',  0, 1, 22),
('rheumatology',    'Rheumatologist',    'Rheumatologists',     'General & specialists',       '🦴', 'allopathic',  0, 1, 23),
('allergy',         'Allergist',         'Allergists',          'General & specialists',       '🤧', 'allopathic',  0, 1, 24),
('pain_management', 'Pain Management',   'Pain management specialists','General & specialists', '💢', 'allopathic',  0, 1, 25),
('trichology',      'Trichologist',      'Trichologists',       'General & specialists',       '💇', 'both',        0, 1, 26),
('cosmetology',     'Cosmetologist',     'Cosmetologists',      'General & specialists',       '💆', 'both',        0, 1, 27),
('sexology',        'Sexologist',        'Sexologists',         'General & specialists',       '🚹', 'allopathic',  0, 0, 28),
-- Surgeons & critical care
('general_surgery', 'General Surgeon',   'General surgeons',    'Surgeons & critical care',    '🔪', 'allopathic',  0, 1, 30),
('neurosurgery',    'Neurosurgeon',      'Neurosurgeons',       'Surgeons & critical care',    '🧠', 'allopathic',  0, 1, 31),
('ortho',           'Orthopedic Surgeon','Orthopedic doctors',  'Surgeons & critical care',    '🦴', 'allopathic',  0, 1, 32),
('spine',           'Spine Surgeon',     'Spine surgeons',      'Surgeons & critical care',    '🦴', 'allopathic',  0, 1, 33),
('plastic_surgery', 'Plastic Surgeon',   'Plastic surgeons',    'Surgeons & critical care',    '💉', 'allopathic',  0, 1, 34),
('bariatric',       'Bariatric Surgeon', 'Bariatric surgeons',  'Surgeons & critical care',    '⚖️', 'allopathic',  0, 1, 35),
('vascular',        'Vascular Surgeon',  'Vascular surgeons',   'Surgeons & critical care',    '🫀', 'allopathic',  0, 1, 36),
('gi_surgery',      'GI Surgeon',        'GI surgeons',         'Surgeons & critical care',    '🔪', 'allopathic',  0, 1, 37),
('sports_medicine', 'Sports Medicine',   'Sports medicine doctors','Surgeons & critical care', '🏃', 'allopathic',  0, 1, 38),
('critical_care',   'Critical Care',     'Critical care specialists','Surgeons & critical care','🚑', 'allopathic',  0, 1, 39),
('radiology',       'Radiologist',       'Radiologists',        'Surgeons & critical care',    '🩻', 'allopathic',  0, 1, 40),
-- Dental
('prosthodontist',  'Prosthodontist',    'Prosthodontists',     'Dental',                      '🦷', 'dental',      0, 1, 50),
('orthodontist',    'Orthodontist',      'Orthodontists',       'Dental',                      '😁', 'dental',      0, 1, 51),
('pediatric_dentist','Pediatric Dentist','Pediatric dentists',  'Dental',                      '🪥', 'dental',      0, 1, 52),
('endodontist',     'Endodontist',       'Endodontists',        'Dental',                      '🦷', 'dental',      0, 1, 53),
('implantologist',  'Dental Implant Specialist','Dental implant specialists','Dental',         '🦷', 'dental',      0, 1, 54),
-- Women, children, eye & ENT
('gyno',            'Gynecologist',      'Gynecologists',       'Women, child, eye & ENT',     '🌸', 'allopathic',  0, 1, 60),
('fertility',       'Fertility Specialist','Fertility specialists','Women, child, eye & ENT',   '🤰', 'allopathic',  0, 1, 61),
('eye',             'Ophthalmologist',   'Ophthalmologists',    'Women, child, eye & ENT',     '👁️', 'allopathic',  0, 1, 62),
('ent',             'ENT Specialist',    'ENT specialists',     'Women, child, eye & ENT',     '👂', 'allopathic',  0, 1, 63),
-- AYUSH & alternative
('ayurveda',        'Ayurveda',          'Ayurveda doctors',    'AYUSH & alternative',         '🪔', 'allopathic',  0, 1, 70),
('siddha',          'Siddha',            'Siddha doctors',      'AYUSH & alternative',         '🌿', 'allopathic',  0, 1, 71),
('unani',           'Unani',             'Unani doctors',       'AYUSH & alternative',         '🌿', 'allopathic',  0, 1, 72),
('naturopathy',     'Naturopathy',       'Naturopathy doctors', 'AYUSH & alternative',         '🍃', 'allopathic',  0, 1, 73),
('acupuncturist',   'Acupuncturist',     'Acupuncturists',      'AYUSH & alternative',         '🪡', 'allopathic',  0, 1, 74),
-- Therapy & nutrition
('psychiatrist',    'Psychiatrist',      'Psychiatrists',       'Therapy & nutrition',         '🧩', 'allopathic',  0, 1, 80),
('psychologist',    'Psychologist',      'Psychologists',       'Therapy & nutrition',         '💭', 'allopathic',  0, 1, 81),
('audiologist',     'Audiologist',       'Audiologists',        'Therapy & nutrition',         '👂', 'allopathic',  0, 1, 82),
('speech',          'Speech Therapist',  'Speech therapists',   'Therapy & nutrition',         '🗣️', 'allopathic',  0, 1, 83),
('dietitian',       'Dietitian',         'Dietitians',          'Therapy & nutrition',         '🥗', 'allopathic',  0, 1, 84);
