-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: May 25, 2026 at 02:38 AM
-- Server version: 10.9.8-MariaDB
-- PHP Version: 7.4.33

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `silverwebbuzz_in_myclinic`
--

-- --------------------------------------------------------

--
-- Table structure for table `analytics_snapshots`
--

CREATE TABLE `analytics_snapshots` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `clinic_id` bigint(20) UNSIGNED NOT NULL,
  `date` date NOT NULL,
  `metric_key` varchar(80) NOT NULL,
  `metric_value` decimal(18,4) NOT NULL DEFAULT 0.0000
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `api_keys`
--

CREATE TABLE `api_keys` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `clinic_id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(80) NOT NULL,
  `key_prefix` varchar(20) DEFAULT NULL,
  `key_hash` varchar(64) NOT NULL,
  `scopes` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`scopes`)),
  `last_used` timestamp NULL DEFAULT NULL,
  `expires_at` date DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `appointments`
--

CREATE TABLE `appointments` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `clinic_id` bigint(20) UNSIGNED NOT NULL,
  `patient_id` bigint(20) UNSIGNED NOT NULL,
  `doctor_id` bigint(20) UNSIGNED NOT NULL,
  `scheduled_at` datetime NOT NULL,
  `slot_duration` tinyint(3) UNSIGNED DEFAULT 15,
  `type` enum('walkin','prebooked','online','followup') DEFAULT 'prebooked',
  `source` enum('reception','website','app','whatsapp','phone','google') DEFAULT 'reception',
  `status` enum('scheduled','confirmed','in_progress','completed','cancelled','no_show') DEFAULT 'scheduled',
  `chief_complaint` text DEFAULT NULL,
  `token_number` smallint(5) UNSIGNED DEFAULT NULL,
  `is_followup` tinyint(1) DEFAULT 0,
  `parent_visit_id` bigint(20) UNSIGNED DEFAULT NULL,
  `reminder_sent` tinyint(1) DEFAULT 0,
  `notes` text DEFAULT NULL,
  `meet_link` varchar(255) DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `audit_log`
--

CREATE TABLE `audit_log` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `clinic_id` bigint(20) UNSIGNED DEFAULT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `table_name` varchar(60) NOT NULL,
  `record_id` bigint(20) UNSIGNED DEFAULT NULL,
  `action` enum('INSERT','UPDATE','DELETE','LOGIN','LOGOUT') NOT NULL,
  `old_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`old_values`)),
  `new_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_values`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `clinic_modules`
--

CREATE TABLE `clinic_modules` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `clinic_id` bigint(20) UNSIGNED NOT NULL,
  `module_id` varchar(40) NOT NULL,
  `activated_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expires_at` date DEFAULT NULL,
  `billing_cycle` enum('monthly','yearly','lifetime','free') DEFAULT 'monthly',
  `stripe_sub_item_id` varchar(60) DEFAULT NULL,
  `razorpay_sub_id` varchar(60) DEFAULT NULL,
  `is_trial` tinyint(1) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `consent_forms`
--

CREATE TABLE `consent_forms` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `clinic_id` bigint(20) UNSIGNED NOT NULL,
  `patient_id` bigint(20) UNSIGNED NOT NULL,
  `visit_id` bigint(20) UNSIGNED DEFAULT NULL,
  `form_type` enum('general','surgical','anaesthesia','procedure','research','photography') DEFAULT NULL,
  `form_version` varchar(10) DEFAULT 'v1',
  `form_content` longtext DEFAULT NULL,
  `signed_by_name` varchar(100) NOT NULL,
  `relationship` enum('self','parent','spouse','guardian','other') DEFAULT 'self',
  `signature_path` varchar(255) DEFAULT NULL,
  `witness_name` varchar(100) DEFAULT NULL,
  `content_hash` varchar(64) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `pdf_path` varchar(255) DEFAULT NULL,
  `signed_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `consent_templates`
--

CREATE TABLE `consent_templates` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `clinic_id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(120) NOT NULL,
  `form_type` enum('general','surgical','anaesthesia','procedure','research','photography') DEFAULT 'procedure',
  `content` longtext NOT NULL,
  `merge_fields` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`merge_fields`)),
  `is_active` tinyint(1) DEFAULT 1,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `crm_leads`
--

CREATE TABLE `crm_leads` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `clinic_id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `inquiry_about` text DEFAULT NULL,
  `source` enum('website','google_ads','instagram','facebook','walk_in','referral','whatsapp','ivr','other') DEFAULT 'walk_in',
  `referred_by_doctor` bigint(20) UNSIGNED DEFAULT NULL,
  `assigned_to` bigint(20) UNSIGNED DEFAULT NULL,
  `status` enum('new','contacted','follow_up','converted','lost') DEFAULT 'new',
  `converted_patient_id` bigint(20) UNSIGNED DEFAULT NULL,
  `follow_up_date` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `diet_plans`
--

CREATE TABLE `diet_plans` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `clinic_id` bigint(20) UNSIGNED NOT NULL,
  `patient_id` bigint(20) UNSIGNED NOT NULL,
  `visit_id` bigint(20) UNSIGNED DEFAULT NULL,
  `prescribed_by` bigint(20) UNSIGNED NOT NULL,
  `condition` varchar(80) DEFAULT NULL,
  `plan_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`plan_json`)),
  `pdf_path` varchar(255) DEFAULT NULL,
  `antidotes_shown` text DEFAULT NULL,
  `veg_type` enum('veg','nonveg','vegan','eggetarian') DEFAULT NULL,
  `status` enum('draft','shared') DEFAULT 'draft',
  `shared_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `directory_cities`
--

CREATE TABLE `directory_cities` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(80) NOT NULL,
  `slug` varchar(80) NOT NULL,
  `state` varchar(80) DEFAULT NULL,
  `country_code` char(2) NOT NULL,
  `lat` decimal(10,8) DEFAULT NULL,
  `lng` decimal(11,8) DEFAULT NULL,
  `doctor_count` int(10) UNSIGNED DEFAULT 0,
  `is_featured` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `directory_doctors`
--

CREATE TABLE `directory_doctors` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `place_id` varchar(180) NOT NULL,
  `source` enum('google','self','manual') NOT NULL DEFAULT 'google',
  `is_claimed` tinyint(1) NOT NULL DEFAULT 0,
  `claimed_tenant_id` bigint(20) UNSIGNED DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `specialty` varchar(40) DEFAULT NULL,
  `country` char(2) NOT NULL DEFAULT 'IN',
  `state` varchar(80) DEFAULT NULL,
  `city` varchar(80) NOT NULL,
  `area` varchar(120) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `lat` decimal(10,7) DEFAULT NULL,
  `lng` decimal(10,7) DEFAULT NULL,
  `plus_code` varchar(40) DEFAULT NULL,
  `phone` varchar(40) DEFAULT NULL,
  `intl_phone` varchar(40) DEFAULT NULL,
  `website` varchar(255) DEFAULT NULL,
  `gmaps_url` varchar(255) DEFAULT NULL,
  `status` enum('OPERATIONAL','CLOSED_TEMPORARILY','CLOSED_PERMANENTLY') NOT NULL DEFAULT 'OPERATIONAL',
  `rating` decimal(3,2) DEFAULT NULL,
  `reviews` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `price_level` tinyint(4) DEFAULT NULL,
  `last_review_at` timestamp NULL DEFAULT NULL,
  `types` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`types`)),
  `opening_hours` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`opening_hours`)),
  `photo_reference` varchar(500) DEFAULT NULL,
  `consultation_fee` decimal(10,2) DEFAULT NULL,
  `consultation_fee_currency` char(3) DEFAULT NULL,
  `doctor_name` varchar(160) DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `languages` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`languages`)),
  `quality_score` smallint(6) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `dropped_reason` varchar(80) DEFAULT NULL,
  `fetched_at` timestamp NULL DEFAULT NULL,
  `refreshed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `discharge_summaries`
--

CREATE TABLE `discharge_summaries` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `clinic_id` bigint(20) UNSIGNED NOT NULL,
  `patient_id` bigint(20) UNSIGNED NOT NULL,
  `visit_id` bigint(20) UNSIGNED DEFAULT NULL,
  `final_diagnosis` text DEFAULT NULL,
  `icd10_codes` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`icd10_codes`)),
  `procedures_done` text DEFAULT NULL,
  `treatment_summary` longtext DEFAULT NULL,
  `condition_at_discharge` enum('improved','same','deteriorated','expired') DEFAULT NULL,
  `medications_at_discharge` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`medications_at_discharge`)),
  `follow_up_instructions` text DEFAULT NULL,
  `diet_at_discharge` text DEFAULT NULL,
  `doctor_signature_path` varchar(255) DEFAULT NULL,
  `pdf_path` varchar(255) DEFAULT NULL,
  `share_token` varchar(64) DEFAULT NULL,
  `status` enum('draft','finalized') DEFAULT 'draft',
  `finalized_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `doctor_incentives`
--

CREATE TABLE `doctor_incentives` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `clinic_id` bigint(20) UNSIGNED NOT NULL,
  `doctor_id` bigint(20) UNSIGNED NOT NULL,
  `period_month` char(7) NOT NULL,
  `revenue_generated` decimal(12,2) DEFAULT 0.00,
  `incentive_percent` decimal(5,2) DEFAULT 0.00,
  `flat_fee` decimal(10,2) DEFAULT 0.00,
  `gross_incentive` decimal(12,2) GENERATED ALWAYS AS (`revenue_generated` * `incentive_percent` / 100 + `flat_fee`) STORED,
  `tds_amount` decimal(10,2) DEFAULT 0.00,
  `net_payable` decimal(12,2) DEFAULT NULL,
  `payment_status` enum('pending','paid','hold') DEFAULT 'pending',
  `paid_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `doctor_leaves`
--

CREATE TABLE `doctor_leaves` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `clinic_id` bigint(20) UNSIGNED NOT NULL,
  `doctor_id` bigint(20) UNSIGNED NOT NULL,
  `leave_date` date NOT NULL,
  `session` enum('full','morning','evening') DEFAULT 'full',
  `reason` varchar(200) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `doctor_locations`
--

CREATE TABLE `doctor_locations` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `doctor_id` bigint(20) UNSIGNED NOT NULL,
  `clinic_name` varchar(150) DEFAULT NULL,
  `address` varchar(250) DEFAULT NULL,
  `city` varchar(80) NOT NULL,
  `state` varchar(80) DEFAULT NULL,
  `country_code` char(2) NOT NULL DEFAULT 'IN',
  `phone` varchar(20) DEFAULT NULL,
  `lat` decimal(10,8) DEFAULT NULL,
  `lng` decimal(11,8) DEFAULT NULL,
  `timing_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`timing_json`)),
  `is_primary` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `doctor_profiles`
--

CREATE TABLE `doctor_profiles` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `clinic_id` bigint(20) UNSIGNED DEFAULT NULL,
  `slug` varchar(100) NOT NULL,
  `full_name` varchar(150) NOT NULL,
  `specialty_primary` varchar(80) NOT NULL,
  `specialties` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`specialties`)),
  `degrees` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`degrees`)),
  `experience_years` tinyint(3) UNSIGNED DEFAULT NULL,
  `languages` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`languages`)),
  `bio` text DEFAULT NULL,
  `photo_path` varchar(255) DEFAULT NULL,
  `consultation_fee` decimal(10,2) DEFAULT NULL,
  `currency` char(3) DEFAULT 'INR',
  `avg_rating` decimal(3,2) DEFAULT 0.00,
  `total_reviews` int(10) UNSIGNED DEFAULT 0,
  `is_verified` tinyint(1) DEFAULT 0,
  `is_featured` tinyint(1) DEFAULT 0,
  `featured_until` date DEFAULT NULL,
  `is_public` tinyint(1) DEFAULT 1,
  `profile_views` int(10) UNSIGNED DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `doctor_reviews`
--

CREATE TABLE `doctor_reviews` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `doctor_id` bigint(20) UNSIGNED NOT NULL,
  `reviewer_name` varchar(80) NOT NULL,
  `reviewer_phone_hash` varchar(64) DEFAULT NULL,
  `rating` tinyint(4) NOT NULL,
  `title` varchar(100) DEFAULT NULL,
  `body` text DEFAULT NULL,
  `condition_treated` varchar(80) DEFAULT NULL,
  `is_verified_patient` tinyint(1) DEFAULT 0,
  `is_approved` tinyint(1) DEFAULT 0,
  `helpful_count` int(10) UNSIGNED DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `doctor_schedules`
--

CREATE TABLE `doctor_schedules` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `clinic_id` bigint(20) UNSIGNED NOT NULL,
  `doctor_id` bigint(20) UNSIGNED NOT NULL,
  `day_of_week` tinyint(4) NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `extended_end_time` time DEFAULT NULL,
  `slot_duration` tinyint(3) UNSIGNED DEFAULT 15,
  `max_patients` smallint(5) UNSIGNED DEFAULT 30,
  `session_name` varchar(40) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `drugs`
--

CREATE TABLE `drugs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(150) NOT NULL,
  `generic_name` varchar(150) DEFAULT NULL,
  `drug_class` varchar(80) DEFAULT NULL,
  `strength` varchar(30) DEFAULT NULL,
  `form` enum('tablet','capsule','syrup','injection','cream','drops','inhaler','patch','other') DEFAULT NULL,
  `contraindications` text DEFAULT NULL,
  `interactions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`interactions`)),
  `schedule` enum('H','H1','X','G','OTC') DEFAULT 'OTC',
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `events`
--

CREATE TABLE `events` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `clinic_id` bigint(20) UNSIGNED DEFAULT NULL,
  `event_name` varchar(80) NOT NULL,
  `entity_type` varchar(40) DEFAULT NULL,
  `entity_id` bigint(20) UNSIGNED DEFAULT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`payload`)),
  `fired_by` bigint(20) UNSIGNED DEFAULT NULL,
  `processed_by` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`processed_by`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `expenses`
--

CREATE TABLE `expenses` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `clinic_id` bigint(20) UNSIGNED NOT NULL,
  `category` enum('rent','utilities','salaries','consumables','equipment','marketing','maintenance','other') DEFAULT NULL,
  `description` varchar(200) NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `currency` char(3) DEFAULT NULL,
  `expense_date` date NOT NULL,
  `paid_via` enum('cash','bank','card','upi') DEFAULT NULL,
  `receipt_path` varchar(255) DEFAULT NULL,
  `entered_by` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `impersonation_tokens`
--

CREATE TABLE `impersonation_tokens` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `admin_id` bigint(20) UNSIGNED NOT NULL,
  `clinic_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `token_hash` varchar(64) NOT NULL,
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `used_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `invoices`
--

CREATE TABLE `invoices` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `clinic_id` bigint(20) UNSIGNED NOT NULL,
  `patient_id` bigint(20) UNSIGNED NOT NULL,
  `visit_id` bigint(20) UNSIGNED DEFAULT NULL,
  `attributed_doctor_id` bigint(20) UNSIGNED DEFAULT NULL,
  `invoice_number` varchar(20) NOT NULL,
  `currency` char(3) NOT NULL,
  `subtotal` decimal(12,2) NOT NULL DEFAULT 0.00,
  `discount_amount` decimal(12,2) DEFAULT 0.00,
  `tax_label` varchar(10) DEFAULT 'GST',
  `tax_percent` decimal(5,2) DEFAULT 0.00,
  `tax_amount` decimal(12,2) DEFAULT 0.00,
  `total` decimal(12,2) NOT NULL,
  `advance_paid` decimal(12,2) DEFAULT 0.00,
  `balance_due` decimal(12,2) GENERATED ALWAYS AS (`total` - `advance_paid`) STORED,
  `payment_mode` enum('cash','upi','card','online','insurance','credit') DEFAULT NULL,
  `status` enum('draft','sent','partial','paid','overdue','refunded') DEFAULT 'draft',
  `stripe_payment_id` varchar(60) DEFAULT NULL,
  `razorpay_order_id` varchar(60) DEFAULT NULL,
  `razorpay_payment_id` varchar(60) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `pdf_path` varchar(255) DEFAULT NULL,
  `paid_at` timestamp NULL DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `invoice_items`
--

CREATE TABLE `invoice_items` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `invoice_id` bigint(20) UNSIGNED NOT NULL,
  `description` varchar(200) NOT NULL,
  `item_type` enum('consultation','procedure','medicine','lab','radiology','package','other') DEFAULT NULL,
  `qty` smallint(5) UNSIGNED DEFAULT 1,
  `unit_price` decimal(10,2) NOT NULL,
  `discount` decimal(10,2) DEFAULT 0.00,
  `total` decimal(10,2) GENERATED ALWAYS AS (`qty` * `unit_price` - `discount`) STORED
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `lab_orders`
--

CREATE TABLE `lab_orders` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `clinic_id` bigint(20) UNSIGNED NOT NULL,
  `patient_id` bigint(20) UNSIGNED NOT NULL,
  `visit_id` bigint(20) UNSIGNED DEFAULT NULL,
  `ordered_by` bigint(20) UNSIGNED NOT NULL,
  `test_id` bigint(20) UNSIGNED NOT NULL,
  `barcode` varchar(30) DEFAULT NULL,
  `sample_collected_at` timestamp NULL DEFAULT NULL,
  `collected_by` bigint(20) UNSIGNED DEFAULT NULL,
  `report_path` varchar(255) DEFAULT NULL,
  `share_token` varchar(64) DEFAULT NULL,
  `share_expires_at` timestamp NULL DEFAULT NULL,
  `status` enum('ordered','sample_collected','processing','resulted','verified','shared') DEFAULT 'ordered',
  `resulted_at` timestamp NULL DEFAULT NULL,
  `ordered_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `lab_results`
--

CREATE TABLE `lab_results` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `lab_order_id` bigint(20) UNSIGNED NOT NULL,
  `parameter_name` varchar(80) NOT NULL,
  `value` varchar(30) NOT NULL,
  `unit` varchar(20) DEFAULT NULL,
  `normal_range` varchar(40) DEFAULT NULL,
  `flag` enum('normal','low','high','critical_low','critical_high') DEFAULT 'normal',
  `entered_by` bigint(20) UNSIGNED NOT NULL,
  `verified_by` bigint(20) UNSIGNED DEFAULT NULL,
  `entered_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `lab_tests_catalog`
--

CREATE TABLE `lab_tests_catalog` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `clinic_id` bigint(20) UNSIGNED DEFAULT NULL,
  `test_code` varchar(20) NOT NULL,
  `test_name` varchar(150) NOT NULL,
  `category` enum('haematology','biochemistry','microbiology','serology','histopathology','other') DEFAULT NULL,
  `parameters` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`parameters`)),
  `sample_type` enum('blood','urine','stool','swab','csf','tissue','other') DEFAULT NULL,
  `tat_hours` tinyint(3) UNSIGNED DEFAULT 24,
  `rate` decimal(8,2) DEFAULT 0.00,
  `is_panel` tinyint(1) DEFAULT 0,
  `panel_tests` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`panel_tests`)),
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `module_catalog`
--

CREATE TABLE `module_catalog` (
  `id` varchar(40) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `category` enum('free','core','addon','platform') DEFAULT NULL,
  `price_monthly_usd` decimal(8,2) DEFAULT 0.00,
  `price_yearly_usd` decimal(8,2) DEFAULT 0.00,
  `specialties` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`specialties`)),
  `depends_on` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`depends_on`)),
  `included_in_plans` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`included_in_plans`)),
  `icon` varchar(40) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `sort_order` smallint(6) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `clinic_id` bigint(20) UNSIGNED NOT NULL,
  `patient_id` bigint(20) UNSIGNED DEFAULT NULL,
  `channel` enum('whatsapp','sms','email','push') NOT NULL,
  `template` varchar(60) NOT NULL,
  `to_number` varchar(20) DEFAULT NULL,
  `to_email` varchar(150) DEFAULT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`payload`)),
  `status` enum('queued','sent','failed','bounced') DEFAULT 'queued',
  `attempts` tinyint(4) DEFAULT 0,
  `error_log` text DEFAULT NULL,
  `scheduled_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `sent_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `otp_tokens`
--

CREATE TABLE `otp_tokens` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `phone` varchar(20) NOT NULL,
  `otp_hash` varchar(64) NOT NULL,
  `purpose` enum('portal_login','password_reset','verification') DEFAULT NULL,
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `used_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `password_reset_tokens`
--

CREATE TABLE `password_reset_tokens` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `email` varchar(150) NOT NULL,
  `token_hash` varchar(64) NOT NULL,
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `used_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `patients`
--

CREATE TABLE `patients` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `clinic_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `identity_id` bigint(20) UNSIGNED DEFAULT NULL,
  `uhid_seq` int(10) UNSIGNED DEFAULT 0,
  `uhid` varchar(20) NOT NULL,
  `name` varchar(100) NOT NULL,
  `dob` date DEFAULT NULL,
  `gender` enum('M','F','Other') DEFAULT NULL,
  `phone` varchar(20) NOT NULL,
  `email` varchar(150) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `blood_group` enum('A+','A-','B+','B-','O+','O-','AB+','AB-') DEFAULT NULL,
  `veg_type` enum('veg','nonveg','vegan','eggetarian') DEFAULT 'veg',
  `allergies` text DEFAULT NULL,
  `chronic_conditions` text DEFAULT NULL,
  `specialty_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`specialty_data`)),
  `photo_path` varchar(255) DEFAULT NULL,
  `qr_card_path` varchar(255) DEFAULT NULL,
  `qr_token` varchar(64) NOT NULL,
  `insurance_provider` varchar(100) DEFAULT NULL,
  `insurance_id` varchar(50) DEFAULT NULL,
  `referred_by` varchar(100) DEFAULT NULL,
  `source` enum('walk_in','referral','online','camp','other') DEFAULT 'walk_in',
  `is_active` tinyint(1) DEFAULT 1,
  `advance_balance` decimal(12,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `patient_allergies`
--

CREATE TABLE `patient_allergies` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `patient_id` bigint(20) UNSIGNED NOT NULL,
  `clinic_id` bigint(20) UNSIGNED NOT NULL,
  `allergen` varchar(100) NOT NULL,
  `reaction` varchar(200) DEFAULT NULL,
  `severity` enum('mild','moderate','severe','life_threatening') DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `patient_documents`
--

CREATE TABLE `patient_documents` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `clinic_id` bigint(20) UNSIGNED NOT NULL,
  `patient_id` bigint(20) UNSIGNED NOT NULL,
  `title` varchar(150) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `mime_type` varchar(80) DEFAULT NULL,
  `uploaded_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `patient_identities`
--

CREATE TABLE `patient_identities` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `phone_alt` varchar(20) DEFAULT NULL,
  `email` varchar(160) DEFAULT NULL,
  `phone_verified_at` datetime DEFAULT NULL,
  `email_verified_at` datetime DEFAULT NULL,
  `name` varchar(120) NOT NULL,
  `first_name` varchar(60) DEFAULT NULL,
  `middle_name` varchar(60) DEFAULT NULL,
  `last_name` varchar(60) DEFAULT NULL,
  `preferred_name` varchar(60) DEFAULT NULL,
  `dob` date DEFAULT NULL,
  `gender` enum('M','F','Other') DEFAULT NULL,
  `blood_group` enum('A+','A-','B+','B-','O+','O-','AB+','AB-') DEFAULT NULL,
  `veg_type` enum('veg','nonveg','vegan','eggetarian') DEFAULT NULL,
  `languages` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`languages`)),
  `allergies` text DEFAULT NULL,
  `chronic_conditions` text DEFAULT NULL,
  `photo_path` varchar(255) DEFAULT NULL,
  `source` enum('self_signup','clinic_created','imported') NOT NULL DEFAULT 'self_signup',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `address_line1` varchar(200) DEFAULT NULL,
  `address_line2` varchar(200) DEFAULT NULL,
  `address_city` varchar(80) DEFAULT NULL,
  `address_state` varchar(80) DEFAULT NULL,
  `address_postal_code` varchar(20) DEFAULT NULL,
  `address_country` char(2) DEFAULT 'IN',
  `emergency_contact_name` varchar(120) DEFAULT NULL,
  `emergency_contact_phone` varchar(20) DEFAULT NULL,
  `emergency_contact_relation` varchar(40) DEFAULT NULL,
  `abha_id` varchar(20) DEFAULT NULL,
  `gov_id_last4` char(4) DEFAULT NULL,
  `preferred_language` char(5) DEFAULT 'en'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `patient_otp_codes`
--

CREATE TABLE `patient_otp_codes` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `handle` varchar(160) NOT NULL,
  `channel` enum('sms','email') NOT NULL,
  `code_hash` char(64) NOT NULL,
  `attempts` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `expires_at` datetime NOT NULL,
  `consumed_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `patient_photos`
--

CREATE TABLE `patient_photos` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `clinic_id` bigint(20) UNSIGNED NOT NULL,
  `patient_id` bigint(20) UNSIGNED NOT NULL,
  `visit_id` bigint(20) UNSIGNED DEFAULT NULL,
  `type` enum('before','after','progress') DEFAULT NULL,
  `photo_path` varchar(255) NOT NULL,
  `condition_label` varchar(100) DEFAULT NULL,
  `is_public` tinyint(1) DEFAULT 0,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `patient_sessions`
--

CREATE TABLE `patient_sessions` (
  `id` char(64) NOT NULL,
  `identity_id` bigint(20) UNSIGNED NOT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_seen_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `expires_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `clinic_id` bigint(20) UNSIGNED NOT NULL,
  `invoice_id` bigint(20) UNSIGNED NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `method` enum('cash','upi','card','bank_transfer','online') DEFAULT NULL,
  `gateway_ref` varchar(80) DEFAULT NULL,
  `notes` varchar(200) DEFAULT NULL,
  `paid_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `recorded_by` bigint(20) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pharmacy_inventory`
--

CREATE TABLE `pharmacy_inventory` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `clinic_id` bigint(20) UNSIGNED NOT NULL,
  `drug_id` bigint(20) UNSIGNED NOT NULL,
  `batch_number` varchar(30) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 0,
  `low_stock_threshold` int(11) DEFAULT 10,
  `expiry_date` date NOT NULL,
  `purchase_price` decimal(10,2) DEFAULT NULL,
  `selling_price` decimal(10,2) DEFAULT NULL,
  `supplier` varchar(100) DEFAULT NULL,
  `location` varchar(50) DEFAULT NULL,
  `added_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pharmacy_narcotic_register`
--

CREATE TABLE `pharmacy_narcotic_register` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `clinic_id` bigint(20) UNSIGNED NOT NULL,
  `drug_id` bigint(20) UNSIGNED NOT NULL,
  `sale_id` bigint(20) UNSIGNED DEFAULT NULL,
  `patient_id` bigint(20) UNSIGNED DEFAULT NULL,
  `patient_name` varchar(100) DEFAULT NULL,
  `qty` int(11) NOT NULL,
  `balance_after` int(11) NOT NULL,
  `schedule` enum('H','H1') NOT NULL DEFAULT 'H',
  `recorded_by` bigint(20) UNSIGNED DEFAULT NULL,
  `recorded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pharmacy_sales`
--

CREATE TABLE `pharmacy_sales` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `clinic_id` bigint(20) UNSIGNED NOT NULL,
  `patient_id` bigint(20) UNSIGNED DEFAULT NULL,
  `subtotal` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `payment_mode` enum('cash','upi','card') DEFAULT 'cash',
  `sold_by` bigint(20) UNSIGNED DEFAULT NULL,
  `sold_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pharmacy_sale_items`
--

CREATE TABLE `pharmacy_sale_items` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `sale_id` bigint(20) UNSIGNED NOT NULL,
  `inventory_id` bigint(20) UNSIGNED NOT NULL,
  `drug_id` bigint(20) UNSIGNED NOT NULL,
  `qty` int(11) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `total` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `platform_admins`
--

CREATE TABLE `platform_admins` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `email` varchar(150) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `name` varchar(100) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `last_login_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `prescriptions`
--

CREATE TABLE `prescriptions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `clinic_id` bigint(20) UNSIGNED NOT NULL,
  `visit_id` bigint(20) UNSIGNED NOT NULL,
  `patient_id` bigint(20) UNSIGNED NOT NULL,
  `mode` enum('allopathic','homeopathic') NOT NULL DEFAULT 'allopathic',
  `drug_id` bigint(20) UNSIGNED DEFAULT NULL,
  `remedy_id` bigint(20) UNSIGNED DEFAULT NULL,
  `potency` varchar(10) DEFAULT NULL,
  `form` varchar(30) DEFAULT NULL,
  `dosage` varchar(60) DEFAULT NULL,
  `frequency` enum('OD','BD','TDS','QID','weekly','monthly','SOS','PRN') DEFAULT NULL,
  `duration_days` smallint(5) UNSIGNED DEFAULT NULL,
  `instructions` text DEFAULT NULL,
  `sort_order` tinyint(3) UNSIGNED DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `radiology_orders`
--

CREATE TABLE `radiology_orders` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `clinic_id` bigint(20) UNSIGNED NOT NULL,
  `patient_id` bigint(20) UNSIGNED NOT NULL,
  `visit_id` bigint(20) UNSIGNED DEFAULT NULL,
  `ordered_by` bigint(20) UNSIGNED NOT NULL,
  `radiologist_id` bigint(20) UNSIGNED DEFAULT NULL,
  `modality` enum('xray','ct','mri','ultrasound','mammography','dexa','pet','other') DEFAULT NULL,
  `body_part` varchar(80) DEFAULT NULL,
  `clinical_indication` text DEFAULT NULL,
  `report_text` longtext DEFAULT NULL,
  `impression` text DEFAULT NULL,
  `image_paths` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`image_paths`)),
  `dicom_study_uid` varchar(64) DEFAULT NULL,
  `share_token` varchar(64) DEFAULT NULL,
  `status` enum('ordered','in_progress','reported','verified','shared') DEFAULT 'ordered',
  `ordered_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `reported_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `remedies`
--

CREATE TABLE `remedies` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `abbreviation` varchar(20) DEFAULT NULL,
  `source` enum('plant','mineral','animal','nosode','sarcode') DEFAULT NULL,
  `key_indications` text DEFAULT NULL,
  `antidotes` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`antidotes`)),
  `complementaries` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`complementaries`)),
  `dietary_restrictions` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `saas_invoices`
--

CREATE TABLE `saas_invoices` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `clinic_id` bigint(20) UNSIGNED NOT NULL,
  `period_start` date NOT NULL,
  `period_end` date NOT NULL,
  `modules_billed` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`modules_billed`)),
  `total_usd` decimal(10,2) NOT NULL,
  `stripe_invoice_id` varchar(60) DEFAULT NULL,
  `razorpay_inv_id` varchar(60) DEFAULT NULL,
  `status` enum('draft','open','paid','void','uncollectable') DEFAULT 'open',
  `paid_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `specialty_configs`
--

CREATE TABLE `specialty_configs` (
  `clinic_id` bigint(20) UNSIGNED NOT NULL,
  `prescription_mode` enum('allopathic','homeopathic','dental','both') DEFAULT 'allopathic',
  `vitals_fields` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`vitals_fields`)),
  `visit_fields` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`visit_fields`)),
  `specialty_options` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`specialty_options`)),
  `invoice_tax_label` varchar(10) DEFAULT 'GST',
  `invoice_tax_percent` decimal(5,2) DEFAULT 0.00,
  `consultation_fee` decimal(10,2) NOT NULL DEFAULT 0.00,
  `slot_duration_min` tinyint(4) DEFAULT 15,
  `booking_window_days` smallint(5) UNSIGNED DEFAULT 30,
  `uhid_prefix` varchar(10) DEFAULT 'MC',
  `invoice_prefix` varchar(10) DEFAULT 'INV',
  `working_hours` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`working_hours`)),
  `whatsapp_number` varchar(20) DEFAULT NULL,
  `whatsapp_token` varchar(255) DEFAULT NULL,
  `razorpay_key` varchar(80) DEFAULT NULL,
  `razorpay_secret` varchar(80) DEFAULT NULL,
  `google_calendar_token` text DEFAULT NULL,
  `notification_prefs` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`notification_prefs`)),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `staff_attendance`
--

CREATE TABLE `staff_attendance` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `clinic_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `date` date NOT NULL,
  `clock_in` time DEFAULT NULL,
  `clock_out` time DEFAULT NULL,
  `status` enum('present','absent','half_day','leave','holiday') DEFAULT 'present',
  `notes` varchar(200) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `staff_invitations`
--

CREATE TABLE `staff_invitations` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `clinic_id` bigint(20) UNSIGNED NOT NULL,
  `invited_by` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `role` enum('doctor','nurse','receptionist','labtech') NOT NULL,
  `token_hash` varchar(64) NOT NULL,
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `accepted_at` timestamp NULL DEFAULT NULL,
  `created_user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `status` enum('pending','accepted','expired','revoked') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `staff_leaves`
--

CREATE TABLE `staff_leaves` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `clinic_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `leave_type` enum('CL','SL','EL','LWP','other') DEFAULT 'CL',
  `from_date` date NOT NULL,
  `to_date` date NOT NULL,
  `days` tinyint(3) UNSIGNED GENERATED ALWAYS AS (to_days(`to_date`) - to_days(`from_date`) + 1) STORED,
  `reason` text DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `approved_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tenants`
--

CREATE TABLE `tenants` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(150) NOT NULL,
  `slug` varchar(60) NOT NULL,
  `custom_domain` varchar(150) DEFAULT NULL,
  `specialty` enum('gp','homeopathy','dental','derma','peds','physio','other') DEFAULT 'gp',
  `country_code` char(2) DEFAULT 'IN',
  `currency` char(3) DEFAULT 'INR',
  `timezone` varchar(50) DEFAULT 'Asia/Kolkata',
  `plan` enum('free','clinic','practice','enterprise') DEFAULT 'free',
  `plan_expires_at` date DEFAULT NULL,
  `trial_ends_at` date DEFAULT NULL,
  `stripe_customer_id` varchar(60) DEFAULT NULL,
  `razorpay_customer_id` varchar(60) DEFAULT NULL,
  `white_label` tinyint(1) DEFAULT 0,
  `logo_path` varchar(255) DEFAULT NULL,
  `brand_color` char(7) DEFAULT '#0F9B6E',
  `gstin` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `onboarding_step` tinyint(4) DEFAULT 1,
  `onboarding_completed_at` timestamp NULL DEFAULT NULL,
  `seat_limit` tinyint(3) UNSIGNED DEFAULT 2,
  `extra_seats_purchased` tinyint(3) UNSIGNED DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `custom_domain_verified` tinyint(1) DEFAULT 0,
  `domain_verify_token` varchar(64) DEFAULT NULL,
  `churn_risk_level` enum('none','low','high') DEFAULT 'none',
  `churn_risk_reason` varchar(255) DEFAULT NULL,
  `last_staff_login_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `clinic_id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `google_id` varchar(255) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('superadmin','admin','doctor','nurse','receptionist','labtech','patient') NOT NULL DEFAULT 'receptionist',
  `is_owner` tinyint(1) DEFAULT 0,
  `custom_permissions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`custom_permissions`)),
  `specialization` varchar(100) DEFAULT NULL,
  `qualification` varchar(200) DEFAULT NULL,
  `incentive_percent` decimal(5,2) DEFAULT 0.00,
  `incentive_flat_fee` decimal(10,2) NOT NULL DEFAULT 0.00,
  `is_active` tinyint(1) DEFAULT 1,
  `remember_token` varchar(100) DEFAULT NULL,
  `last_login_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_sessions`
--

CREATE TABLE `user_sessions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `refresh_token_hash` varchar(64) NOT NULL,
  `device_label` varchar(120) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `is_current` tinyint(1) NOT NULL DEFAULT 0,
  `last_active_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `visits`
--

CREATE TABLE `visits` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `clinic_id` bigint(20) UNSIGNED NOT NULL,
  `appointment_id` bigint(20) UNSIGNED DEFAULT NULL,
  `patient_id` bigint(20) UNSIGNED NOT NULL,
  `doctor_id` bigint(20) UNSIGNED NOT NULL,
  `visit_number` tinyint(3) UNSIGNED DEFAULT 1,
  `status` enum('in_progress','completed','cancelled') NOT NULL DEFAULT 'in_progress',
  `chief_complaint` text DEFAULT NULL,
  `history` text DEFAULT NULL,
  `examination` text DEFAULT NULL,
  `diagnosis` text DEFAULT NULL,
  `icd10_code` varchar(10) DEFAULT NULL,
  `clinical_notes` longtext DEFAULT NULL,
  `rx_pdf_path` varchar(255) DEFAULT NULL,
  `specialty_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`specialty_data`)),
  `condition_score` tinyint(3) UNSIGNED DEFAULT NULL,
  `follow_up_date` date DEFAULT NULL,
  `follow_up_notes` text DEFAULT NULL,
  `unlocked_by` bigint(20) UNSIGNED DEFAULT NULL,
  `unlocked_at` timestamp NULL DEFAULT NULL,
  `visited_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `vitals`
--

CREATE TABLE `vitals` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `clinic_id` bigint(20) UNSIGNED NOT NULL,
  `visit_id` bigint(20) UNSIGNED NOT NULL,
  `patient_id` bigint(20) UNSIGNED NOT NULL,
  `bp_systolic` smallint(5) UNSIGNED DEFAULT NULL,
  `bp_diastolic` smallint(5) UNSIGNED DEFAULT NULL,
  `blood_sugar` decimal(5,1) DEFAULT NULL,
  `sugar_type` enum('fasting','pp','random') DEFAULT NULL,
  `weight_kg` decimal(5,2) DEFAULT NULL,
  `height_cm` decimal(5,1) DEFAULT NULL,
  `bmi` decimal(4,1) GENERATED ALWAYS AS (case when `height_cm` > 0 then round(`weight_kg` / (`height_cm` / 100 * (`height_cm` / 100)),1) else NULL end) STORED,
  `temperature` decimal(4,1) DEFAULT NULL,
  `spo2` tinyint(3) UNSIGNED DEFAULT NULL,
  `pulse_rate` smallint(5) UNSIGNED DEFAULT NULL,
  `tsh` decimal(6,3) DEFAULT NULL,
  `t3` decimal(6,2) DEFAULT NULL,
  `t4` decimal(6,2) DEFAULT NULL,
  `skin_score` tinyint(3) UNSIGNED DEFAULT NULL,
  `extra_vitals` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`extra_vitals`)),
  `recorded_by` bigint(20) UNSIGNED DEFAULT NULL,
  `recorded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `waiting_list`
--

CREATE TABLE `waiting_list` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `clinic_id` bigint(20) UNSIGNED NOT NULL,
  `patient_id` bigint(20) UNSIGNED NOT NULL,
  `doctor_id` bigint(20) UNSIGNED NOT NULL,
  `preferred_date` date NOT NULL,
  `notified` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `analytics_snapshots`
--
ALTER TABLE `analytics_snapshots`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_clinic_date_metric` (`clinic_id`,`date`,`metric_key`),
  ADD KEY `idx_clinic_date` (`clinic_id`,`date`);

--
-- Indexes for table `api_keys`
--
ALTER TABLE `api_keys`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_api_keys_clinic` (`clinic_id`);

--
-- Indexes for table `appointments`
--
ALTER TABLE `appointments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_doctor_date` (`doctor_id`,`scheduled_at`),
  ADD KEY `idx_patient` (`clinic_id`,`patient_id`),
  ADD KEY `idx_clinic_date` (`clinic_id`,`scheduled_at`,`status`),
  ADD KEY `fk_appt_patient` (`patient_id`),
  ADD KEY `fk_appt_created_by` (`created_by`),
  ADD KEY `idx_appointments_clinic_scheduled` (`clinic_id`,`scheduled_at`);

--
-- Indexes for table `audit_log`
--
ALTER TABLE `audit_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_clinic_audit` (`clinic_id`,`created_at`),
  ADD KEY `idx_table_record` (`table_name`,`record_id`),
  ADD KEY `fk_audit_user` (`user_id`);

--
-- Indexes for table `clinic_modules`
--
ALTER TABLE `clinic_modules`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_clinic_module` (`clinic_id`,`module_id`),
  ADD KEY `idx_active` (`clinic_id`,`is_active`,`expires_at`),
  ADD KEY `fk_clinic_modules_module` (`module_id`);

--
-- Indexes for table `consent_forms`
--
ALTER TABLE `consent_forms`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_patient_consents` (`clinic_id`,`patient_id`),
  ADD KEY `fk_consent_patient` (`patient_id`),
  ADD KEY `fk_consent_visit` (`visit_id`);

--
-- Indexes for table `consent_templates`
--
ALTER TABLE `consent_templates`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_clinic_templates` (`clinic_id`,`is_active`);

--
-- Indexes for table `crm_leads`
--
ALTER TABLE `crm_leads`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_clinic_crm` (`clinic_id`,`status`,`follow_up_date`),
  ADD KEY `fk_crm_ref_doc` (`referred_by_doctor`),
  ADD KEY `fk_crm_assigned` (`assigned_to`),
  ADD KEY `fk_crm_patient` (`converted_patient_id`);

--
-- Indexes for table `diet_plans`
--
ALTER TABLE `diet_plans`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_patient_diet` (`clinic_id`,`patient_id`),
  ADD KEY `fk_diet_patient` (`patient_id`),
  ADD KEY `fk_diet_visit` (`visit_id`),
  ADD KEY `fk_diet_prescriber` (`prescribed_by`);

--
-- Indexes for table `directory_cities`
--
ALTER TABLE `directory_cities`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_city_slug` (`slug`,`country_code`);

--
-- Indexes for table `directory_doctors`
--
ALTER TABLE `directory_doctors`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_place` (`place_id`),
  ADD KEY `idx_country_state_city` (`country`,`state`,`city`),
  ADD KEY `idx_specialty` (`specialty`),
  ADD KEY `idx_quality` (`is_active`,`quality_score`),
  ADD KEY `idx_claimed` (`is_claimed`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `discharge_summaries`
--
ALTER TABLE `discharge_summaries`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_patient_ds` (`clinic_id`,`patient_id`),
  ADD KEY `fk_ds_patient` (`patient_id`),
  ADD KEY `fk_ds_visit` (`visit_id`);

--
-- Indexes for table `doctor_incentives`
--
ALTER TABLE `doctor_incentives`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_incentive` (`clinic_id`,`doctor_id`,`period_month`),
  ADD KEY `fk_incentives_doctor` (`doctor_id`);

--
-- Indexes for table `doctor_leaves`
--
ALTER TABLE `doctor_leaves`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_doctor_leave` (`doctor_id`,`leave_date`),
  ADD KEY `fk_doctor_leaves_clinic` (`clinic_id`);

--
-- Indexes for table `doctor_locations`
--
ALTER TABLE `doctor_locations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_city` (`city`,`country_code`),
  ADD KEY `idx_latlng` (`lat`,`lng`),
  ADD KEY `fk_doctor_locations_profile` (`doctor_id`);

--
-- Indexes for table `doctor_profiles`
--
ALTER TABLE `doctor_profiles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_slug` (`slug`),
  ADD KEY `idx_specialty` (`specialty_primary`,`is_public`),
  ADD KEY `fk_doctor_profiles_user` (`user_id`),
  ADD KEY `fk_doctor_profiles_clinic` (`clinic_id`);
ALTER TABLE `doctor_profiles` ADD FULLTEXT KEY `idx_dir_search` (`full_name`,`specialty_primary`,`bio`);

--
-- Indexes for table `doctor_reviews`
--
ALTER TABLE `doctor_reviews`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_doctor_rating` (`doctor_id`,`rating`);

--
-- Indexes for table `doctor_schedules`
--
ALTER TABLE `doctor_schedules`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_doctor_day` (`doctor_id`,`day_of_week`,`is_active`),
  ADD KEY `fk_doctor_sched_clinic` (`clinic_id`);

--
-- Indexes for table `drugs`
--
ALTER TABLE `drugs`
  ADD PRIMARY KEY (`id`);
ALTER TABLE `drugs` ADD FULLTEXT KEY `idx_drug_search` (`name`,`generic_name`);

--
-- Indexes for table `events`
--
ALTER TABLE `events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_event` (`event_name`,`created_at`),
  ADD KEY `idx_clinic_events` (`clinic_id`,`created_at`),
  ADD KEY `fk_events_fired_by` (`fired_by`);

--
-- Indexes for table `expenses`
--
ALTER TABLE `expenses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_clinic_expenses` (`clinic_id`,`expense_date`),
  ADD KEY `fk_exp_entered_by` (`entered_by`);

--
-- Indexes for table `impersonation_tokens`
--
ALTER TABLE `impersonation_tokens`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_impersonate_token` (`token_hash`,`expires_at`),
  ADD KEY `fk_impersonate_admin` (`admin_id`),
  ADD KEY `fk_impersonate_clinic` (`clinic_id`),
  ADD KEY `fk_impersonate_user` (`user_id`);

--
-- Indexes for table `invoices`
--
ALTER TABLE `invoices`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_inv_num` (`clinic_id`,`invoice_number`),
  ADD KEY `idx_patient_invoices` (`clinic_id`,`patient_id`,`created_at`),
  ADD KEY `idx_status` (`clinic_id`,`status`,`created_at`),
  ADD KEY `fk_inv_patient` (`patient_id`),
  ADD KEY `fk_inv_visit` (`visit_id`),
  ADD KEY `fk_inv_attributed_doctor` (`attributed_doctor_id`);

--
-- Indexes for table `invoice_items`
--
ALTER TABLE `invoice_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_invoice` (`invoice_id`);

--
-- Indexes for table `lab_orders`
--
ALTER TABLE `lab_orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_patient_labs` (`clinic_id`,`patient_id`,`ordered_at`),
  ADD KEY `fk_lab_orders_patient` (`patient_id`),
  ADD KEY `fk_lab_orders_visit` (`visit_id`),
  ADD KEY `fk_lab_orders_ordered_by` (`ordered_by`),
  ADD KEY `fk_lab_orders_test` (`test_id`),
  ADD KEY `fk_lab_orders_collected_by` (`collected_by`);

--
-- Indexes for table `lab_results`
--
ALTER TABLE `lab_results`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_order` (`lab_order_id`),
  ADD KEY `fk_lab_results_entered_by` (`entered_by`),
  ADD KEY `fk_lab_results_verified_by` (`verified_by`);

--
-- Indexes for table `lab_tests_catalog`
--
ALTER TABLE `lab_tests_catalog`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_code` (`clinic_id`,`test_code`);
ALTER TABLE `lab_tests_catalog` ADD FULLTEXT KEY `idx_test_search` (`test_name`);

--
-- Indexes for table `module_catalog`
--
ALTER TABLE `module_catalog`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_queue` (`status`,`scheduled_at`),
  ADD KEY `idx_clinic_notifs` (`clinic_id`,`created_at`),
  ADD KEY `fk_notif_patient` (`patient_id`),
  ADD KEY `idx_notifications_queue` (`status`,`scheduled_at`);

--
-- Indexes for table `otp_tokens`
--
ALTER TABLE `otp_tokens`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_otp_phone` (`phone`,`expires_at`);

--
-- Indexes for table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_email_expires` (`email`,`expires_at`);

--
-- Indexes for table `patients`
--
ALTER TABLE `patients`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_uhid` (`clinic_id`,`uhid`),
  ADD UNIQUE KEY `uq_qr` (`qr_token`),
  ADD KEY `idx_name` (`clinic_id`,`name`),
  ADD KEY `idx_phone` (`clinic_id`,`phone`),
  ADD KEY `fk_patients_user` (`user_id`),
  ADD KEY `idx_patients_clinic_phone` (`clinic_id`,`phone`),
  ADD KEY `idx_patients_identity` (`identity_id`);
ALTER TABLE `patients` ADD FULLTEXT KEY `idx_search` (`name`,`phone`);

--
-- Indexes for table `patient_allergies`
--
ALTER TABLE `patient_allergies`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_patient` (`patient_id`),
  ADD KEY `fk_allergies_clinic` (`clinic_id`);

--
-- Indexes for table `patient_documents`
--
ALTER TABLE `patient_documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_patient_docs` (`clinic_id`,`patient_id`,`created_at`),
  ADD KEY `fk_patient_docs_patient` (`patient_id`),
  ADD KEY `fk_patient_docs_user` (`uploaded_by`);

--
-- Indexes for table `patient_identities`
--
ALTER TABLE `patient_identities`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_phone` (`phone`),
  ADD UNIQUE KEY `uq_email` (`email`),
  ADD KEY `idx_name` (`name`);

--
-- Indexes for table `patient_otp_codes`
--
ALTER TABLE `patient_otp_codes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_handle` (`handle`),
  ADD KEY `idx_expires` (`expires_at`);

--
-- Indexes for table `patient_photos`
--
ALTER TABLE `patient_photos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_patient_photos` (`clinic_id`,`patient_id`,`type`),
  ADD KEY `fk_photos_patient` (`patient_id`),
  ADD KEY `fk_photos_visit` (`visit_id`);

--
-- Indexes for table `patient_sessions`
--
ALTER TABLE `patient_sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_identity` (`identity_id`),
  ADD KEY `idx_expires` (`expires_at`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_invoice_payments` (`invoice_id`),
  ADD KEY `fk_payments_clinic` (`clinic_id`),
  ADD KEY `fk_payments_recorded_by` (`recorded_by`);

--
-- Indexes for table `pharmacy_inventory`
--
ALTER TABLE `pharmacy_inventory`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_drug_stock` (`clinic_id`,`drug_id`,`expiry_date`),
  ADD KEY `idx_low_stock` (`clinic_id`,`quantity`,`low_stock_threshold`),
  ADD KEY `fk_pharmacy_drug` (`drug_id`);

--
-- Indexes for table `pharmacy_narcotic_register`
--
ALTER TABLE `pharmacy_narcotic_register`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_clinic_narcotic` (`clinic_id`,`recorded_at`),
  ADD KEY `fk_narc_drug` (`drug_id`),
  ADD KEY `fk_narc_sale` (`sale_id`);

--
-- Indexes for table `pharmacy_sales`
--
ALTER TABLE `pharmacy_sales`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_clinic_sales` (`clinic_id`,`sold_at`),
  ADD KEY `fk_pharm_sale_patient` (`patient_id`),
  ADD KEY `fk_pharm_sale_user` (`sold_by`);

--
-- Indexes for table `pharmacy_sale_items`
--
ALTER TABLE `pharmacy_sale_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_sale` (`sale_id`),
  ADD KEY `fk_sale_items_inv` (`inventory_id`),
  ADD KEY `fk_sale_items_drug` (`drug_id`);

--
-- Indexes for table `platform_admins`
--
ALTER TABLE `platform_admins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_platform_admin_email` (`email`);

--
-- Indexes for table `prescriptions`
--
ALTER TABLE `prescriptions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_visit` (`visit_id`),
  ADD KEY `idx_patient_rx` (`clinic_id`,`patient_id`),
  ADD KEY `fk_rx_patient` (`patient_id`),
  ADD KEY `fk_rx_drug` (`drug_id`),
  ADD KEY `fk_rx_remedy` (`remedy_id`);

--
-- Indexes for table `radiology_orders`
--
ALTER TABLE `radiology_orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_patient_radiology` (`clinic_id`,`patient_id`,`ordered_at`),
  ADD KEY `fk_rad_patient` (`patient_id`),
  ADD KEY `fk_rad_visit` (`visit_id`),
  ADD KEY `fk_rad_ordered_by` (`ordered_by`),
  ADD KEY `fk_rad_radiologist` (`radiologist_id`);

--
-- Indexes for table `remedies`
--
ALTER TABLE `remedies`
  ADD PRIMARY KEY (`id`);
ALTER TABLE `remedies` ADD FULLTEXT KEY `idx_remedy_search` (`name`,`abbreviation`,`key_indications`);

--
-- Indexes for table `saas_invoices`
--
ALTER TABLE `saas_invoices`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_clinic_period` (`clinic_id`,`period_start`);

--
-- Indexes for table `specialty_configs`
--
ALTER TABLE `specialty_configs`
  ADD PRIMARY KEY (`clinic_id`);

--
-- Indexes for table `staff_attendance`
--
ALTER TABLE `staff_attendance`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_att` (`clinic_id`,`user_id`,`date`),
  ADD KEY `fk_att_user` (`user_id`);

--
-- Indexes for table `staff_invitations`
--
ALTER TABLE `staff_invitations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_clinic_invites` (`clinic_id`,`status`),
  ADD KEY `idx_email` (`email`,`status`),
  ADD KEY `fk_staff_inv_invited_by` (`invited_by`),
  ADD KEY `fk_staff_inv_user` (`created_user_id`);

--
-- Indexes for table `staff_leaves`
--
ALTER TABLE `staff_leaves`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_leave` (`clinic_id`,`user_id`,`from_date`),
  ADD KEY `fk_leave_user` (`user_id`),
  ADD KEY `fk_leave_approved_by` (`approved_by`);

--
-- Indexes for table `tenants`
--
ALTER TABLE `tenants`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_slug` (`slug`),
  ADD UNIQUE KEY `uq_domain` (`custom_domain`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_email` (`email`),
  ADD UNIQUE KEY `uq_google_id` (`google_id`),
  ADD KEY `idx_clinic_role` (`clinic_id`,`role`),
  ADD KEY `idx_owner` (`clinic_id`,`is_owner`);

--
-- Indexes for table `user_sessions`
--
ALTER TABLE `user_sessions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_refresh_hash` (`refresh_token_hash`),
  ADD KEY `idx_user_sessions` (`user_id`,`expires_at`);

--
-- Indexes for table `visits`
--
ALTER TABLE `visits`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_patient_visits` (`clinic_id`,`patient_id`,`visited_at`),
  ADD KEY `idx_doctor_visits` (`clinic_id`,`doctor_id`,`visited_at`),
  ADD KEY `fk_visits_patient` (`patient_id`),
  ADD KEY `fk_visits_doctor` (`doctor_id`),
  ADD KEY `fk_visits_appt` (`appointment_id`),
  ADD KEY `idx_visit_status` (`clinic_id`,`status`,`visited_at`),
  ADD KEY `idx_visits_clinic_status_visited` (`clinic_id`,`status`,`visited_at`);

--
-- Indexes for table `vitals`
--
ALTER TABLE `vitals`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_patient_vitals` (`clinic_id`,`patient_id`,`recorded_at`),
  ADD KEY `fk_vitals_visit` (`visit_id`),
  ADD KEY `fk_vitals_patient` (`patient_id`),
  ADD KEY `fk_vitals_recorded_by` (`recorded_by`);

--
-- Indexes for table `waiting_list`
--
ALTER TABLE `waiting_list`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_doctor_wait` (`clinic_id`,`doctor_id`,`preferred_date`),
  ADD KEY `fk_wait_patient` (`patient_id`),
  ADD KEY `fk_wait_doctor` (`doctor_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `analytics_snapshots`
--
ALTER TABLE `analytics_snapshots`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `api_keys`
--
ALTER TABLE `api_keys`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `appointments`
--
ALTER TABLE `appointments`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `audit_log`
--
ALTER TABLE `audit_log`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `clinic_modules`
--
ALTER TABLE `clinic_modules`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `consent_forms`
--
ALTER TABLE `consent_forms`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `consent_templates`
--
ALTER TABLE `consent_templates`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `crm_leads`
--
ALTER TABLE `crm_leads`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `diet_plans`
--
ALTER TABLE `diet_plans`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `directory_cities`
--
ALTER TABLE `directory_cities`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `directory_doctors`
--
ALTER TABLE `directory_doctors`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `discharge_summaries`
--
ALTER TABLE `discharge_summaries`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `doctor_incentives`
--
ALTER TABLE `doctor_incentives`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `doctor_leaves`
--
ALTER TABLE `doctor_leaves`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `doctor_locations`
--
ALTER TABLE `doctor_locations`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `doctor_profiles`
--
ALTER TABLE `doctor_profiles`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `doctor_reviews`
--
ALTER TABLE `doctor_reviews`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `doctor_schedules`
--
ALTER TABLE `doctor_schedules`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `drugs`
--
ALTER TABLE `drugs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `events`
--
ALTER TABLE `events`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `expenses`
--
ALTER TABLE `expenses`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `impersonation_tokens`
--
ALTER TABLE `impersonation_tokens`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `invoices`
--
ALTER TABLE `invoices`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `invoice_items`
--
ALTER TABLE `invoice_items`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `lab_orders`
--
ALTER TABLE `lab_orders`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `lab_results`
--
ALTER TABLE `lab_results`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `lab_tests_catalog`
--
ALTER TABLE `lab_tests_catalog`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `otp_tokens`
--
ALTER TABLE `otp_tokens`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `patients`
--
ALTER TABLE `patients`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `patient_allergies`
--
ALTER TABLE `patient_allergies`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `patient_documents`
--
ALTER TABLE `patient_documents`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `patient_identities`
--
ALTER TABLE `patient_identities`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `patient_otp_codes`
--
ALTER TABLE `patient_otp_codes`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `patient_photos`
--
ALTER TABLE `patient_photos`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pharmacy_inventory`
--
ALTER TABLE `pharmacy_inventory`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pharmacy_narcotic_register`
--
ALTER TABLE `pharmacy_narcotic_register`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pharmacy_sales`
--
ALTER TABLE `pharmacy_sales`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pharmacy_sale_items`
--
ALTER TABLE `pharmacy_sale_items`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `platform_admins`
--
ALTER TABLE `platform_admins`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `prescriptions`
--
ALTER TABLE `prescriptions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `radiology_orders`
--
ALTER TABLE `radiology_orders`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `remedies`
--
ALTER TABLE `remedies`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `saas_invoices`
--
ALTER TABLE `saas_invoices`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `staff_attendance`
--
ALTER TABLE `staff_attendance`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `staff_invitations`
--
ALTER TABLE `staff_invitations`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `staff_leaves`
--
ALTER TABLE `staff_leaves`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tenants`
--
ALTER TABLE `tenants`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `user_sessions`
--
ALTER TABLE `user_sessions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `visits`
--
ALTER TABLE `visits`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `vitals`
--
ALTER TABLE `vitals`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `waiting_list`
--
ALTER TABLE `waiting_list`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `analytics_snapshots`
--
ALTER TABLE `analytics_snapshots`
  ADD CONSTRAINT `fk_analytics_clinic` FOREIGN KEY (`clinic_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `api_keys`
--
ALTER TABLE `api_keys`
  ADD CONSTRAINT `fk_api_keys_clinic` FOREIGN KEY (`clinic_id`) REFERENCES `tenants` (`id`);

--
-- Constraints for table `appointments`
--
ALTER TABLE `appointments`
  ADD CONSTRAINT `fk_appt_clinic` FOREIGN KEY (`clinic_id`) REFERENCES `tenants` (`id`),
  ADD CONSTRAINT `fk_appt_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_appt_doctor` FOREIGN KEY (`doctor_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_appt_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`);

--
-- Constraints for table `audit_log`
--
ALTER TABLE `audit_log`
  ADD CONSTRAINT `fk_audit_clinic` FOREIGN KEY (`clinic_id`) REFERENCES `tenants` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_audit_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `clinic_modules`
--
ALTER TABLE `clinic_modules`
  ADD CONSTRAINT `fk_clinic_modules_clinic` FOREIGN KEY (`clinic_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_clinic_modules_module` FOREIGN KEY (`module_id`) REFERENCES `module_catalog` (`id`);

--
-- Constraints for table `consent_forms`
--
ALTER TABLE `consent_forms`
  ADD CONSTRAINT `fk_consent_clinic` FOREIGN KEY (`clinic_id`) REFERENCES `tenants` (`id`),
  ADD CONSTRAINT `fk_consent_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`),
  ADD CONSTRAINT `fk_consent_visit` FOREIGN KEY (`visit_id`) REFERENCES `visits` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `consent_templates`
--
ALTER TABLE `consent_templates`
  ADD CONSTRAINT `fk_consent_tpl_clinic` FOREIGN KEY (`clinic_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `crm_leads`
--
ALTER TABLE `crm_leads`
  ADD CONSTRAINT `fk_crm_assigned` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_crm_clinic` FOREIGN KEY (`clinic_id`) REFERENCES `tenants` (`id`),
  ADD CONSTRAINT `fk_crm_patient` FOREIGN KEY (`converted_patient_id`) REFERENCES `patients` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_crm_ref_doc` FOREIGN KEY (`referred_by_doctor`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `diet_plans`
--
ALTER TABLE `diet_plans`
  ADD CONSTRAINT `fk_diet_clinic` FOREIGN KEY (`clinic_id`) REFERENCES `tenants` (`id`),
  ADD CONSTRAINT `fk_diet_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`),
  ADD CONSTRAINT `fk_diet_prescriber` FOREIGN KEY (`prescribed_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_diet_visit` FOREIGN KEY (`visit_id`) REFERENCES `visits` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `discharge_summaries`
--
ALTER TABLE `discharge_summaries`
  ADD CONSTRAINT `fk_ds_clinic` FOREIGN KEY (`clinic_id`) REFERENCES `tenants` (`id`),
  ADD CONSTRAINT `fk_ds_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`),
  ADD CONSTRAINT `fk_ds_visit` FOREIGN KEY (`visit_id`) REFERENCES `visits` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `doctor_incentives`
--
ALTER TABLE `doctor_incentives`
  ADD CONSTRAINT `fk_incentives_clinic` FOREIGN KEY (`clinic_id`) REFERENCES `tenants` (`id`),
  ADD CONSTRAINT `fk_incentives_doctor` FOREIGN KEY (`doctor_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `doctor_leaves`
--
ALTER TABLE `doctor_leaves`
  ADD CONSTRAINT `fk_doctor_leaves_clinic` FOREIGN KEY (`clinic_id`) REFERENCES `tenants` (`id`),
  ADD CONSTRAINT `fk_doctor_leaves_doctor` FOREIGN KEY (`doctor_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `doctor_locations`
--
ALTER TABLE `doctor_locations`
  ADD CONSTRAINT `fk_doctor_locations_profile` FOREIGN KEY (`doctor_id`) REFERENCES `doctor_profiles` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `doctor_profiles`
--
ALTER TABLE `doctor_profiles`
  ADD CONSTRAINT `fk_doctor_profiles_clinic` FOREIGN KEY (`clinic_id`) REFERENCES `tenants` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_doctor_profiles_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `doctor_reviews`
--
ALTER TABLE `doctor_reviews`
  ADD CONSTRAINT `fk_doctor_reviews_profile` FOREIGN KEY (`doctor_id`) REFERENCES `doctor_profiles` (`id`);

--
-- Constraints for table `doctor_schedules`
--
ALTER TABLE `doctor_schedules`
  ADD CONSTRAINT `fk_doctor_sched_clinic` FOREIGN KEY (`clinic_id`) REFERENCES `tenants` (`id`),
  ADD CONSTRAINT `fk_doctor_sched_doctor` FOREIGN KEY (`doctor_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `events`
--
ALTER TABLE `events`
  ADD CONSTRAINT `fk_events_clinic` FOREIGN KEY (`clinic_id`) REFERENCES `tenants` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_events_fired_by` FOREIGN KEY (`fired_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `expenses`
--
ALTER TABLE `expenses`
  ADD CONSTRAINT `fk_exp_clinic` FOREIGN KEY (`clinic_id`) REFERENCES `tenants` (`id`),
  ADD CONSTRAINT `fk_exp_entered_by` FOREIGN KEY (`entered_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `impersonation_tokens`
--
ALTER TABLE `impersonation_tokens`
  ADD CONSTRAINT `fk_impersonate_admin` FOREIGN KEY (`admin_id`) REFERENCES `platform_admins` (`id`),
  ADD CONSTRAINT `fk_impersonate_clinic` FOREIGN KEY (`clinic_id`) REFERENCES `tenants` (`id`),
  ADD CONSTRAINT `fk_impersonate_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `invoices`
--
ALTER TABLE `invoices`
  ADD CONSTRAINT `fk_inv_attributed_doctor` FOREIGN KEY (`attributed_doctor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_inv_clinic` FOREIGN KEY (`clinic_id`) REFERENCES `tenants` (`id`),
  ADD CONSTRAINT `fk_inv_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`),
  ADD CONSTRAINT `fk_inv_visit` FOREIGN KEY (`visit_id`) REFERENCES `visits` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `invoice_items`
--
ALTER TABLE `invoice_items`
  ADD CONSTRAINT `fk_invoice_items_inv` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `lab_orders`
--
ALTER TABLE `lab_orders`
  ADD CONSTRAINT `fk_lab_orders_clinic` FOREIGN KEY (`clinic_id`) REFERENCES `tenants` (`id`),
  ADD CONSTRAINT `fk_lab_orders_collected_by` FOREIGN KEY (`collected_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_lab_orders_ordered_by` FOREIGN KEY (`ordered_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_lab_orders_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`),
  ADD CONSTRAINT `fk_lab_orders_test` FOREIGN KEY (`test_id`) REFERENCES `lab_tests_catalog` (`id`),
  ADD CONSTRAINT `fk_lab_orders_visit` FOREIGN KEY (`visit_id`) REFERENCES `visits` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `lab_results`
--
ALTER TABLE `lab_results`
  ADD CONSTRAINT `fk_lab_results_entered_by` FOREIGN KEY (`entered_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_lab_results_order` FOREIGN KEY (`lab_order_id`) REFERENCES `lab_orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_lab_results_verified_by` FOREIGN KEY (`verified_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `lab_tests_catalog`
--
ALTER TABLE `lab_tests_catalog`
  ADD CONSTRAINT `fk_lab_catalog_clinic` FOREIGN KEY (`clinic_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `fk_notif_clinic` FOREIGN KEY (`clinic_id`) REFERENCES `tenants` (`id`),
  ADD CONSTRAINT `fk_notif_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `patients`
--
ALTER TABLE `patients`
  ADD CONSTRAINT `fk_patients_clinic` FOREIGN KEY (`clinic_id`) REFERENCES `tenants` (`id`),
  ADD CONSTRAINT `fk_patients_identity` FOREIGN KEY (`identity_id`) REFERENCES `patient_identities` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_patients_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `patient_allergies`
--
ALTER TABLE `patient_allergies`
  ADD CONSTRAINT `fk_allergies_clinic` FOREIGN KEY (`clinic_id`) REFERENCES `tenants` (`id`),
  ADD CONSTRAINT `fk_allergies_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `patient_documents`
--
ALTER TABLE `patient_documents`
  ADD CONSTRAINT `fk_patient_docs_clinic` FOREIGN KEY (`clinic_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_patient_docs_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_patient_docs_user` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `patient_photos`
--
ALTER TABLE `patient_photos`
  ADD CONSTRAINT `fk_photos_clinic` FOREIGN KEY (`clinic_id`) REFERENCES `tenants` (`id`),
  ADD CONSTRAINT `fk_photos_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`),
  ADD CONSTRAINT `fk_photos_visit` FOREIGN KEY (`visit_id`) REFERENCES `visits` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `patient_sessions`
--
ALTER TABLE `patient_sessions`
  ADD CONSTRAINT `fk_patient_sessions_identity` FOREIGN KEY (`identity_id`) REFERENCES `patient_identities` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `fk_payments_clinic` FOREIGN KEY (`clinic_id`) REFERENCES `tenants` (`id`),
  ADD CONSTRAINT `fk_payments_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`),
  ADD CONSTRAINT `fk_payments_recorded_by` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `pharmacy_inventory`
--
ALTER TABLE `pharmacy_inventory`
  ADD CONSTRAINT `fk_pharmacy_clinic` FOREIGN KEY (`clinic_id`) REFERENCES `tenants` (`id`),
  ADD CONSTRAINT `fk_pharmacy_drug` FOREIGN KEY (`drug_id`) REFERENCES `drugs` (`id`);

--
-- Constraints for table `pharmacy_narcotic_register`
--
ALTER TABLE `pharmacy_narcotic_register`
  ADD CONSTRAINT `fk_narc_clinic` FOREIGN KEY (`clinic_id`) REFERENCES `tenants` (`id`),
  ADD CONSTRAINT `fk_narc_drug` FOREIGN KEY (`drug_id`) REFERENCES `drugs` (`id`),
  ADD CONSTRAINT `fk_narc_sale` FOREIGN KEY (`sale_id`) REFERENCES `pharmacy_sales` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `pharmacy_sales`
--
ALTER TABLE `pharmacy_sales`
  ADD CONSTRAINT `fk_pharm_sale_clinic` FOREIGN KEY (`clinic_id`) REFERENCES `tenants` (`id`),
  ADD CONSTRAINT `fk_pharm_sale_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_pharm_sale_user` FOREIGN KEY (`sold_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `pharmacy_sale_items`
--
ALTER TABLE `pharmacy_sale_items`
  ADD CONSTRAINT `fk_sale_items_drug` FOREIGN KEY (`drug_id`) REFERENCES `drugs` (`id`),
  ADD CONSTRAINT `fk_sale_items_inv` FOREIGN KEY (`inventory_id`) REFERENCES `pharmacy_inventory` (`id`),
  ADD CONSTRAINT `fk_sale_items_sale` FOREIGN KEY (`sale_id`) REFERENCES `pharmacy_sales` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `prescriptions`
--
ALTER TABLE `prescriptions`
  ADD CONSTRAINT `fk_rx_clinic` FOREIGN KEY (`clinic_id`) REFERENCES `tenants` (`id`),
  ADD CONSTRAINT `fk_rx_drug` FOREIGN KEY (`drug_id`) REFERENCES `drugs` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_rx_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`),
  ADD CONSTRAINT `fk_rx_remedy` FOREIGN KEY (`remedy_id`) REFERENCES `remedies` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_rx_visit` FOREIGN KEY (`visit_id`) REFERENCES `visits` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `radiology_orders`
--
ALTER TABLE `radiology_orders`
  ADD CONSTRAINT `fk_rad_clinic` FOREIGN KEY (`clinic_id`) REFERENCES `tenants` (`id`),
  ADD CONSTRAINT `fk_rad_ordered_by` FOREIGN KEY (`ordered_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_rad_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`),
  ADD CONSTRAINT `fk_rad_radiologist` FOREIGN KEY (`radiologist_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_rad_visit` FOREIGN KEY (`visit_id`) REFERENCES `visits` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `saas_invoices`
--
ALTER TABLE `saas_invoices`
  ADD CONSTRAINT `fk_saas_invoices_clinic` FOREIGN KEY (`clinic_id`) REFERENCES `tenants` (`id`);

--
-- Constraints for table `specialty_configs`
--
ALTER TABLE `specialty_configs`
  ADD CONSTRAINT `fk_specialty_configs_clinic` FOREIGN KEY (`clinic_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `staff_attendance`
--
ALTER TABLE `staff_attendance`
  ADD CONSTRAINT `fk_att_clinic` FOREIGN KEY (`clinic_id`) REFERENCES `tenants` (`id`),
  ADD CONSTRAINT `fk_att_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `staff_invitations`
--
ALTER TABLE `staff_invitations`
  ADD CONSTRAINT `fk_staff_inv_clinic` FOREIGN KEY (`clinic_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_staff_inv_invited_by` FOREIGN KEY (`invited_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_staff_inv_user` FOREIGN KEY (`created_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `staff_leaves`
--
ALTER TABLE `staff_leaves`
  ADD CONSTRAINT `fk_leave_approved_by` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_leave_clinic` FOREIGN KEY (`clinic_id`) REFERENCES `tenants` (`id`),
  ADD CONSTRAINT `fk_leave_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_users_clinic` FOREIGN KEY (`clinic_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_sessions`
--
ALTER TABLE `user_sessions`
  ADD CONSTRAINT `fk_user_sessions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `visits`
--
ALTER TABLE `visits`
  ADD CONSTRAINT `fk_visits_appt` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_visits_clinic` FOREIGN KEY (`clinic_id`) REFERENCES `tenants` (`id`),
  ADD CONSTRAINT `fk_visits_doctor` FOREIGN KEY (`doctor_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_visits_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`);

--
-- Constraints for table `vitals`
--
ALTER TABLE `vitals`
  ADD CONSTRAINT `fk_vitals_clinic` FOREIGN KEY (`clinic_id`) REFERENCES `tenants` (`id`),
  ADD CONSTRAINT `fk_vitals_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`),
  ADD CONSTRAINT `fk_vitals_recorded_by` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_vitals_visit` FOREIGN KEY (`visit_id`) REFERENCES `visits` (`id`);

--
-- Constraints for table `waiting_list`
--
ALTER TABLE `waiting_list`
  ADD CONSTRAINT `fk_wait_clinic` FOREIGN KEY (`clinic_id`) REFERENCES `tenants` (`id`),
  ADD CONSTRAINT `fk_wait_doctor` FOREIGN KEY (`doctor_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_wait_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
