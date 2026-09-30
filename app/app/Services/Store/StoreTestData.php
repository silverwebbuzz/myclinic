<?php

declare(strict_types=1);

namespace App\Services\Store;

use App\Core\Database;

/**
 * Markers for the store TEST data created by database/seeds/store_test_seed.php
 * (run on the live database so the flows can be tested end to end).
 *
 * Everything the seeder creates is recognisable by these markers, so
 * `store_test_seed.php --wipe` removes exactly that and nothing else, and the
 * few places that write into eClinicPro's OWN legal GST series skip it:
 * a test document in that series would leave a gap once the test data is
 * deleted (see SellerInvoiceService::issueMonth, TaxDocumentService::issueInvoice).
 */
final class StoreTestData
{
    /** store_vendors.slug of every test seller. */
    public const VENDOR_SLUG_PREFIX = 'test-';
    /**
     * Test customers: phone +91 90000 00000–00099 (same range as ECP_TEST_PHONE_PREFIX in
     * partials/patient_auth.php) AND email test.patientNN@example.com. Both must match, so a
     * real patient who happens to own a number in the range is never treated as test data.
     */
    public const PHONE_PREFIX = '+9190000000';
    public const CUSTOMER_EMAIL_LIKE = 'test.patient%@example.com';
    /**
     * store_orders.order_no of every test order: the real format (store/order.php and
     * the mobile API only open ECS{yymmdd}-{code}) with code TST + 3 digits, e.g.
     * ECS260930-TST007. Real codes (CheckoutService::newOrderNo) are 5 characters
     * without 0/1, or 8 hex digits, so they can never match this pattern.
     */
    public const ORDER_NO_REGEXP = '^ECS[0-9]{6}-TST[0-9]{3}$';
    /** Emails of test sellers/customers: example.com never delivers (RFC 2606). */
    public const EMAIL_DOMAIN = 'example.com';

    /** @var array<int, bool> */
    private static array $vendorCache = [];

    public static function isTestVendor(int $vendorId): bool
    {
        if (!isset(self::$vendorCache[$vendorId])) {
            $st = Database::connection()->prepare('SELECT slug FROM store_vendors WHERE id = :id');
            $st->execute(['id' => $vendorId]);
            self::$vendorCache[$vendorId] = str_starts_with((string) $st->fetchColumn(), self::VENDOR_SLUG_PREFIX);
        }

        return self::$vendorCache[$vendorId];
    }
}
