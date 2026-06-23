-- 039_patient_family.sql
-- Family profiles for the patient panel (/patient → Family tab).
--
-- A logged-in account holder (patient_identities row) keeps a set of family
-- members. Members are ISOLATED, PRIVATE DATA owned by that one account —
-- NOT login accounts. They live entirely in patient_family_members, so
-- patient_identities stays pure (unique, phone-verified login accounts only)
-- and is never polluted with dependents.
--
-- Consequences of the isolated model (all intended):
--   * A member's phone is plain contact text, not a link to any login.
--   * If that person later registers their own login, they get a fresh empty
--     account and never see data someone else typed about them.
--   * The same real person may exist as several unrelated member rows across
--     different accounts. That's fine.
--   * No auto-link to clinic `patients` charts.
--
-- We deliberately do NOT store Aadhaar (DPDP / UIDAI risk) — ABHA + insurance
-- policy numbers only, all optional.
--
-- Three tables:
--   patient_family_members      relation + the member's data, inline
--   patient_insurance_policies  health / top-up policies per member
--   patient_owned_documents     private documents per member
--
-- Safe to re-run: CREATE IF NOT EXISTS only.
-- phpMyAdmin: select your app database in the left sidebar first.

CREATE TABLE IF NOT EXISTS patient_family_members (
    id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    owner_identity_id BIGINT UNSIGNED NOT NULL,     -- the account this member belongs to
    relation          ENUM('self','spouse','mother','father','son','daughter','guardian','other')
                        NOT NULL DEFAULT 'other',
    name              VARCHAR(120) NOT NULL,
    first_name        VARCHAR(60)  NULL,
    last_name         VARCHAR(60)  NULL,
    dob               DATE         NULL,
    gender            ENUM('M','F','Other') NULL,
    blood_group       ENUM('A+','A-','B+','B-','O+','O-','AB+','AB-') NULL,
    phone             VARCHAR(20)  NULL,             -- plain contact field, NOT a login
    email             VARCHAR(160) NULL,
    allergies         TEXT NULL,
    chronic_conditions TEXT NULL,
    emergency_contact_name     VARCHAR(120) NULL,
    emergency_contact_phone    VARCHAR(20)  NULL,
    emergency_contact_relation VARCHAR(40)  NULL,
    abha_id           VARCHAR(20)  NULL,
    photo_path        VARCHAR(255) NULL,
    is_minor          TINYINT(1) NOT NULL DEFAULT 0,
    is_self           TINYINT(1) NOT NULL DEFAULT 0, -- the owner's own "self" row
    sort_order        SMALLINT   NOT NULL DEFAULT 100,
    is_active         TINYINT(1) NOT NULL DEFAULT 1,
    created_at        TIMESTAMP  NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        TIMESTAMP  NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_owner (owner_identity_id, is_active, sort_order),
    CONSTRAINT fk_fam_owner FOREIGN KEY (owner_identity_id)
        REFERENCES patient_identities (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS patient_insurance_policies (
    id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    family_member_id BIGINT UNSIGNED NOT NULL,       -- whose policy
    insurer_name     VARCHAR(120)  NULL,
    policy_type      ENUM('health','topup','personal_accident','critical_illness','other')
                       NOT NULL DEFAULT 'health',
    policy_number    VARCHAR(80)   NULL,
    sum_insured_inr  DECIMAL(12,2) NULL,
    valid_till       DATE          NULL,
    document_id      BIGINT UNSIGNED NULL,            -- optional scan in patient_owned_documents
    created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_member (family_member_id),
    CONSTRAINT fk_pol_member FOREIGN KEY (family_member_id)
        REFERENCES patient_family_members (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS patient_owned_documents (
    id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    family_member_id  BIGINT UNSIGNED NOT NULL,       -- whose document
    owner_identity_id BIGINT UNSIGNED NOT NULL,       -- account that uploaded it
    doc_type          ENUM('abha','insurance_card','id_photo','prescription',
                           'lab_report','vaccine_cert','other') NOT NULL DEFAULT 'other',
    title             VARCHAR(150) NULL,
    file_path         VARCHAR(255) NOT NULL,          -- relative, private (storage/patient_docs/…)
    mime_type         VARCHAR(80)  NULL,
    size_bytes        INT UNSIGNED NULL,
    next_due_on       DATE         NULL,              -- e.g. next vaccine due
    created_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_member_type (family_member_id, doc_type),
    CONSTRAINT fk_doc_member FOREIGN KEY (family_member_id)
        REFERENCES patient_family_members (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
