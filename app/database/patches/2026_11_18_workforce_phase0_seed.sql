-- =====================================================================
-- Workforce Health — Phase 0 seed data (2026-11-18)
--
-- Run AFTER 2026_11_18_workforce_phase0.sql.
-- INSERT IGNORE everywhere: re-running never overwrites rows an admin has
-- since edited, and never duplicates.
--
-- Only IN and AE are switched on (is_active = 1). The other markets are
-- loaded but hidden until switched on from /admin/geo.
-- Tax RATES are deliberately not stored here (billing phase, verified per
-- country). Only the labels used on forms (GST/VAT, GSTIN/TRN) are seeded.
-- =====================================================================

-- ---------------------------------------------------------------------
-- Currencies (ISO 4217). minor_units = decimals shown.
-- ---------------------------------------------------------------------
INSERT IGNORE INTO `geo_currencies` (`code`, `name`, `symbol`, `minor_units`) VALUES
    ('INR', 'Indian rupee',        '₹',   2),
    ('AED', 'UAE dirham',          'AED', 2),
    ('SAR', 'Saudi riyal',         'SAR', 2),
    ('QAR', 'Qatari riyal',        'QAR', 2),
    ('KWD', 'Kuwaiti dinar',       'KWD', 3),
    ('BHD', 'Bahraini dinar',      'BHD', 3),
    ('OMR', 'Omani rial',          'OMR', 3),
    ('KES', 'Kenyan shilling',     'KSh', 2),
    ('NGN', 'Nigerian naira',      '₦',   2),
    ('ZAR', 'South African rand',  'R',   2),
    ('EGP', 'Egyptian pound',      'EGP', 2),
    ('GBP', 'Pound sterling',      '£',   2),
    ('EUR', 'Euro',                '€',   2),
    ('USD', 'US dollar',           '$',   2);

-- ---------------------------------------------------------------------
-- Countries
-- phone_nsn_lengths = allowed lengths of the national number (without the
-- country code and without a leading trunk 0).
-- ---------------------------------------------------------------------
INSERT IGNORE INTO `geo_countries`
    (`code`, `name`, `market`, `phone_code`, `phone_nsn_lengths`, `currency_code`, `default_timezone`,
     `date_format`, `time_format`, `week_start`, `region_label`, `tax_label`, `tax_id_label`,
     `company_id_label`, `postal_label`, `is_active`, `sort_order`) VALUES
-- India
    ('IN', 'India',                'india',       '91',  '10',            'INR', 'Asia/Kolkata',        'd M Y', 'h:i A', 1, 'State',        'GST', 'GSTIN',               'CIN',                      'PIN code',    1, 10),
-- Middle East
    ('AE', 'United Arab Emirates', 'middle_east', '971', '8,9',           'AED', 'Asia/Dubai',          'd M Y', 'h:i A', 1, 'Emirate',      'VAT', 'TRN',                 'Trade licence no.',        NULL,          1, 20),
    ('SA', 'Saudi Arabia',         'middle_east', '966', '8,9',           'SAR', 'Asia/Riyadh',         'd M Y', 'h:i A', 0, 'Region',       'VAT', 'VAT registration no.', 'CR number',               'Postal code', 0, 30),
    ('QA', 'Qatar',                'middle_east', '974', '8',             'QAR', 'Asia/Qatar',          'd M Y', 'h:i A', 0, 'Municipality', NULL,  NULL,                  'CR number',                NULL,          0, 40),
    ('KW', 'Kuwait',               'middle_east', '965', '8',             'KWD', 'Asia/Kuwait',         'd M Y', 'h:i A', 0, 'Governorate',  NULL,  NULL,                  'Commercial licence no.',   'Postal code', 0, 50),
    ('BH', 'Bahrain',              'middle_east', '973', '8',             'BHD', 'Asia/Bahrain',        'd M Y', 'h:i A', 0, 'Governorate',  'VAT', 'VAT account no.',     'CR number',                'Postal code', 0, 60),
    ('OM', 'Oman',                 'middle_east', '968', '8',             'OMR', 'Asia/Muscat',         'd M Y', 'h:i A', 0, 'Governorate',  'VAT', 'VATIN',               'CR number',                'Postal code', 0, 70),
-- Africa
    ('KE', 'Kenya',                'africa',      '254', '9',             'KES', 'Africa/Nairobi',      'd M Y', 'h:i A', 1, 'County',       'VAT', 'KRA PIN',             'Company registration no.', 'Postal code', 0, 80),
    ('NG', 'Nigeria',              'africa',      '234', '8,10',          'NGN', 'Africa/Lagos',        'd M Y', 'h:i A', 1, 'State',        'VAT', 'TIN',                 'RC number',                'Postal code', 0, 90),
    ('ZA', 'South Africa',         'africa',      '27',  '9',             'ZAR', 'Africa/Johannesburg', 'd M Y', 'H:i',   1, 'Province',     'VAT', 'VAT number',          'Company registration no.', 'Postal code', 0, 100),
    ('EG', 'Egypt',                'africa',      '20',  '9,10',          'EGP', 'Africa/Cairo',        'd M Y', 'h:i A', 0, 'Governorate',  'VAT', 'Tax registration no.', 'Commercial registry no.', 'Postal code', 0, 110),
-- Europe
    ('GB', 'United Kingdom',       'europe',      '44',  '9,10',          'GBP', 'Europe/London',       'd M Y', 'H:i',   1, 'County',       'VAT', 'VAT number',          'Company number',           'Postcode',    0, 120),
    ('IE', 'Ireland',              'europe',      '353', '7,8,9',         'EUR', 'Europe/Dublin',       'd M Y', 'H:i',   1, 'County',       'VAT', 'VAT number',          'CRO number',               'Eircode',     0, 130),
    ('DE', 'Germany',              'europe',      '49',  '6,7,8,9,10,11', 'EUR', 'Europe/Berlin',       'd M Y', 'H:i',   1, 'State',        'VAT', 'VAT ID (USt-IdNr.)',  'Commercial register no.',  'Postal code', 0, 140),
    ('FR', 'France',               'europe',      '33',  '9',             'EUR', 'Europe/Paris',        'd M Y', 'H:i',   1, 'Region',       'VAT', 'VAT number',          'SIREN',                    'Postal code', 0, 150),
    ('NL', 'Netherlands',          'europe',      '31',  '9',             'EUR', 'Europe/Amsterdam',    'd M Y', 'H:i',   1, 'Province',     'VAT', 'VAT number',          'KvK number',               'Postcode',    0, 160);

-- ---------------------------------------------------------------------
-- Regions: UAE emirates + Indian states / union territories.
-- Other countries' regions are added from /admin/geo when switched on.
-- ---------------------------------------------------------------------
INSERT IGNORE INTO `geo_regions` (`country_code`, `code`, `name`, `sort_order`) VALUES
    ('AE', 'AZ', 'Abu Dhabi',      10),
    ('AE', 'DU', 'Dubai',          20),
    ('AE', 'SH', 'Sharjah',        30),
    ('AE', 'AJ', 'Ajman',          40),
    ('AE', 'UQ', 'Umm Al Quwain',  50),
    ('AE', 'RK', 'Ras Al Khaimah', 60),
    ('AE', 'FU', 'Fujairah',       70);

INSERT IGNORE INTO `geo_regions` (`country_code`, `name`) VALUES
    ('IN', 'Andhra Pradesh'), ('IN', 'Arunachal Pradesh'), ('IN', 'Assam'), ('IN', 'Bihar'),
    ('IN', 'Chhattisgarh'), ('IN', 'Goa'), ('IN', 'Gujarat'), ('IN', 'Haryana'),
    ('IN', 'Himachal Pradesh'), ('IN', 'Jharkhand'), ('IN', 'Karnataka'), ('IN', 'Kerala'),
    ('IN', 'Madhya Pradesh'), ('IN', 'Maharashtra'), ('IN', 'Manipur'), ('IN', 'Meghalaya'),
    ('IN', 'Mizoram'), ('IN', 'Nagaland'), ('IN', 'Odisha'), ('IN', 'Punjab'),
    ('IN', 'Rajasthan'), ('IN', 'Sikkim'), ('IN', 'Tamil Nadu'), ('IN', 'Telangana'),
    ('IN', 'Tripura'), ('IN', 'Uttar Pradesh'), ('IN', 'Uttarakhand'), ('IN', 'West Bengal'),
    ('IN', 'Andaman and Nicobar Islands'), ('IN', 'Chandigarh'),
    ('IN', 'Dadra and Nagar Haveli and Daman and Diu'), ('IN', 'Delhi'),
    ('IN', 'Jammu and Kashmir'), ('IN', 'Ladakh'), ('IN', 'Lakshadweep'), ('IN', 'Puducherry');

-- ---------------------------------------------------------------------
-- Industries (top level; ISIC Rev.4 section / division for reference).
-- These are the axis for screening rule templates (country × industry).
-- ---------------------------------------------------------------------
INSERT IGNORE INTO `corp_industries` (`code`, `name`, `isic_code`, `sort_order`) VALUES
    ('construction',        'Construction',                         'F',   10),
    ('manufacturing',       'Manufacturing (general)',              'C',   20),
    ('food_processing',     'Food & beverage processing',           'C10', 30),
    ('chemicals',           'Chemicals & petrochemicals',           'C20', 40),
    ('pharma',              'Pharmaceuticals',                      'C21', 50),
    ('oil_gas',             'Oil & gas',                            'B06', 60),
    ('mining',              'Mining & quarrying',                   'B',   70),
    ('utilities',           'Electricity, gas & water utilities',   'D',   80),
    ('waste',               'Waste management & recycling',         'E',   90),
    ('transport_logistics', 'Transport, logistics & warehousing',   'H',  100),
    ('maritime',            'Maritime & shipping',                  'H50', 110),
    ('aviation',            'Aviation',                             'H51', 120),
    ('hospitality',         'Hotels, restaurants & catering',       'I',  130),
    ('retail',              'Retail & wholesale',                   'G',  140),
    ('healthcare',          'Healthcare & social care',             'Q',  150),
    ('education',           'Education',                            'P',  160),
    ('it_office',           'IT, software & office-based services', 'J',  170),
    ('finance',             'Banking, finance & insurance',         'K',  180),
    ('real_estate',         'Real estate & facilities management',  'L',  190),
    ('security_services',   'Security services',                    'N80', 200),
    ('cleaning_services',   'Cleaning & support services',          'N81', 210),
    ('agriculture',         'Agriculture, forestry & fishing',      'A',  220),
    ('government',          'Government & public administration',   'O',  230),
    ('media_entertainment', 'Media, arts & entertainment',          'R',  240),
    ('other',               'Other',                                NULL, 999);

-- ---------------------------------------------------------------------
-- Permissions (design §4.1). phase > 0 = hidden until that phase ships.
-- ---------------------------------------------------------------------
INSERT IGNORE INTO `corp_permissions` (`code`, `module`, `label`, `is_clinical`, `phase`, `sort_order`) VALUES
    ('company.view',          'company',   'View company profile',                          0, 0,  10),
    ('company.edit',          'company',   'Edit company profile and documents',            0, 0,  20),
    ('users.view',            'users',     'View company users',                            0, 0,  30),
    ('users.manage',          'users',     'Invite, disable and change roles of users',     0, 0,  40),
    ('roles.manage',          'roles',     'Create and edit custom roles',                  0, 0,  50),
    ('audit.view',            'audit',     'View the company activity log',                 0, 0,  60),
    ('billing.view',          'billing',   'View plan, trial and invoices',                 0, 0,  70),
    ('branches.manage',       'structure', 'Manage branches and work locations',            0, 1,  80),
    ('departments.manage',    'structure', 'Manage departments',                            0, 1,  90),
    ('jobroles.manage',       'structure', 'Manage job roles and risk categories',          0, 1, 100),
    ('employees.view',        'employees', 'View employees',                                0, 1, 110),
    ('employees.manage',      'employees', 'Add, edit and deactivate employees',            0, 1, 120),
    ('employees.import',      'employees', 'Bulk import employees',                         0, 1, 130),
    ('employees.export',      'employees', 'Export employee lists',                         0, 1, 140),
    ('screening.view_status', 'screening', 'View screening status and fitness outcomes',    0, 1, 150),
    ('screening.manage',      'screening', 'Manage screening programmes and assignments',   0, 1, 160),
    ('rules.customise',       'screening', 'Adopt and customise screening rule templates',  0, 1, 170),
    ('bookings.manage',       'booking',   'Book and manage employee appointments',         0, 1, 180),
    ('providers.manage',      'providers', 'Manage clinic / lab network and contracts',     0, 1, 190),
    ('reports.view',          'reports',   'View reports',                                  0, 1, 200),
    ('reports.export',        'reports',   'Export reports (PDF / Excel / CSV)',            0, 1, 210),
    ('hazards.manage',        'hse',       'Manage workplace hazards',                      0, 2, 220),
    ('risk_register.manage',  'hse',       'Manage the risk register',                      0, 2, 230),
    ('medical.view_clinical', 'clinical',  'View clinical details (with employee consent)',  1, 2, 240);

-- ---------------------------------------------------------------------
-- System roles (company_id = 0). Design §4.2.
-- ---------------------------------------------------------------------
INSERT IGNORE INTO `corp_roles`
    (`company_id`, `code`, `name`, `description`, `scope_type`, `is_system`, `is_clinical`, `sort_order`, `created_at`) VALUES
    (0, 'company_admin',  'Company Admin',               'Full control of the company account. No clinical access.',                 'company', 1, 0, 10, UTC_TIMESTAMP()),
    (0, 'hr_manager',     'HR Manager',                  'Employees, structure, bookings and administrative compliance.',            'company', 1, 0, 20, UTC_TIMESTAMP()),
    (0, 'hse_manager',    'HSE Manager',                 'Hazards, risk register, screening programmes and schedules.',             'company', 1, 0, 30, UTC_TIMESTAMP()),
    (0, 'branch_manager', 'Branch Manager',              'Employees and screening status for assigned branch only.',                'branch',  1, 0, 40, UTC_TIMESTAMP()),
    (0, 'billing_admin',  'Billing Admin',               'Plan, trial, invoices and spend reports.',                                'company', 1, 0, 50, UTC_TIMESTAMP()),
    (0, 'viewer',         'Viewer',                      'Read-only access to employees, status and reports.',                      'company', 1, 0, 60, UTC_TIMESTAMP()),
    (0, 'oh_physician',   'Company Occupational Physician', 'In-house doctor. May see clinical details with employee consent.',     'company', 1, 1, 70, UTC_TIMESTAMP());

-- Company Admin: every NON-clinical permission
INSERT IGNORE INTO `corp_role_permissions` (`role_id`, `permission_code`)
SELECT r.id, p.code FROM `corp_roles` r JOIN `corp_permissions` p ON p.is_clinical = 0
WHERE r.company_id = 0 AND r.code = 'company_admin';

INSERT IGNORE INTO `corp_role_permissions` (`role_id`, `permission_code`)
SELECT r.id, p.code FROM `corp_roles` r JOIN `corp_permissions` p
WHERE r.company_id = 0 AND r.code = 'hr_manager' AND p.code IN (
    'company.view', 'users.view', 'departments.manage', 'jobroles.manage',
    'employees.view', 'employees.manage', 'employees.import', 'employees.export',
    'screening.view_status', 'bookings.manage', 'reports.view', 'reports.export');

INSERT IGNORE INTO `corp_role_permissions` (`role_id`, `permission_code`)
SELECT r.id, p.code FROM `corp_roles` r JOIN `corp_permissions` p
WHERE r.company_id = 0 AND r.code = 'hse_manager' AND p.code IN (
    'company.view', 'jobroles.manage', 'employees.view',
    'screening.view_status', 'screening.manage', 'rules.customise',
    'hazards.manage', 'risk_register.manage', 'reports.view', 'reports.export');

INSERT IGNORE INTO `corp_role_permissions` (`role_id`, `permission_code`)
SELECT r.id, p.code FROM `corp_roles` r JOIN `corp_permissions` p
WHERE r.company_id = 0 AND r.code = 'branch_manager' AND p.code IN (
    'company.view', 'employees.view', 'screening.view_status', 'bookings.manage', 'reports.view');

INSERT IGNORE INTO `corp_role_permissions` (`role_id`, `permission_code`)
SELECT r.id, p.code FROM `corp_roles` r JOIN `corp_permissions` p
WHERE r.company_id = 0 AND r.code = 'billing_admin' AND p.code IN (
    'company.view', 'billing.view', 'reports.view');

INSERT IGNORE INTO `corp_role_permissions` (`role_id`, `permission_code`)
SELECT r.id, p.code FROM `corp_roles` r JOIN `corp_permissions` p
WHERE r.company_id = 0 AND r.code = 'viewer' AND p.code IN (
    'company.view', 'employees.view', 'screening.view_status', 'reports.view');

INSERT IGNORE INTO `corp_role_permissions` (`role_id`, `permission_code`)
SELECT r.id, p.code FROM `corp_roles` r JOIN `corp_permissions` p
WHERE r.company_id = 0 AND r.code = 'oh_physician' AND p.code IN (
    'company.view', 'employees.view', 'screening.view_status', 'screening.manage', 'rules.customise',
    'hazards.manage', 'risk_register.manage', 'reports.view', 'reports.export', 'medical.view_clinical');

-- ---------------------------------------------------------------------
-- Check (optional — run after import, expect: 14 / 16 / 43 / 25 / 24 / 7)
-- SELECT (SELECT COUNT(*) FROM geo_currencies), (SELECT COUNT(*) FROM geo_countries),
--        (SELECT COUNT(*) FROM geo_regions), (SELECT COUNT(*) FROM corp_industries),
--        (SELECT COUNT(*) FROM corp_permissions), (SELECT COUNT(*) FROM corp_roles WHERE company_id = 0);
-- ---------------------------------------------------------------------
