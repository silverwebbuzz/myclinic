-- =============================================================
-- specialty_master: add url_slug + align labels to patient-friendly style.
-- =============================================================
-- Run AFTER specialty_master.sql was already imported (the original version
-- had no url_slug column). MariaDB-native + idempotent.
--
-- This makes specialty_master the single source for the homepage,
-- find-a-doctor, and sitemap — so the same specialty name shows everywhere.
-- =============================================================

ALTER TABLE `specialty_master`
    ADD COLUMN IF NOT EXISTS `url_slug` VARCHAR(50) NULL AFTER `slug`;

-- Backfill url_slug + canonical patient-friendly label per db slug.
UPDATE `specialty_master` SET url_slug='general-physician',   label='General physician',          plural_label='General physicians'        WHERE slug='gp';
UPDATE `specialty_master` SET url_slug='homeopathy',          label='Homeopathy doctor',          plural_label='Homeopathy doctors'        WHERE slug='homeopathy';
UPDATE `specialty_master` SET url_slug='dentist',             label='Dentist',                    plural_label='Dentists'                  WHERE slug='dental';
UPDATE `specialty_master` SET url_slug='dermatologist',       label='Dermatologist',              plural_label='Dermatologists'            WHERE slug='derma';
UPDATE `specialty_master` SET url_slug='pediatrician',        label='Pediatrician',               plural_label='Pediatricians'             WHERE slug='peds';
UPDATE `specialty_master` SET url_slug='physiotherapist',     label='Physiotherapist',            plural_label='Physiotherapists'          WHERE slug='physio';
UPDATE `specialty_master` SET url_slug='family-medicine',     label='Family medicine doctor',     plural_label='Family medicine doctors'   WHERE slug='family_medicine';
UPDATE `specialty_master` SET url_slug='cardiologist',        label='Cardiologist',               plural_label='Cardiologists'             WHERE slug='cardio';
UPDATE `specialty_master` SET url_slug='neurologist',         label='Neurologist',                plural_label='Neurologists'              WHERE slug='neuro';
UPDATE `specialty_master` SET url_slug='pulmonologist',       label='Pulmonologist',              plural_label='Pulmonologists'            WHERE slug='pulmonology';
UPDATE `specialty_master` SET url_slug='gastroenterologist',  label='Gastroenterologist',         plural_label='Gastroenterologists'       WHERE slug='gastro';
UPDATE `specialty_master` SET url_slug='hepatologist',        label='Hepatologist',               plural_label='Hepatologists'             WHERE slug='hepatology';
UPDATE `specialty_master` SET url_slug='endocrinologist',     label='Endocrinologist',            plural_label='Endocrinologists'          WHERE slug='endocrinology';
UPDATE `specialty_master` SET url_slug='diabetologist',       label='Diabetologist',              plural_label='Diabetologists'            WHERE slug='diabetology';
UPDATE `specialty_master` SET url_slug='nephrologist',        label='Nephrologist',               plural_label='Nephrologists'             WHERE slug='nephrology';
UPDATE `specialty_master` SET url_slug='oncologist',          label='Oncologist',                 plural_label='Oncologists'               WHERE slug='oncology';
UPDATE `specialty_master` SET url_slug='hematologist',        label='Hematologist',               plural_label='Hematologists'             WHERE slug='hematology';
UPDATE `specialty_master` SET url_slug='urologist',           label='Urologist',                  plural_label='Urologists'                WHERE slug='urologist';
UPDATE `specialty_master` SET url_slug='andrologist',         label='Andrologist',                plural_label='Andrologists'              WHERE slug='andrology';
UPDATE `specialty_master` SET url_slug='rheumatologist',      label='Rheumatologist',             plural_label='Rheumatologists'           WHERE slug='rheumatology';
UPDATE `specialty_master` SET url_slug='allergist',           label='Allergist',                  plural_label='Allergists'                WHERE slug='allergy';
UPDATE `specialty_master` SET url_slug='pain-management',     label='Pain management specialist', plural_label='Pain management specialists' WHERE slug='pain_management';
UPDATE `specialty_master` SET url_slug='trichologist',        label='Trichologist',               plural_label='Trichologists'             WHERE slug='trichology';
UPDATE `specialty_master` SET url_slug='cosmetologist',       label='Cosmetologist',              plural_label='Cosmetologists'            WHERE slug='cosmetology';
UPDATE `specialty_master` SET url_slug='sexologist',          label='Sexologist',                 plural_label='Sexologists'               WHERE slug='sexology';
UPDATE `specialty_master` SET url_slug='general-surgeon',     label='General surgeon',            plural_label='General surgeons'          WHERE slug='general_surgery';
UPDATE `specialty_master` SET url_slug='neurosurgeon',        label='Neurosurgeon',               plural_label='Neurosurgeons'             WHERE slug='neurosurgery';
UPDATE `specialty_master` SET url_slug='orthopedic',          label='Orthopedic doctor',          plural_label='Orthopedic doctors'        WHERE slug='ortho';
UPDATE `specialty_master` SET url_slug='spine-surgeon',       label='Spine surgeon',              plural_label='Spine surgeons'            WHERE slug='spine';
UPDATE `specialty_master` SET url_slug='plastic-surgeon',     label='Plastic surgeon',            plural_label='Plastic surgeons'          WHERE slug='plastic_surgery';
UPDATE `specialty_master` SET url_slug='bariatric-surgeon',   label='Bariatric surgeon',          plural_label='Bariatric surgeons'        WHERE slug='bariatric';
UPDATE `specialty_master` SET url_slug='vascular-surgeon',    label='Vascular surgeon',           plural_label='Vascular surgeons'         WHERE slug='vascular';
UPDATE `specialty_master` SET url_slug='gi-surgeon',          label='GI surgeon',                 plural_label='GI surgeons'               WHERE slug='gi_surgery';
UPDATE `specialty_master` SET url_slug='sports-medicine',     label='Sports medicine doctor',     plural_label='Sports medicine doctors'   WHERE slug='sports_medicine';
UPDATE `specialty_master` SET url_slug='critical-care',       label='Critical care specialist',   plural_label='Critical care specialists' WHERE slug='critical_care';
UPDATE `specialty_master` SET url_slug='radiologist',         label='Radiologist',                plural_label='Radiologists'              WHERE slug='radiology';
UPDATE `specialty_master` SET url_slug='prosthodontist',      label='Prosthodontist',             plural_label='Prosthodontists'           WHERE slug='prosthodontist';
UPDATE `specialty_master` SET url_slug='orthodontist',        label='Orthodontist',               plural_label='Orthodontists'             WHERE slug='orthodontist';
UPDATE `specialty_master` SET url_slug='pediatric-dentist',   label='Pediatric dentist',          plural_label='Pediatric dentists'        WHERE slug='pediatric_dentist';
UPDATE `specialty_master` SET url_slug='endodontist',         label='Endodontist',                plural_label='Endodontists'              WHERE slug='endodontist';
UPDATE `specialty_master` SET url_slug='implantologist',      label='Dental implant specialist',  plural_label='Dental implant specialists' WHERE slug='implantologist';
UPDATE `specialty_master` SET url_slug='gynecologist',        label='Gynecologist',               plural_label='Gynecologists'             WHERE slug='gyno';
UPDATE `specialty_master` SET url_slug='fertility-specialist',label='Fertility specialist',       plural_label='Fertility specialists'     WHERE slug='fertility';
UPDATE `specialty_master` SET url_slug='ophthalmologist',     label='Ophthalmologist',            plural_label='Ophthalmologists'          WHERE slug='eye';
UPDATE `specialty_master` SET url_slug='ent-specialist',      label='ENT specialist',             plural_label='ENT specialists'           WHERE slug='ent';
UPDATE `specialty_master` SET url_slug='ayurveda',            label='Ayurveda doctor',            plural_label='Ayurveda doctors'          WHERE slug='ayurveda';
UPDATE `specialty_master` SET url_slug='siddha',              label='Siddha doctor',              plural_label='Siddha doctors'            WHERE slug='siddha';
UPDATE `specialty_master` SET url_slug='unani',               label='Unani doctor',               plural_label='Unani doctors'             WHERE slug='unani';
UPDATE `specialty_master` SET url_slug='naturopathy',         label='Naturopathy doctor',         plural_label='Naturopathy doctors'       WHERE slug='naturopathy';
UPDATE `specialty_master` SET url_slug='acupuncturist',       label='Acupuncturist',              plural_label='Acupuncturists'            WHERE slug='acupuncturist';
UPDATE `specialty_master` SET url_slug='psychiatrist',        label='Psychiatrist',               plural_label='Psychiatrists'             WHERE slug='psychiatrist';
UPDATE `specialty_master` SET url_slug='psychologist',        label='Psychologist',               plural_label='Psychologists'             WHERE slug='psychologist';
UPDATE `specialty_master` SET url_slug='audiologist',         label='Audiologist',                plural_label='Audiologists'              WHERE slug='audiologist';
UPDATE `specialty_master` SET url_slug='speech-therapist',    label='Speech therapist',           plural_label='Speech therapists'         WHERE slug='speech';
UPDATE `specialty_master` SET url_slug='dietitian',           label='Dietitian',                  plural_label='Dietitians'                WHERE slug='dietitian';

-- Enforce uniqueness once backfilled (safe to re-run).
ALTER TABLE `specialty_master`
    ADD UNIQUE KEY IF NOT EXISTS `uq_specialty_url_slug` (`url_slug`);
