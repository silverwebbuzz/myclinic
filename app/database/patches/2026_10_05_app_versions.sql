-- =====================================================================
-- Patient mobile app update check (2026-10-05)
--   Server: mysql silverwebbuzz_in_myclinic < app/database/patches/2026_10_05_app_versions.sql
--
-- Settings read by api/mobile/v1/app_version.php and edited on
-- /admin/app-versions. Seeded EMPTY: with no latest build / store URL the
-- endpoint answers update = "none", so nothing changes for users until an
-- admin fills them in. (The admin page also creates them on save, so this
-- patch is optional; it just makes the keys visible in platform_settings.)
--
-- Safe to run more than once (INSERT IGNORE keeps existing values).
-- =====================================================================

INSERT IGNORE INTO `platform_settings` (`setting_key`, `setting_value`, `is_secret`) VALUES
    ('app_android_latest_version', '', 0),   -- display string, e.g. 1.2.0
    ('app_android_latest_build',   '', 0),   -- int: build number (the 7 in 1.2.0+7)
    ('app_android_min_build',      '', 0),   -- int: below this the app must update ('' = no minimum)
    ('app_android_store_url',      '', 0),
    ('app_android_update_title',   '', 0),
    ('app_android_update_message', '', 0),
    ('app_android_update_notes',   '', 0),   -- "what's new", one line per item
    ('app_ios_latest_version',     '', 0),
    ('app_ios_latest_build',       '', 0),
    ('app_ios_min_build',          '', 0),
    ('app_ios_store_url',          '', 0),
    ('app_ios_update_title',       '', 0),
    ('app_ios_update_message',     '', 0),
    ('app_ios_update_notes',       '', 0);
