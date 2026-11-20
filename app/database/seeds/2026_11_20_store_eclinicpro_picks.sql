-- =====================================================================
-- eClinicPro Store — seed the "eClinicPro Picks" affiliate seller (2026-11-20)
--   Server: run in phpMyAdmin AFTER app/database/patches/2026_11_20_store_affiliate.sql
--
--   Creates the seller ALREADY APPROVED as an affiliate seller (no KYC,
--   bank or pickup address needed) + its owner login:
--       login:    /vendor/login
--       email:    eclinicpro.com@gmail.com
--       password: given separately (only the bcrypt hash is stored here).
--                 Change it any time with "Forgot password" on /vendor/login.
--
--   Safe to run more than once: does nothing if the email or slug exists.
-- =====================================================================

INSERT INTO `store_vendors`
    (`slug`, `display_name`, `business_type`, `vendor_type`, `contact_name`, `email`, `phone`,
     `description`, `status`, `approved_at`)
SELECT 'eclinicpro-picks', 'eClinicPro Picks', 'other', 'affiliate', 'eClinicPro Team',
       'eclinicpro.com@gmail.com', '',
       'Health and wellness products hand-picked by the eClinicPro team. You buy them on the brand''s or partner''s own website; we may earn a commission at no extra cost to you.',
       'approved', NOW()
  FROM DUAL
 WHERE NOT EXISTS (SELECT 1 FROM `store_vendors` WHERE `slug` = 'eclinicpro-picks' OR `email` = 'eclinicpro.com@gmail.com');

INSERT INTO `store_vendor_users`
    (`vendor_id`, `name`, `email`, `password_hash`, `role`, `status`, `email_verified_at`)
SELECT v.`id`, 'eClinicPro Team', 'eclinicpro.com@gmail.com',
       '$2y$12$8yxSegrJjJV1DiPbkbCC8.QWQm.qGQFLUWHhrAU3aLc5NaBim6TIe',
       'owner', 'active', NOW()
  FROM `store_vendors` v
 WHERE v.`slug` = 'eclinicpro-picks'
   AND NOT EXISTS (SELECT 1 FROM `store_vendor_users` WHERE `email` = 'eclinicpro.com@gmail.com');

-- Check: should show one row each.
SELECT id, slug, display_name, vendor_type, status FROM `store_vendors` WHERE slug = 'eclinicpro-picks';
SELECT id, vendor_id, email, role, status FROM `store_vendor_users` WHERE email = 'eclinicpro.com@gmail.com';
