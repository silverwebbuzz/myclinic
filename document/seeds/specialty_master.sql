-- =============================================================
-- eClinicPro — specialty_master (single source of truth)
-- =============================================================
-- One catalog table for ALL specialties, shared by the doctor portal,
-- public directory, SEO pages, homepage, and admin. Replaces the old
-- hardcoded PHP lists (config/specialties.php, ecp_specialty_map,
-- find-doctor-data, mapSpecialty).
--
-- Two slugs, on purpose:
--   slug      — db value stored in tenants.specialty & directory_doctors.specialty
--               (e.g. 'cardio', 'gp', 'derma'). Used for filtering/matching.
--   url_slug  — SEO URL form (e.g. 'cardiologist', 'general-physician'). Used
--               in /find-a-doctor/{url_slug} and sitemap.
--
-- Custom clinical behaviour (homeopathy case-taking, dental chart, etc.) still
-- lives in code (App\Support\SpecialtyAdapter); has_custom_form flags it.
--
-- Labels are patient-friendly (match the public directory style) so the same
-- name shows on the homepage, find-a-doctor, and SEO pages.
--
-- MariaDB-native + idempotent: safe to re-run (IF NOT EXISTS + INSERT IGNORE).
-- =============================================================

CREATE TABLE IF NOT EXISTS `specialty_master` (
    `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `slug`              VARCHAR(40)  NOT NULL,            -- db value (tenants.specialty)
    `url_slug`          VARCHAR(50)  NOT NULL,            -- SEO URL form (/find-a-doctor/{url_slug})
    `label`             VARCHAR(80)  NOT NULL,            -- patient-friendly display name
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
    UNIQUE KEY `uq_specialty_url_slug` (`url_slug`),
    KEY `idx_specialty_active` (`is_active`, `category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---- Seed (INSERT IGNORE on slug → idempotent) ----
INSERT IGNORE INTO `specialty_master`
    (`slug`, `url_slug`, `label`, `plural_label`, `category`, `icon`, `prescription_mode`, `has_custom_form`, `seo_safe`, `sort_order`)
VALUES
-- Custom clinical layouts (has_custom_form = 1)
('gp',              'general-physician', 'General physician',   'General physicians',   'General & specialists',     '🩺', 'allopathic',  1, 1, 1),
('homeopathy',      'homeopathy',        'Homeopathy doctor',   'Homeopathy doctors',   'AYUSH & alternative',       '🌿', 'homeopathic', 1, 1, 2),
('dental',          'dentist',           'Dentist',             'Dentists',             'Dental',                    '🦷', 'dental',      1, 1, 3),
('derma',           'dermatologist',     'Dermatologist',       'Dermatologists',       'General & specialists',     '✨', 'both',        1, 1, 4),
('peds',            'pediatrician',      'Pediatrician',        'Pediatricians',        'Women, child, eye & ENT',   '👶', 'allopathic',  1, 1, 5),
('physio',          'physiotherapist',   'Physiotherapist',     'Physiotherapists',     'Therapy & nutrition',       '🤸', 'allopathic',  1, 1, 6),
-- General physicians & specialists
('family_medicine', 'family-medicine',   'Family medicine doctor','Family medicine doctors','General & specialists','🏠', 'allopathic',  0, 1, 10),
('cardio',          'cardiologist',      'Cardiologist',        'Cardiologists',        'General & specialists',     '❤️', 'allopathic',  0, 1, 11),
('neuro',           'neurologist',       'Neurologist',         'Neurologists',         'General & specialists',     '🧠', 'allopathic',  0, 1, 12),
('pulmonology',     'pulmonologist',     'Pulmonologist',       'Pulmonologists',       'General & specialists',     '🫁', 'allopathic',  0, 1, 13),
('gastro',          'gastroenterologist','Gastroenterologist',  'Gastroenterologists',  'General & specialists',     '🍽️', 'allopathic',  0, 1, 14),
('hepatology',      'hepatologist',      'Hepatologist',        'Hepatologists',        'General & specialists',     '🩸', 'allopathic',  0, 1, 15),
('endocrinology',   'endocrinologist',   'Endocrinologist',     'Endocrinologists',     'General & specialists',     '⚖️', 'allopathic',  0, 1, 16),
('diabetology',     'diabetologist',     'Diabetologist',       'Diabetologists',       'General & specialists',     '🩸', 'allopathic',  0, 1, 17),
('nephrology',      'nephrologist',      'Nephrologist',        'Nephrologists',        'General & specialists',     '💧', 'allopathic',  0, 1, 18),
('oncology',        'oncologist',        'Oncologist',          'Oncologists',          'General & specialists',     '🎗️', 'allopathic',  0, 1, 19),
('hematology',      'hematologist',      'Hematologist',        'Hematologists',        'General & specialists',     '🩸', 'allopathic',  0, 1, 20),
('urologist',       'urologist',         'Urologist',           'Urologists',           'General & specialists',     '🚹', 'allopathic',  0, 1, 21),
('andrology',       'andrologist',       'Andrologist',         'Andrologists',         'General & specialists',     '🚹', 'allopathic',  0, 1, 22),
('rheumatology',    'rheumatologist',    'Rheumatologist',      'Rheumatologists',      'General & specialists',     '🦴', 'allopathic',  0, 1, 23),
('allergy',         'allergist',         'Allergist',           'Allergists',           'General & specialists',     '🤧', 'allopathic',  0, 1, 24),
('pain_management', 'pain-management',   'Pain management specialist','Pain management specialists','General & specialists','💢','allopathic',0,1,25),
('trichology',      'trichologist',      'Trichologist',        'Trichologists',        'General & specialists',     '💇', 'both',        0, 1, 26),
('cosmetology',     'cosmetologist',     'Cosmetologist',       'Cosmetologists',       'General & specialists',     '💆', 'both',        0, 1, 27),
('sexology',        'sexologist',        'Sexologist',          'Sexologists',          'General & specialists',     '🚹', 'allopathic',  0, 0, 28),
-- Surgeons & critical care
('general_surgery', 'general-surgeon',   'General surgeon',     'General surgeons',     'Surgeons & critical care',  '🔪', 'allopathic',  0, 1, 30),
('neurosurgery',    'neurosurgeon',      'Neurosurgeon',        'Neurosurgeons',        'Surgeons & critical care',  '🧠', 'allopathic',  0, 1, 31),
('ortho',           'orthopedic',        'Orthopedic doctor',   'Orthopedic doctors',   'Surgeons & critical care',  '🦴', 'allopathic',  0, 1, 32),
('spine',           'spine-surgeon',     'Spine surgeon',       'Spine surgeons',       'Surgeons & critical care',  '🦴', 'allopathic',  0, 1, 33),
('plastic_surgery', 'plastic-surgeon',   'Plastic surgeon',     'Plastic surgeons',     'Surgeons & critical care',  '💉', 'allopathic',  0, 1, 34),
('bariatric',       'bariatric-surgeon', 'Bariatric surgeon',   'Bariatric surgeons',   'Surgeons & critical care',  '⚖️', 'allopathic',  0, 1, 35),
('vascular',        'vascular-surgeon',  'Vascular surgeon',    'Vascular surgeons',    'Surgeons & critical care',  '🫀', 'allopathic',  0, 1, 36),
('gi_surgery',      'gi-surgeon',        'GI surgeon',          'GI surgeons',          'Surgeons & critical care',  '🔪', 'allopathic',  0, 1, 37),
('sports_medicine', 'sports-medicine',   'Sports medicine doctor','Sports medicine doctors','Surgeons & critical care','🏃','allopathic',0,1,38),
('critical_care',   'critical-care',     'Critical care specialist','Critical care specialists','Surgeons & critical care','🚑','allopathic',0,1,39),
('radiology',       'radiologist',       'Radiologist',         'Radiologists',         'Surgeons & critical care',  '🩻', 'allopathic',  0, 1, 40),
-- Dental
('prosthodontist',  'prosthodontist',    'Prosthodontist',      'Prosthodontists',      'Dental',                    '🦷', 'dental',      0, 1, 50),
('orthodontist',    'orthodontist',      'Orthodontist',        'Orthodontists',        'Dental',                    '😁', 'dental',      0, 1, 51),
('pediatric_dentist','pediatric-dentist','Pediatric dentist',   'Pediatric dentists',   'Dental',                    '🪥', 'dental',      0, 1, 52),
('endodontist',     'endodontist',       'Endodontist',         'Endodontists',         'Dental',                    '🦷', 'dental',      0, 1, 53),
('implantologist',  'implantologist',    'Dental implant specialist','Dental implant specialists','Dental',         '🦷', 'dental',      0, 1, 54),
-- Women, children, eye & ENT
('gyno',            'gynecologist',      'Gynecologist',        'Gynecologists',        'Women, child, eye & ENT',   '🌸', 'allopathic',  0, 1, 60),
('fertility',       'fertility-specialist','Fertility specialist','Fertility specialists','Women, child, eye & ENT', '🤰', 'allopathic',  0, 1, 61),
('eye',             'ophthalmologist',   'Ophthalmologist',     'Ophthalmologists',     'Women, child, eye & ENT',   '👁️', 'allopathic',  0, 1, 62),
('ent',             'ent-specialist',    'ENT specialist',      'ENT specialists',      'Women, child, eye & ENT',   '👂', 'allopathic',  0, 1, 63),
-- AYUSH & alternative
('ayurveda',        'ayurveda',          'Ayurveda doctor',     'Ayurveda doctors',     'AYUSH & alternative',       '🪔', 'allopathic',  0, 1, 70),
('siddha',          'siddha',            'Siddha doctor',       'Siddha doctors',       'AYUSH & alternative',       '🌿', 'allopathic',  0, 1, 71),
('unani',           'unani',             'Unani doctor',        'Unani doctors',        'AYUSH & alternative',       '🌿', 'allopathic',  0, 1, 72),
('naturopathy',     'naturopathy',       'Naturopathy doctor',  'Naturopathy doctors',  'AYUSH & alternative',       '🍃', 'allopathic',  0, 1, 73),
('acupuncturist',   'acupuncturist',     'Acupuncturist',       'Acupuncturists',       'AYUSH & alternative',       '🪡', 'allopathic',  0, 1, 74),
-- Therapy & nutrition
('psychiatrist',    'psychiatrist',      'Psychiatrist',        'Psychiatrists',        'Therapy & nutrition',       '🧩', 'allopathic',  0, 1, 80),
('psychologist',    'psychologist',      'Psychologist',        'Psychologists',        'Therapy & nutrition',       '💭', 'allopathic',  0, 1, 81),
('audiologist',     'audiologist',       'Audiologist',         'Audiologists',         'Therapy & nutrition',       '👂', 'allopathic',  0, 1, 82),
('speech',          'speech-therapist',  'Speech therapist',    'Speech therapists',    'Therapy & nutrition',       '🗣️', 'allopathic',  0, 1, 83),
('dietitian',       'dietitian',         'Dietitian',           'Dietitians',           'Therapy & nutrition',       '🥗', 'allopathic',  0, 1, 84);
