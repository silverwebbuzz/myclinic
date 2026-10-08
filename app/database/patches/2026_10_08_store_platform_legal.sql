-- eClinicPro Store: the company behind the platform (seller terms + eClinicPro's own invoices).
-- Same values can be edited later in /admin/store/settings → Invoicing.
-- Note: with legal name + GSTIN + address filled, eClinicPro starts issuing its own invoice
-- for the customer delivery fee (TaxDocumentService::platformReady).

INSERT INTO `platform_settings` (`setting_key`, `setting_value`, `is_secret`) VALUES
    ('store_platform_legal_name', 'SILVER WEBBUZZ PRIVATE LIMITED', 0),
    ('store_platform_gstin', '24ABHCS6317H1ZB', 0),
    ('store_platform_address', '1109, Satyamev Eminence, Science City Road, Near Shukan Mall, Sola, Ahmedabad, Gujarat 380060', 0),
    ('store_legal_jurisdiction_city', 'Ahmedabad, Gujarat', 0),
    ('store_grievance_email', 'hello@eclinicpro.com', 0)
ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`);
