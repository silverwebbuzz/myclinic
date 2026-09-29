-- Lists every store table that is MISSING on this database, with the patch that creates it.
SELECT e.patch, e.tbl AS missing_table
FROM (
  SELECT 'store_brands' AS tbl, '2026_09_28_store_catalog.sql' AS patch
  UNION ALL SELECT 'store_collections' AS tbl, '2026_09_28_store_catalog.sql' AS patch
  UNION ALL SELECT 'store_collection_products' AS tbl, '2026_09_28_store_catalog.sql' AS patch
  UNION ALL SELECT 'store_attributes' AS tbl, '2026_09_28_store_catalog.sql' AS patch
  UNION ALL SELECT 'store_attribute_values' AS tbl, '2026_09_28_store_catalog.sql' AS patch
  UNION ALL SELECT 'store_category_attributes' AS tbl, '2026_09_28_store_catalog.sql' AS patch
  UNION ALL SELECT 'store_products' AS tbl, '2026_09_28_store_catalog.sql' AS patch
  UNION ALL SELECT 'store_product_categories' AS tbl, '2026_09_28_store_catalog.sql' AS patch
  UNION ALL SELECT 'store_product_concerns' AS tbl, '2026_09_28_store_catalog.sql' AS patch
  UNION ALL SELECT 'store_product_variants' AS tbl, '2026_09_28_store_catalog.sql' AS patch
  UNION ALL SELECT 'store_variant_options' AS tbl, '2026_09_28_store_catalog.sql' AS patch
  UNION ALL SELECT 'store_product_images' AS tbl, '2026_09_28_store_catalog.sql' AS patch
  UNION ALL SELECT 'store_inventory_movements' AS tbl, '2026_09_28_store_catalog.sql' AS patch
  UNION ALL SELECT 'store_commission_rules' AS tbl, '2026_09_28_store_catalog.sql' AS patch
  UNION ALL SELECT 'store_wishlist' AS tbl, '2026_09_28_store_catalog.sql' AS patch
  UNION ALL SELECT 'store_audit_log' AS tbl, '2026_09_28_store_foundation.sql' AS patch
  UNION ALL SELECT 'store_webhook_events' AS tbl, '2026_09_28_store_foundation.sql' AS patch
  UNION ALL SELECT 'store_notifications' AS tbl, '2026_09_28_store_foundation.sql' AS patch
  UNION ALL SELECT 'store_categories' AS tbl, '2026_09_28_store_taxonomy.sql' AS patch
  UNION ALL SELECT 'store_nav_items' AS tbl, '2026_09_28_store_taxonomy.sql' AS patch
  UNION ALL SELECT 'store_nav_item_targets' AS tbl, '2026_09_28_store_taxonomy.sql' AS patch
  UNION ALL SELECT 'store_concerns' AS tbl, '2026_09_28_store_taxonomy.sql' AS patch
  UNION ALL SELECT 'store_concern_categories' AS tbl, '2026_09_28_store_taxonomy.sql' AS patch
  UNION ALL SELECT 'store_vendors' AS tbl, '2026_09_28_store_vendors.sql' AS patch
  UNION ALL SELECT 'store_vendor_users' AS tbl, '2026_09_28_store_vendors.sql' AS patch
  UNION ALL SELECT 'store_vendor_addresses' AS tbl, '2026_09_28_store_vendors.sql' AS patch
  UNION ALL SELECT 'store_vendor_bank_accounts' AS tbl, '2026_09_28_store_vendors.sql' AS patch
  UNION ALL SELECT 'store_vendor_documents' AS tbl, '2026_09_28_store_vendors.sql' AS patch
  UNION ALL SELECT 'store_customer_flags' AS tbl, '2026_09_29_store_orders.sql' AS patch
  UNION ALL SELECT 'store_addresses' AS tbl, '2026_09_29_store_orders.sql' AS patch
  UNION ALL SELECT 'store_carts' AS tbl, '2026_09_29_store_orders.sql' AS patch
  UNION ALL SELECT 'store_cart_items' AS tbl, '2026_09_29_store_orders.sql' AS patch
  UNION ALL SELECT 'store_coupons' AS tbl, '2026_09_29_store_orders.sql' AS patch
  UNION ALL SELECT 'store_coupon_redemptions' AS tbl, '2026_09_29_store_orders.sql' AS patch
  UNION ALL SELECT 'store_shipping_rules' AS tbl, '2026_09_29_store_orders.sql' AS patch
  UNION ALL SELECT 'store_orders' AS tbl, '2026_09_29_store_orders.sql' AS patch
  UNION ALL SELECT 'store_vendor_orders' AS tbl, '2026_09_29_store_orders.sql' AS patch
  UNION ALL SELECT 'store_order_items' AS tbl, '2026_09_29_store_orders.sql' AS patch
  UNION ALL SELECT 'store_order_status_history' AS tbl, '2026_09_29_store_orders.sql' AS patch
  UNION ALL SELECT 'store_payments' AS tbl, '2026_09_29_store_orders.sql' AS patch
  UNION ALL SELECT 'store_payment_transactions' AS tbl, '2026_09_29_store_orders.sql' AS patch
  UNION ALL SELECT 'store_refunds' AS tbl, '2026_09_29_store_orders.sql' AS patch
  UNION ALL SELECT 'store_refund_items' AS tbl, '2026_09_29_store_orders.sql' AS patch
  UNION ALL SELECT 'store_shipments' AS tbl, '2026_09_30_store_fulfilment.sql' AS patch
  UNION ALL SELECT 'store_shipment_items' AS tbl, '2026_09_30_store_fulfilment.sql' AS patch
  UNION ALL SELECT 'store_shipment_events' AS tbl, '2026_09_30_store_fulfilment.sql' AS patch
  UNION ALL SELECT 'store_shiprocket_status_map' AS tbl, '2026_09_30_store_fulfilment.sql' AS patch
  UNION ALL SELECT 'store_returns' AS tbl, '2026_09_30_store_fulfilment.sql' AS patch
  UNION ALL SELECT 'store_return_items' AS tbl, '2026_09_30_store_fulfilment.sql' AS patch
  UNION ALL SELECT 'store_vendor_ledger' AS tbl, '2026_09_30_store_fulfilment.sql' AS patch
  UNION ALL SELECT 'store_payouts' AS tbl, '2026_09_30_store_fulfilment.sql' AS patch
  UNION ALL SELECT 'store_reviews' AS tbl, '2026_09_30_store_fulfilment.sql' AS patch
  UNION ALL SELECT 'store_banners' AS tbl, '2026_09_30_store_fulfilment.sql' AS patch
  UNION ALL SELECT 'store_tax_documents' AS tbl, '2026_10_01_store_tax_documents.sql' AS patch
  UNION ALL SELECT 'store_tax_document_lines' AS tbl, '2026_10_01_store_tax_documents.sql' AS patch
  UNION ALL SELECT 'store_tax_doc_sequences' AS tbl, '2026_10_01_store_tax_documents.sql' AS patch
  UNION ALL SELECT 'store_policy_pages' AS tbl, '2026_10_01_store_tax_documents.sql' AS patch
  UNION ALL SELECT 'store_policy_versions' AS tbl, '2026_10_01_store_tax_documents.sql' AS patch
  UNION ALL SELECT 'store_policy_acceptances' AS tbl, '2026_10_01_store_tax_documents.sql' AS patch
  UNION ALL SELECT 'store_hsn_codes' AS tbl, '2026_10_02_store_hsn_codes.sql' AS patch
  UNION ALL SELECT 'store_email_templates' AS tbl, '2026_10_03_store_emails.sql' AS patch
  UNION ALL SELECT 'store_email_log' AS tbl, '2026_10_03_store_emails.sql' AS patch
  UNION ALL SELECT 'store_vendor_password_resets' AS tbl, '2026_10_03_store_emails.sql' AS patch
  UNION ALL SELECT 'store_payout_requests' AS tbl, '2026_10_03_store_emails.sql' AS patch
) e
LEFT JOIN information_schema.tables t ON t.table_schema = DATABASE() AND t.table_name = e.tbl
WHERE t.table_name IS NULL
ORDER BY e.patch, e.tbl;
