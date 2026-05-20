-- Note: database creation and selection is handled by the deployment process.
-- The connecting user is expected to already be scoped to the target database.

-- 01 tenants
CREATE TABLE tenants (
  id                      BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name                    VARCHAR(150) NOT NULL,
  slug                    VARCHAR(60) NOT NULL,
  custom_domain           VARCHAR(150) NULL,
  specialty               ENUM('gp','homeopathy','dental','derma','peds','physio','other') DEFAULT 'gp',
  country_code            CHAR(2) DEFAULT 'IN',
  currency                CHAR(3) DEFAULT 'INR',
  timezone                VARCHAR(50) DEFAULT 'Asia/Kolkata',
  plan                    ENUM('free','clinic','practice','enterprise') DEFAULT 'free',
  plan_expires_at         DATE NULL,
  trial_ends_at           DATE NULL,
  stripe_customer_id      VARCHAR(60) NULL,
  razorpay_customer_id    VARCHAR(60) NULL,
  white_label             TINYINT(1) DEFAULT 0,
  logo_path               VARCHAR(255) NULL,
  brand_color             CHAR(7) DEFAULT '#0F9B6E',
  gstin                   VARCHAR(20) NULL,
  address                 TEXT NULL,
  phone                   VARCHAR(20) NULL,
  email                   VARCHAR(150) NULL,
  is_active               TINYINT(1) DEFAULT 1,
  onboarding_step         TINYINT DEFAULT 1,
  onboarding_completed_at TIMESTAMP NULL,
  seat_limit              TINYINT UNSIGNED DEFAULT 2,
  extra_seats_purchased   TINYINT UNSIGNED DEFAULT 0,
  created_at              TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at              TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_slug (slug),
  UNIQUE KEY uq_domain (custom_domain)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 02 module_catalog
CREATE TABLE module_catalog (
  id                VARCHAR(40) NOT NULL,
  name              VARCHAR(100) NOT NULL,
  description       TEXT,
  category          ENUM('free','core','addon','platform'),
  price_monthly_usd DECIMAL(8,2) DEFAULT 0.00,
  price_yearly_usd  DECIMAL(8,2) DEFAULT 0.00,
  specialties       JSON,
  depends_on        JSON,
  included_in_plans JSON,
  icon              VARCHAR(40),
  is_active         TINYINT(1) DEFAULT 1,
  sort_order        SMALLINT DEFAULT 0,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 03 directory_cities (no FKs)
CREATE TABLE directory_cities (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name          VARCHAR(80) NOT NULL,
  slug          VARCHAR(80) NOT NULL,
  state         VARCHAR(80) NULL,
  country_code  CHAR(2) NOT NULL,
  lat           DECIMAL(10,8) NULL,
  lng           DECIMAL(11,8) NULL,
  doctor_count  INT UNSIGNED DEFAULT 0,
  is_featured   TINYINT(1) DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY uq_city_slug (slug, country_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 04 drugs (global catalog)
CREATE TABLE drugs (
  id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name              VARCHAR(150) NOT NULL,
  generic_name      VARCHAR(150) NULL,
  drug_class        VARCHAR(80) NULL,
  strength          VARCHAR(30) NULL,
  form              ENUM('tablet','capsule','syrup','injection','cream','drops','inhaler','patch','other'),
  contraindications TEXT NULL,
  interactions      JSON NULL,
  schedule          ENUM('H','H1','X','G','OTC') DEFAULT 'OTC',
  is_active         TINYINT(1) DEFAULT 1,
  PRIMARY KEY (id),
  FULLTEXT KEY idx_drug_search (name, generic_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 05 remedies (global catalog)
CREATE TABLE remedies (
  id                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name                 VARCHAR(100) NOT NULL,
  abbreviation         VARCHAR(20) NULL,
  source               ENUM('plant','mineral','animal','nosode','sarcode'),
  key_indications      TEXT NULL,
  antidotes            JSON NULL,
  complementaries      JSON NULL,
  dietary_restrictions TEXT NULL,
  is_active            TINYINT(1) DEFAULT 1,
  PRIMARY KEY (id),
  FULLTEXT KEY idx_remedy_search (name, abbreviation, key_indications)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 06 clinic_modules
CREATE TABLE clinic_modules (
  id                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  clinic_id          BIGINT UNSIGNED NOT NULL,
  module_id          VARCHAR(40) NOT NULL,
  activated_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  expires_at         DATE NULL,
  billing_cycle      ENUM('monthly','yearly','lifetime','free') DEFAULT 'monthly',
  stripe_sub_item_id VARCHAR(60) NULL,
  razorpay_sub_id    VARCHAR(60) NULL,
  is_trial           TINYINT(1) DEFAULT 0,
  is_active          TINYINT(1) DEFAULT 1,
  PRIMARY KEY (id),
  UNIQUE KEY uq_clinic_module (clinic_id, module_id),
  KEY idx_active (clinic_id, is_active, expires_at),
  CONSTRAINT fk_clinic_modules_clinic FOREIGN KEY (clinic_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_clinic_modules_module FOREIGN KEY (module_id) REFERENCES module_catalog(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 07 specialty_configs
CREATE TABLE specialty_configs (
  clinic_id             BIGINT UNSIGNED NOT NULL,
  prescription_mode     ENUM('allopathic','homeopathic','dental','both') DEFAULT 'allopathic',
  vitals_fields         JSON,
  visit_fields          JSON,
  invoice_tax_label     VARCHAR(10) DEFAULT 'GST',
  invoice_tax_percent   DECIMAL(5,2) DEFAULT 0.00,
  slot_duration_min     TINYINT DEFAULT 15,
  uhid_prefix           VARCHAR(10) DEFAULT 'MC',
  invoice_prefix        VARCHAR(10) DEFAULT 'INV',
  working_hours         JSON,
  whatsapp_number       VARCHAR(20) NULL,
  whatsapp_token        VARCHAR(255) NULL,
  razorpay_key          VARCHAR(80) NULL,
  razorpay_secret       VARCHAR(80) NULL,
  google_calendar_token TEXT NULL,
  updated_at            TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (clinic_id),
  CONSTRAINT fk_specialty_configs_clinic FOREIGN KEY (clinic_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 08 saas_invoices
CREATE TABLE saas_invoices (
  id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  clinic_id        BIGINT UNSIGNED NOT NULL,
  period_start     DATE NOT NULL,
  period_end       DATE NOT NULL,
  modules_billed   JSON,
  total_usd        DECIMAL(10,2) NOT NULL,
  stripe_invoice_id VARCHAR(60) NULL,
  razorpay_inv_id  VARCHAR(60) NULL,
  status           ENUM('draft','open','paid','void','uncollectable') DEFAULT 'open',
  paid_at          TIMESTAMP NULL,
  created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_clinic_period (clinic_id, period_start),
  CONSTRAINT fk_saas_invoices_clinic FOREIGN KEY (clinic_id) REFERENCES tenants(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 09 users
CREATE TABLE users (
  id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  clinic_id        BIGINT UNSIGNED NOT NULL,
  name             VARCHAR(100) NOT NULL,
  email            VARCHAR(150) NOT NULL,
  phone            VARCHAR(20) NULL,
  password_hash    VARCHAR(255) NOT NULL,
  role             ENUM('superadmin','admin','doctor','nurse','receptionist','labtech','patient') NOT NULL DEFAULT 'receptionist',
  is_owner         TINYINT(1) DEFAULT 0,
  custom_permissions JSON NULL,
  specialization   VARCHAR(100) NULL,
  qualification    VARCHAR(200) NULL,
  incentive_percent DECIMAL(5,2) DEFAULT 0.00,
  is_active        TINYINT(1) DEFAULT 1,
  remember_token   VARCHAR(100) NULL,
  last_login_at    TIMESTAMP NULL,
  created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_email (email),
  KEY idx_clinic_role (clinic_id, role),
  KEY idx_owner (clinic_id, is_owner),
  CONSTRAINT fk_users_clinic FOREIGN KEY (clinic_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 10 password_reset_tokens
CREATE TABLE password_reset_tokens (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  email       VARCHAR(150) NOT NULL,
  token_hash  VARCHAR(64) NOT NULL,
  expires_at  TIMESTAMP NOT NULL,
  used_at     TIMESTAMP NULL,
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_email_expires (email, expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 11 staff_invitations
CREATE TABLE staff_invitations (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  clinic_id       BIGINT UNSIGNED NOT NULL,
  invited_by      BIGINT UNSIGNED NOT NULL,
  name            VARCHAR(100) NOT NULL,
  email           VARCHAR(150) NOT NULL,
  role            ENUM('doctor','nurse','receptionist','labtech') NOT NULL,
  token_hash      VARCHAR(64) NOT NULL,
  expires_at      TIMESTAMP NOT NULL,
  accepted_at     TIMESTAMP NULL,
  created_user_id BIGINT UNSIGNED NULL,
  status          ENUM('pending','accepted','expired','revoked') DEFAULT 'pending',
  created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_clinic_invites (clinic_id, status),
  KEY idx_email (email, status),
  CONSTRAINT fk_staff_inv_clinic FOREIGN KEY (clinic_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_staff_inv_invited_by FOREIGN KEY (invited_by) REFERENCES users(id),
  CONSTRAINT fk_staff_inv_user FOREIGN KEY (created_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 12 patients
CREATE TABLE patients (
  id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  clinic_id           BIGINT UNSIGNED NOT NULL,
  user_id             BIGINT UNSIGNED NULL,
  uhid_seq            INT UNSIGNED DEFAULT 0,
  uhid                VARCHAR(20) NOT NULL,
  name                VARCHAR(100) NOT NULL,
  dob                 DATE NULL,
  gender              ENUM('M','F','Other'),
  phone               VARCHAR(20) NOT NULL,
  email               VARCHAR(150) NULL,
  address             TEXT NULL,
  blood_group         ENUM('A+','A-','B+','B-','O+','O-','AB+','AB-'),
  veg_type            ENUM('veg','nonveg','vegan','eggetarian') DEFAULT 'veg',
  allergies           TEXT NULL,
  chronic_conditions  TEXT NULL,
  specialty_data      JSON NULL,
  photo_path          VARCHAR(255) NULL,
  qr_token            VARCHAR(64) NOT NULL,
  insurance_provider  VARCHAR(100) NULL,
  insurance_id        VARCHAR(50) NULL,
  referred_by         VARCHAR(100) NULL,
  source              ENUM('walk_in','referral','online','camp','other') DEFAULT 'walk_in',
  is_active           TINYINT(1) DEFAULT 1,
  created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_uhid (clinic_id, uhid),
  UNIQUE KEY uq_qr (qr_token),
  KEY idx_name (clinic_id, name),
  KEY idx_phone (clinic_id, phone),
  FULLTEXT KEY idx_search (name, phone),
  CONSTRAINT fk_patients_clinic FOREIGN KEY (clinic_id) REFERENCES tenants(id),
  CONSTRAINT fk_patients_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 13 doctor_profiles
CREATE TABLE doctor_profiles (
  id                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id            BIGINT UNSIGNED NULL,
  clinic_id          BIGINT UNSIGNED NULL,
  slug               VARCHAR(100) NOT NULL,
  full_name          VARCHAR(150) NOT NULL,
  specialty_primary  VARCHAR(80) NOT NULL,
  specialties        JSON NULL,
  degrees            JSON NULL,
  experience_years   TINYINT UNSIGNED NULL,
  languages          JSON NULL,
  bio                TEXT NULL,
  photo_path         VARCHAR(255) NULL,
  consultation_fee   DECIMAL(10,2) NULL,
  currency           CHAR(3) DEFAULT 'INR',
  avg_rating         DECIMAL(3,2) DEFAULT 0.00,
  total_reviews      INT UNSIGNED DEFAULT 0,
  is_verified        TINYINT(1) DEFAULT 0,
  is_featured        TINYINT(1) DEFAULT 0,
  featured_until     DATE NULL,
  is_public          TINYINT(1) DEFAULT 1,
  profile_views      INT UNSIGNED DEFAULT 0,
  created_at         TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at         TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_slug (slug),
  KEY idx_specialty (specialty_primary, is_public),
  FULLTEXT KEY idx_dir_search (full_name, specialty_primary, bio),
  CONSTRAINT fk_doctor_profiles_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_doctor_profiles_clinic FOREIGN KEY (clinic_id) REFERENCES tenants(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 14 doctor_locations
CREATE TABLE doctor_locations (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  doctor_id     BIGINT UNSIGNED NOT NULL,
  clinic_name   VARCHAR(150) NULL,
  address       VARCHAR(250) NULL,
  city          VARCHAR(80) NOT NULL,
  state         VARCHAR(80) NULL,
  country_code  CHAR(2) NOT NULL DEFAULT 'IN',
  phone         VARCHAR(20) NULL,
  lat           DECIMAL(10,8) NULL,
  lng           DECIMAL(11,8) NULL,
  timing_json   JSON NULL,
  is_primary    TINYINT(1) DEFAULT 1,
  PRIMARY KEY (id),
  KEY idx_city (city, country_code),
  KEY idx_latlng (lat, lng),
  CONSTRAINT fk_doctor_locations_profile FOREIGN KEY (doctor_id) REFERENCES doctor_profiles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 15 doctor_reviews
CREATE TABLE doctor_reviews (
  id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  doctor_id           BIGINT UNSIGNED NOT NULL,
  reviewer_name       VARCHAR(80) NOT NULL,
  reviewer_phone_hash VARCHAR(64) NULL,
  rating              TINYINT NOT NULL,
  title               VARCHAR(100) NULL,
  body                TEXT NULL,
  condition_treated   VARCHAR(80) NULL,
  is_verified_patient TINYINT(1) DEFAULT 0,
  is_approved         TINYINT(1) DEFAULT 0,
  helpful_count       INT UNSIGNED DEFAULT 0,
  created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_doctor_rating (doctor_id, rating),
  CONSTRAINT fk_doctor_reviews_profile FOREIGN KEY (doctor_id) REFERENCES doctor_profiles(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 16 appointments
CREATE TABLE appointments (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  clinic_id       BIGINT UNSIGNED NOT NULL,
  patient_id      BIGINT UNSIGNED NOT NULL,
  doctor_id       BIGINT UNSIGNED NOT NULL,
  scheduled_at    DATETIME NOT NULL,
  slot_duration   TINYINT UNSIGNED DEFAULT 15,
  type            ENUM('walkin','prebooked','online','followup') DEFAULT 'prebooked',
  source          ENUM('reception','website','app','whatsapp','phone','google') DEFAULT 'reception',
  status          ENUM('scheduled','confirmed','in_progress','completed','cancelled','no_show') DEFAULT 'scheduled',
  chief_complaint TEXT NULL,
  token_number    SMALLINT UNSIGNED NULL,
  is_followup     TINYINT(1) DEFAULT 0,
  parent_visit_id BIGINT UNSIGNED NULL,
  reminder_sent   TINYINT(1) DEFAULT 0,
  notes           TEXT NULL,
  meet_link       VARCHAR(255) NULL,
  created_by      BIGINT UNSIGNED NULL,
  created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_doctor_date (doctor_id, scheduled_at),
  KEY idx_patient (clinic_id, patient_id),
  KEY idx_clinic_date (clinic_id, scheduled_at, status),
  CONSTRAINT fk_appt_clinic FOREIGN KEY (clinic_id) REFERENCES tenants(id),
  CONSTRAINT fk_appt_patient FOREIGN KEY (patient_id) REFERENCES patients(id),
  CONSTRAINT fk_appt_doctor FOREIGN KEY (doctor_id) REFERENCES users(id),
  CONSTRAINT fk_appt_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 17 doctor_schedules
CREATE TABLE doctor_schedules (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  clinic_id     BIGINT UNSIGNED NOT NULL,
  doctor_id     BIGINT UNSIGNED NOT NULL,
  day_of_week   TINYINT NOT NULL,
  start_time    TIME NOT NULL,
  end_time      TIME NOT NULL,
  slot_duration TINYINT UNSIGNED DEFAULT 15,
  max_patients  SMALLINT UNSIGNED DEFAULT 30,
  session_name  VARCHAR(40) NULL,
  is_active     TINYINT(1) DEFAULT 1,
  PRIMARY KEY (id),
  KEY idx_doctor_day (doctor_id, day_of_week, is_active),
  CONSTRAINT fk_doctor_sched_clinic FOREIGN KEY (clinic_id) REFERENCES tenants(id),
  CONSTRAINT fk_doctor_sched_doctor FOREIGN KEY (doctor_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 18 doctor_leaves
CREATE TABLE doctor_leaves (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  clinic_id  BIGINT UNSIGNED NOT NULL,
  doctor_id  BIGINT UNSIGNED NOT NULL,
  leave_date DATE NOT NULL,
  session    ENUM('full','morning','evening') DEFAULT 'full',
  reason     VARCHAR(200) NULL,
  PRIMARY KEY (id),
  KEY idx_doctor_leave (doctor_id, leave_date),
  CONSTRAINT fk_doctor_leaves_clinic FOREIGN KEY (clinic_id) REFERENCES tenants(id),
  CONSTRAINT fk_doctor_leaves_doctor FOREIGN KEY (doctor_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 19 visits
CREATE TABLE visits (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  clinic_id       BIGINT UNSIGNED NOT NULL,
  appointment_id  BIGINT UNSIGNED NULL,
  patient_id      BIGINT UNSIGNED NOT NULL,
  doctor_id       BIGINT UNSIGNED NOT NULL,
  visit_number    TINYINT UNSIGNED DEFAULT 1,
  chief_complaint TEXT NULL,
  history         TEXT NULL,
  examination     TEXT NULL,
  diagnosis       TEXT NULL,
  icd10_code      VARCHAR(10) NULL,
  clinical_notes  LONGTEXT NULL,
  specialty_data  JSON NULL,
  condition_score TINYINT UNSIGNED NULL,
  follow_up_date  DATE NULL,
  follow_up_notes TEXT NULL,
  visited_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_patient_visits (clinic_id, patient_id, visited_at),
  KEY idx_doctor_visits (clinic_id, doctor_id, visited_at),
  CONSTRAINT fk_visits_clinic FOREIGN KEY (clinic_id) REFERENCES tenants(id),
  CONSTRAINT fk_visits_patient FOREIGN KEY (patient_id) REFERENCES patients(id),
  CONSTRAINT fk_visits_doctor FOREIGN KEY (doctor_id) REFERENCES users(id),
  CONSTRAINT fk_visits_appt FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 20 vitals
CREATE TABLE vitals (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  clinic_id    BIGINT UNSIGNED NOT NULL,
  visit_id     BIGINT UNSIGNED NOT NULL,
  patient_id   BIGINT UNSIGNED NOT NULL,
  bp_systolic  SMALLINT UNSIGNED NULL,
  bp_diastolic SMALLINT UNSIGNED NULL,
  blood_sugar  DECIMAL(5,1) NULL,
  sugar_type   ENUM('fasting','pp','random') NULL,
  weight_kg    DECIMAL(5,2) NULL,
  height_cm    DECIMAL(5,1) NULL,
  bmi          DECIMAL(4,1) GENERATED ALWAYS AS
               (CASE WHEN height_cm > 0 THEN ROUND(weight_kg / ((height_cm/100) * (height_cm/100)),1) ELSE NULL END) STORED,
  temperature  DECIMAL(4,1) NULL,
  spo2         TINYINT UNSIGNED NULL,
  pulse_rate   SMALLINT UNSIGNED NULL,
  tsh          DECIMAL(6,3) NULL,
  t3           DECIMAL(6,2) NULL,
  t4           DECIMAL(6,2) NULL,
  skin_score   TINYINT UNSIGNED NULL,
  extra_vitals JSON NULL,
  recorded_by  BIGINT UNSIGNED NULL,
  recorded_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_patient_vitals (clinic_id, patient_id, recorded_at),
  CONSTRAINT fk_vitals_clinic FOREIGN KEY (clinic_id) REFERENCES tenants(id),
  CONSTRAINT fk_vitals_visit FOREIGN KEY (visit_id) REFERENCES visits(id),
  CONSTRAINT fk_vitals_patient FOREIGN KEY (patient_id) REFERENCES patients(id),
  CONSTRAINT fk_vitals_recorded_by FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 21 prescriptions
CREATE TABLE prescriptions (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  clinic_id     BIGINT UNSIGNED NOT NULL,
  visit_id      BIGINT UNSIGNED NOT NULL,
  patient_id    BIGINT UNSIGNED NOT NULL,
  mode          ENUM('allopathic','homeopathic') NOT NULL DEFAULT 'allopathic',
  drug_id       BIGINT UNSIGNED NULL,
  remedy_id     BIGINT UNSIGNED NULL,
  potency       VARCHAR(10) NULL,
  form          VARCHAR(30) NULL,
  dosage        VARCHAR(60) NULL,
  frequency     ENUM('OD','BD','TDS','QID','weekly','monthly','SOS','PRN'),
  duration_days SMALLINT UNSIGNED NULL,
  instructions  TEXT NULL,
  sort_order    TINYINT UNSIGNED DEFAULT 0,
  PRIMARY KEY (id),
  KEY idx_visit (visit_id),
  KEY idx_patient_rx (clinic_id, patient_id),
  CONSTRAINT fk_rx_clinic FOREIGN KEY (clinic_id) REFERENCES tenants(id),
  CONSTRAINT fk_rx_visit FOREIGN KEY (visit_id) REFERENCES visits(id) ON DELETE CASCADE,
  CONSTRAINT fk_rx_patient FOREIGN KEY (patient_id) REFERENCES patients(id),
  CONSTRAINT fk_rx_drug FOREIGN KEY (drug_id) REFERENCES drugs(id) ON DELETE SET NULL,
  CONSTRAINT fk_rx_remedy FOREIGN KEY (remedy_id) REFERENCES remedies(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 22 patient_allergies
CREATE TABLE patient_allergies (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  patient_id BIGINT UNSIGNED NOT NULL,
  clinic_id  BIGINT UNSIGNED NOT NULL,
  allergen   VARCHAR(100) NOT NULL,
  reaction   VARCHAR(200) NULL,
  severity   ENUM('mild','moderate','severe','life_threatening'),
  PRIMARY KEY (id),
  KEY idx_patient (patient_id),
  CONSTRAINT fk_allergies_patient FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
  CONSTRAINT fk_allergies_clinic FOREIGN KEY (clinic_id) REFERENCES tenants(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 23 invoices
CREATE TABLE invoices (
  id                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  clinic_id            BIGINT UNSIGNED NOT NULL,
  patient_id           BIGINT UNSIGNED NOT NULL,
  visit_id             BIGINT UNSIGNED NULL,
  attributed_doctor_id BIGINT UNSIGNED NULL,
  invoice_number       VARCHAR(20) NOT NULL,
  currency             CHAR(3) NOT NULL,
  subtotal             DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  discount_amount      DECIMAL(12,2) DEFAULT 0.00,
  tax_label            VARCHAR(10) DEFAULT 'GST',
  tax_percent          DECIMAL(5,2) DEFAULT 0.00,
  tax_amount           DECIMAL(12,2) DEFAULT 0.00,
  total                DECIMAL(12,2) NOT NULL,
  advance_paid         DECIMAL(12,2) DEFAULT 0.00,
  balance_due          DECIMAL(12,2) GENERATED ALWAYS AS (total - advance_paid) STORED,
  payment_mode         ENUM('cash','upi','card','online','insurance','credit') NULL,
  status               ENUM('draft','sent','partial','paid','overdue','refunded') DEFAULT 'draft',
  stripe_payment_id    VARCHAR(60) NULL,
  razorpay_order_id    VARCHAR(60) NULL,
  razorpay_payment_id  VARCHAR(60) NULL,
  notes                TEXT NULL,
  paid_at              TIMESTAMP NULL,
  due_date             DATE NULL,
  created_at           TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_inv_num (clinic_id, invoice_number),
  KEY idx_patient_invoices (clinic_id, patient_id, created_at),
  KEY idx_status (clinic_id, status, created_at),
  CONSTRAINT fk_inv_clinic FOREIGN KEY (clinic_id) REFERENCES tenants(id),
  CONSTRAINT fk_inv_patient FOREIGN KEY (patient_id) REFERENCES patients(id),
  CONSTRAINT fk_inv_visit FOREIGN KEY (visit_id) REFERENCES visits(id) ON DELETE SET NULL,
  CONSTRAINT fk_inv_attributed_doctor FOREIGN KEY (attributed_doctor_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 24 invoice_items
CREATE TABLE invoice_items (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  invoice_id  BIGINT UNSIGNED NOT NULL,
  description VARCHAR(200) NOT NULL,
  item_type   ENUM('consultation','procedure','medicine','lab','radiology','package','other'),
  qty         SMALLINT UNSIGNED DEFAULT 1,
  unit_price  DECIMAL(10,2) NOT NULL,
  discount    DECIMAL(10,2) DEFAULT 0.00,
  total       DECIMAL(10,2) GENERATED ALWAYS AS ((qty * unit_price) - discount) STORED,
  PRIMARY KEY (id),
  KEY idx_invoice (invoice_id),
  CONSTRAINT fk_invoice_items_inv FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 25 payments
CREATE TABLE payments (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  clinic_id   BIGINT UNSIGNED NOT NULL,
  invoice_id  BIGINT UNSIGNED NOT NULL,
  amount      DECIMAL(12,2) NOT NULL,
  method      ENUM('cash','upi','card','bank_transfer','online'),
  gateway_ref VARCHAR(80) NULL,
  notes       VARCHAR(200) NULL,
  paid_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  recorded_by BIGINT UNSIGNED NULL,
  PRIMARY KEY (id),
  KEY idx_invoice_payments (invoice_id),
  CONSTRAINT fk_payments_clinic FOREIGN KEY (clinic_id) REFERENCES tenants(id),
  CONSTRAINT fk_payments_invoice FOREIGN KEY (invoice_id) REFERENCES invoices(id),
  CONSTRAINT fk_payments_recorded_by FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 26 lab_tests_catalog
CREATE TABLE lab_tests_catalog (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  clinic_id   BIGINT UNSIGNED NULL,
  test_code   VARCHAR(20) NOT NULL,
  test_name   VARCHAR(150) NOT NULL,
  category    ENUM('haematology','biochemistry','microbiology','serology','histopathology','other'),
  parameters  JSON NOT NULL,
  sample_type ENUM('blood','urine','stool','swab','csf','tissue','other'),
  tat_hours   TINYINT UNSIGNED DEFAULT 24,
  rate        DECIMAL(8,2) DEFAULT 0.00,
  is_panel    TINYINT(1) DEFAULT 0,
  panel_tests JSON NULL,
  is_active   TINYINT(1) DEFAULT 1,
  PRIMARY KEY (id),
  KEY idx_code (clinic_id, test_code),
  FULLTEXT KEY idx_test_search (test_name),
  CONSTRAINT fk_lab_catalog_clinic FOREIGN KEY (clinic_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 27 lab_orders
CREATE TABLE lab_orders (
  id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  clinic_id           BIGINT UNSIGNED NOT NULL,
  patient_id          BIGINT UNSIGNED NOT NULL,
  visit_id            BIGINT UNSIGNED NULL,
  ordered_by          BIGINT UNSIGNED NOT NULL,
  test_id             BIGINT UNSIGNED NOT NULL,
  barcode             VARCHAR(30) NULL,
  sample_collected_at TIMESTAMP NULL,
  collected_by        BIGINT UNSIGNED NULL,
  report_path         VARCHAR(255) NULL,
  share_token         VARCHAR(64) NULL,
  status              ENUM('ordered','sample_collected','processing','resulted','verified','shared') DEFAULT 'ordered',
  resulted_at         TIMESTAMP NULL,
  ordered_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_patient_labs (clinic_id, patient_id, ordered_at),
  CONSTRAINT fk_lab_orders_clinic FOREIGN KEY (clinic_id) REFERENCES tenants(id),
  CONSTRAINT fk_lab_orders_patient FOREIGN KEY (patient_id) REFERENCES patients(id),
  CONSTRAINT fk_lab_orders_visit FOREIGN KEY (visit_id) REFERENCES visits(id) ON DELETE SET NULL,
  CONSTRAINT fk_lab_orders_ordered_by FOREIGN KEY (ordered_by) REFERENCES users(id),
  CONSTRAINT fk_lab_orders_test FOREIGN KEY (test_id) REFERENCES lab_tests_catalog(id),
  CONSTRAINT fk_lab_orders_collected_by FOREIGN KEY (collected_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 28 lab_results
CREATE TABLE lab_results (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  lab_order_id   BIGINT UNSIGNED NOT NULL,
  parameter_name VARCHAR(80) NOT NULL,
  value          VARCHAR(30) NOT NULL,
  unit           VARCHAR(20) NULL,
  normal_range   VARCHAR(40) NULL,
  flag           ENUM('normal','low','high','critical_low','critical_high') DEFAULT 'normal',
  entered_by     BIGINT UNSIGNED NOT NULL,
  verified_by    BIGINT UNSIGNED NULL,
  entered_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_order (lab_order_id),
  CONSTRAINT fk_lab_results_order FOREIGN KEY (lab_order_id) REFERENCES lab_orders(id) ON DELETE CASCADE,
  CONSTRAINT fk_lab_results_entered_by FOREIGN KEY (entered_by) REFERENCES users(id),
  CONSTRAINT fk_lab_results_verified_by FOREIGN KEY (verified_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 29 pharmacy_inventory
CREATE TABLE pharmacy_inventory (
  id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  clinic_id           BIGINT UNSIGNED NOT NULL,
  drug_id             BIGINT UNSIGNED NOT NULL,
  batch_number        VARCHAR(30) NOT NULL,
  quantity            INT NOT NULL DEFAULT 0,
  low_stock_threshold INT DEFAULT 10,
  expiry_date         DATE NOT NULL,
  purchase_price      DECIMAL(10,2),
  selling_price       DECIMAL(10,2),
  supplier            VARCHAR(100) NULL,
  location            VARCHAR(50) NULL,
  added_at            TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_drug_stock (clinic_id, drug_id, expiry_date),
  KEY idx_low_stock (clinic_id, quantity, low_stock_threshold),
  CONSTRAINT fk_pharmacy_clinic FOREIGN KEY (clinic_id) REFERENCES tenants(id),
  CONSTRAINT fk_pharmacy_drug FOREIGN KEY (drug_id) REFERENCES drugs(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 30 radiology_orders
CREATE TABLE radiology_orders (
  id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  clinic_id           BIGINT UNSIGNED NOT NULL,
  patient_id          BIGINT UNSIGNED NOT NULL,
  visit_id            BIGINT UNSIGNED NULL,
  ordered_by          BIGINT UNSIGNED NOT NULL,
  radiologist_id      BIGINT UNSIGNED NULL,
  modality            ENUM('xray','ct','mri','ultrasound','mammography','dexa','pet','other'),
  body_part           VARCHAR(80) NULL,
  clinical_indication TEXT NULL,
  report_text         LONGTEXT NULL,
  impression          TEXT NULL,
  image_paths         JSON NULL,
  dicom_study_uid     VARCHAR(64) NULL,
  share_token         VARCHAR(64) NULL,
  status              ENUM('ordered','in_progress','reported','verified','shared') DEFAULT 'ordered',
  ordered_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  reported_at         TIMESTAMP NULL,
  PRIMARY KEY (id),
  KEY idx_patient_radiology (clinic_id, patient_id, ordered_at),
  CONSTRAINT fk_rad_clinic FOREIGN KEY (clinic_id) REFERENCES tenants(id),
  CONSTRAINT fk_rad_patient FOREIGN KEY (patient_id) REFERENCES patients(id),
  CONSTRAINT fk_rad_visit FOREIGN KEY (visit_id) REFERENCES visits(id) ON DELETE SET NULL,
  CONSTRAINT fk_rad_ordered_by FOREIGN KEY (ordered_by) REFERENCES users(id),
  CONSTRAINT fk_rad_radiologist FOREIGN KEY (radiologist_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 31 consent_forms
CREATE TABLE consent_forms (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  clinic_id      BIGINT UNSIGNED NOT NULL,
  patient_id     BIGINT UNSIGNED NOT NULL,
  visit_id       BIGINT UNSIGNED NULL,
  form_type      ENUM('general','surgical','anaesthesia','procedure','research','photography'),
  form_version   VARCHAR(10) DEFAULT 'v1',
  form_content   LONGTEXT NULL,
  signed_by_name VARCHAR(100) NOT NULL,
  relationship   ENUM('self','parent','spouse','guardian','other') DEFAULT 'self',
  signature_path VARCHAR(255) NULL,
  witness_name   VARCHAR(100) NULL,
  content_hash   VARCHAR(64) NULL,
  ip_address     VARCHAR(45) NULL,
  pdf_path       VARCHAR(255) NULL,
  signed_at      TIMESTAMP NOT NULL,
  PRIMARY KEY (id),
  KEY idx_patient_consents (clinic_id, patient_id),
  CONSTRAINT fk_consent_clinic FOREIGN KEY (clinic_id) REFERENCES tenants(id),
  CONSTRAINT fk_consent_patient FOREIGN KEY (patient_id) REFERENCES patients(id),
  CONSTRAINT fk_consent_visit FOREIGN KEY (visit_id) REFERENCES visits(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 32 discharge_summaries
CREATE TABLE discharge_summaries (
  id                       BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  clinic_id                BIGINT UNSIGNED NOT NULL,
  patient_id               BIGINT UNSIGNED NOT NULL,
  visit_id                 BIGINT UNSIGNED NULL,
  final_diagnosis          TEXT NULL,
  icd10_codes              JSON NULL,
  procedures_done          TEXT NULL,
  treatment_summary        LONGTEXT NULL,
  condition_at_discharge   ENUM('improved','same','deteriorated','expired'),
  medications_at_discharge JSON NULL,
  follow_up_instructions   TEXT NULL,
  diet_at_discharge        TEXT NULL,
  doctor_signature_path    VARCHAR(255) NULL,
  status                   ENUM('draft','finalized') DEFAULT 'draft',
  finalized_at             TIMESTAMP NULL,
  created_at               TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_patient_ds (clinic_id, patient_id),
  CONSTRAINT fk_ds_clinic FOREIGN KEY (clinic_id) REFERENCES tenants(id),
  CONSTRAINT fk_ds_patient FOREIGN KEY (patient_id) REFERENCES patients(id),
  CONSTRAINT fk_ds_visit FOREIGN KEY (visit_id) REFERENCES visits(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 33 patient_photos
CREATE TABLE patient_photos (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  clinic_id       BIGINT UNSIGNED NOT NULL,
  patient_id      BIGINT UNSIGNED NOT NULL,
  visit_id        BIGINT UNSIGNED NULL,
  type            ENUM('before','after','progress'),
  photo_path      VARCHAR(255) NOT NULL,
  condition_label VARCHAR(100) NULL,
  is_public       TINYINT(1) DEFAULT 0,
  uploaded_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_patient_photos (clinic_id, patient_id, type),
  CONSTRAINT fk_photos_clinic FOREIGN KEY (clinic_id) REFERENCES tenants(id),
  CONSTRAINT fk_photos_patient FOREIGN KEY (patient_id) REFERENCES patients(id),
  CONSTRAINT fk_photos_visit FOREIGN KEY (visit_id) REFERENCES visits(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 34 notifications (created_at before keys)
CREATE TABLE notifications (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  clinic_id    BIGINT UNSIGNED NOT NULL,
  patient_id   BIGINT UNSIGNED NULL,
  channel      ENUM('whatsapp','sms','email','push') NOT NULL,
  template     VARCHAR(60) NOT NULL,
  to_number    VARCHAR(20) NULL,
  to_email     VARCHAR(150) NULL,
  payload      JSON NOT NULL,
  status       ENUM('queued','sent','failed','bounced') DEFAULT 'queued',
  attempts     TINYINT DEFAULT 0,
  error_log    TEXT NULL,
  scheduled_at TIMESTAMP NOT NULL,
  sent_at      TIMESTAMP NULL,
  created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_queue (status, scheduled_at),
  KEY idx_clinic_notifs (clinic_id, created_at),
  CONSTRAINT fk_notif_clinic FOREIGN KEY (clinic_id) REFERENCES tenants(id),
  CONSTRAINT fk_notif_patient FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 35 events
CREATE TABLE events (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  clinic_id    BIGINT UNSIGNED NULL,
  event_name   VARCHAR(80) NOT NULL,
  entity_type  VARCHAR(40) NULL,
  entity_id    BIGINT UNSIGNED NULL,
  payload      JSON NOT NULL,
  fired_by     BIGINT UNSIGNED NULL,
  processed_by JSON NULL,
  created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_event (event_name, created_at),
  KEY idx_clinic_events (clinic_id, created_at),
  CONSTRAINT fk_events_clinic FOREIGN KEY (clinic_id) REFERENCES tenants(id) ON DELETE SET NULL,
  CONSTRAINT fk_events_fired_by FOREIGN KEY (fired_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 36 staff_attendance
CREATE TABLE staff_attendance (
  id        BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  clinic_id BIGINT UNSIGNED NOT NULL,
  user_id   BIGINT UNSIGNED NOT NULL,
  date      DATE NOT NULL,
  clock_in  TIME NULL,
  clock_out TIME NULL,
  status    ENUM('present','absent','half_day','leave','holiday') DEFAULT 'present',
  notes     VARCHAR(200) NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_att (clinic_id, user_id, date),
  CONSTRAINT fk_att_clinic FOREIGN KEY (clinic_id) REFERENCES tenants(id),
  CONSTRAINT fk_att_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 37 staff_leaves
CREATE TABLE staff_leaves (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  clinic_id    BIGINT UNSIGNED NOT NULL,
  user_id      BIGINT UNSIGNED NOT NULL,
  leave_type   ENUM('CL','SL','EL','LWP','other') DEFAULT 'CL',
  from_date    DATE NOT NULL,
  to_date      DATE NOT NULL,
  days         TINYINT UNSIGNED GENERATED ALWAYS AS (DATEDIFF(to_date, from_date) + 1) STORED,
  reason       TEXT NULL,
  status       ENUM('pending','approved','rejected') DEFAULT 'pending',
  approved_by  BIGINT UNSIGNED NULL,
  created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_user_leave (clinic_id, user_id, from_date),
  CONSTRAINT fk_leave_clinic FOREIGN KEY (clinic_id) REFERENCES tenants(id),
  CONSTRAINT fk_leave_user FOREIGN KEY (user_id) REFERENCES users(id),
  CONSTRAINT fk_leave_approved_by FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 38 crm_leads
CREATE TABLE crm_leads (
  id                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  clinic_id            BIGINT UNSIGNED NOT NULL,
  name                 VARCHAR(100) NOT NULL,
  phone                VARCHAR(20) NULL,
  email                VARCHAR(150) NULL,
  inquiry_about        TEXT NULL,
  source               ENUM('website','google_ads','instagram','facebook','walk_in','referral','whatsapp','ivr','other') DEFAULT 'walk_in',
  referred_by_doctor   BIGINT UNSIGNED NULL,
  assigned_to          BIGINT UNSIGNED NULL,
  status               ENUM('new','contacted','follow_up','converted','lost') DEFAULT 'new',
  converted_patient_id BIGINT UNSIGNED NULL,
  follow_up_date       DATE NULL,
  notes                TEXT NULL,
  created_at           TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_clinic_crm (clinic_id, status, follow_up_date),
  CONSTRAINT fk_crm_clinic FOREIGN KEY (clinic_id) REFERENCES tenants(id),
  CONSTRAINT fk_crm_ref_doc FOREIGN KEY (referred_by_doctor) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_crm_assigned FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_crm_patient FOREIGN KEY (converted_patient_id) REFERENCES patients(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 39 expenses
CREATE TABLE expenses (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  clinic_id    BIGINT UNSIGNED NOT NULL,
  category     ENUM('rent','utilities','salaries','consumables','equipment','marketing','maintenance','other'),
  description  VARCHAR(200) NOT NULL,
  amount       DECIMAL(12,2) NOT NULL,
  currency     CHAR(3),
  expense_date DATE NOT NULL,
  paid_via     ENUM('cash','bank','card','upi'),
  receipt_path VARCHAR(255) NULL,
  entered_by   BIGINT UNSIGNED NOT NULL,
  created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_clinic_expenses (clinic_id, expense_date),
  CONSTRAINT fk_exp_clinic FOREIGN KEY (clinic_id) REFERENCES tenants(id),
  CONSTRAINT fk_exp_entered_by FOREIGN KEY (entered_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 40 doctor_incentives
CREATE TABLE doctor_incentives (
  id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  clinic_id         BIGINT UNSIGNED NOT NULL,
  doctor_id         BIGINT UNSIGNED NOT NULL,
  period_month      CHAR(7) NOT NULL,
  revenue_generated DECIMAL(12,2) DEFAULT 0.00,
  incentive_percent DECIMAL(5,2) DEFAULT 0.00,
  flat_fee          DECIMAL(10,2) DEFAULT 0.00,
  gross_incentive   DECIMAL(12,2) GENERATED ALWAYS AS ((revenue_generated * incentive_percent / 100) + flat_fee) STORED,
  tds_amount        DECIMAL(10,2) DEFAULT 0.00,
  net_payable       DECIMAL(12,2) NULL,
  payment_status    ENUM('pending','paid','hold') DEFAULT 'pending',
  paid_at           TIMESTAMP NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_incentive (clinic_id, doctor_id, period_month),
  CONSTRAINT fk_incentives_clinic FOREIGN KEY (clinic_id) REFERENCES tenants(id),
  CONSTRAINT fk_incentives_doctor FOREIGN KEY (doctor_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 41 diet_plans
CREATE TABLE diet_plans (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  clinic_id       BIGINT UNSIGNED NOT NULL,
  patient_id      BIGINT UNSIGNED NOT NULL,
  visit_id        BIGINT UNSIGNED NULL,
  prescribed_by   BIGINT UNSIGNED NOT NULL,
  `condition`     VARCHAR(80) NULL,
  plan_json       JSON NOT NULL,
  antidotes_shown TEXT NULL,
  veg_type        ENUM('veg','nonveg','vegan','eggetarian'),
  status          ENUM('draft','shared') DEFAULT 'draft',
  shared_at       TIMESTAMP NULL,
  created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_patient_diet (clinic_id, patient_id),
  CONSTRAINT fk_diet_clinic FOREIGN KEY (clinic_id) REFERENCES tenants(id),
  CONSTRAINT fk_diet_patient FOREIGN KEY (patient_id) REFERENCES patients(id),
  CONSTRAINT fk_diet_visit FOREIGN KEY (visit_id) REFERENCES visits(id) ON DELETE SET NULL,
  CONSTRAINT fk_diet_prescriber FOREIGN KEY (prescribed_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 42 audit_log (no partitioning)
CREATE TABLE audit_log (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  clinic_id  BIGINT UNSIGNED NULL,
  user_id    BIGINT UNSIGNED NULL,
  table_name VARCHAR(60) NOT NULL,
  record_id  BIGINT UNSIGNED NULL,
  action     ENUM('INSERT','UPDATE','DELETE','LOGIN','LOGOUT') NOT NULL,
  old_values JSON NULL,
  new_values JSON NULL,
  ip_address VARCHAR(45) NULL,
  user_agent VARCHAR(255) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_clinic_audit (clinic_id, created_at),
  KEY idx_table_record (table_name, record_id),
  CONSTRAINT fk_audit_clinic FOREIGN KEY (clinic_id) REFERENCES tenants(id) ON DELETE SET NULL,
  CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 43 waiting_list
CREATE TABLE waiting_list (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  clinic_id      BIGINT UNSIGNED NOT NULL,
  patient_id     BIGINT UNSIGNED NOT NULL,
  doctor_id      BIGINT UNSIGNED NOT NULL,
  preferred_date DATE NOT NULL,
  notified       TINYINT(1) DEFAULT 0,
  created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_doctor_wait (clinic_id, doctor_id, preferred_date),
  CONSTRAINT fk_wait_clinic FOREIGN KEY (clinic_id) REFERENCES tenants(id),
  CONSTRAINT fk_wait_patient FOREIGN KEY (patient_id) REFERENCES patients(id),
  CONSTRAINT fk_wait_doctor FOREIGN KEY (doctor_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 44 otp_tokens
CREATE TABLE otp_tokens (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  phone      VARCHAR(20) NOT NULL,
  otp_hash   VARCHAR(64) NOT NULL,
  purpose    ENUM('portal_login','password_reset','verification'),
  expires_at TIMESTAMP NOT NULL,
  used_at    TIMESTAMP NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_otp_phone (phone, expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 45 api_keys
CREATE TABLE api_keys (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  clinic_id  BIGINT UNSIGNED NOT NULL,
  name       VARCHAR(80) NOT NULL,
  key_hash   VARCHAR(64) NOT NULL,
  scopes     JSON NULL,
  last_used  TIMESTAMP NULL,
  expires_at DATE NULL,
  is_active  TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT fk_api_keys_clinic FOREIGN KEY (clinic_id) REFERENCES tenants(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 46 analytics_snapshots
CREATE TABLE analytics_snapshots (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  clinic_id    BIGINT UNSIGNED NOT NULL,
  date         DATE NOT NULL,
  metric_key   VARCHAR(80) NOT NULL,
  metric_value DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
  PRIMARY KEY (id),
  UNIQUE KEY uq_clinic_date_metric (clinic_id, date, metric_key),
  KEY idx_clinic_date (clinic_id, date),
  CONSTRAINT fk_analytics_clinic FOREIGN KEY (clinic_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
