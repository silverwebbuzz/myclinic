<?php

declare(strict_types=1);

// Cron: every 10 minutes — php workers/store_maintenance.php
// eClinicPro Store housekeeping (the same checks also run lazily on page views):
//   1. unpaid orders past their payment window → confirm with Razorpay, else expire + release stock
//   2. paid packages the seller didn't accept by the deadline → auto-cancel + refund
require dirname(__DIR__) . '/vendor/autoload.php';

use App\Services\Store\FulfilmentService;
use App\Services\Store\OrderService;
use Dotenv\Dotenv;

$base = dirname(__DIR__);
if (is_file($base . '/.env')) {
    Dotenv::createImmutable($base)->safeLoad();
}

$expired = OrderService::expireStale(200);
$autoCancelled = FulfilmentService::autoCancelOverdue(50);
// 3. shipments with no courier update for ~2h → pull tracking from Shiprocket (webhook backup)
$polled = \App\Services\Store\ShippingService::pollDue(40);
// 4. return windows that ended → seller earnings become available for payout
$released = \App\Services\Store\SettlementService::releaseMatured();
// 5. once a day per seller: low-stock email
\App\Services\Store\StoreNotifier::lowStockDigests();
// 6. dispatched packages without a GST invoice (safety net; normally issued at dispatch)
$invoiced = \App\Services\Store\TaxDocumentService::issueMissing(50);
// 7. on the 1st: eClinicPro's GST invoices to sellers for last month (commission + courier + charges)
$sellerInvoices = \App\Services\Store\SellerInvoiceService::issueDue();
echo date('Y-m-d H:i:s') . " store: seller invoices {$sellerInvoices}, expired {$expired} unpaid order(s), auto-cancelled {$autoCancelled} late package(s), polled {$polled} shipment(s), released {$released} ledger entr(ies), invoiced {$invoiced} package(s)\n";
