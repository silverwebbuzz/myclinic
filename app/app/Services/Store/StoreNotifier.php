<?php

declare(strict_types=1);

namespace App\Services\Store;

use App\Core\Database;
use App\Core\QueryBuilder;
use App\Services\SmtpMailService;

/**
 * Store notifications: branded HTML email (SMTP) + in-app rows (store_notifications).
 * Best-effort ALWAYS — called after the database commit, never inside it, so a
 * mail failure can't undo a paid order. Email only goes to addresses we have.
 *
 * Split of responsibilities:
 *   - StoreEmailTemplates owns each email's WORDING (subject, heading, message,
 *     button label, extra note), admin-editable, plus the on/off switch.
 *   - This class decides WHEN to send and fills the automatic parts: details box,
 *     item lines, totals, address, warnings and the button's link.
 *   - render() draws every email with one layout.
 * Every attempt is logged to store_email_log (sent / failed / disabled).
 */
final class StoreNotifier
{
    // ------------------------------------------------------------------
    // Orders
    // ------------------------------------------------------------------

    public static function orderPaid(int $orderId): void
    {
        try {
            $o = OrderService::load($orderId);
            if ($o === null) {
                return;
            }
            $a = $o['ship_address'];
            $total = self::rs((int) $o['grand_total_paise']);

            // Customer
            $packages = [];
            foreach ($o['vendor_orders'] as $vo) {
                $packages[] = [
                    'heading' => 'Sold by ' . $vo['vendor_name'],
                    'sub' => (int) $vo['shipping_paise'] > 0 ? 'Shipping ' . self::rs((int) $vo['shipping_paise']) : 'Free shipping',
                    'lines' => array_map(static fn ($it) => [
                        'name' => (string) $it['name'],
                        'meta' => trim(($it['variant_title'] ? $it['variant_title'] . ' · ' : '') . 'Qty ' . (int) $it['qty']),
                        'amount' => self::rs((int) $it['line_total_paise']),
                        'image' => $it['image_path'] ?? null,
                    ], $vo['items']),
                ];
            }
            $totals = [['Items', self::rs((int) $o['items_subtotal_paise'])]];
            if ((int) $o['discount_paise'] > 0) {
                $totals[] = ['Coupon ' . $o['coupon_code'], '−' . self::rs((int) $o['discount_paise'])];
            }
            $totals[] = ['Shipping', (int) $o['shipping_paise'] > 0 ? self::rs((int) $o['shipping_paise']) : 'Free'];
            $totals[] = ['Total paid', $total, true];
            self::send('customer_order_confirmed', (string) $o['contact_email'],
                ['name' => self::first((string) $o['contact_name']), 'order_no' => (string) $o['order_no'], 'amount' => $total], [
                    'facts' => [['Order number', (string) $o['order_no']], ['Paid on', self::when((string) ($o['paid_at'] ?? $o['placed_at']))],
                        ['Packages', (string) count($o['vendor_orders'])]],
                    'packages' => $packages,
                    'totals' => $totals,
                    'totals_note' => 'Includes ' . self::rs((int) $o['tax_included_paise']) . ' GST.',
                    'address_label' => 'Delivering to',
                    'address' => self::addressLines($a),
                    'cta_url' => self::orderUrl((string) $o['order_no']),
                ]);

            // Each seller: ONLY their own package (no other sellers' items or the customer's contact details).
            foreach ($o['vendor_orders'] as $vo) {
                $vendor = VendorService::find((int) $vo['vendor_id']);
                if ($vendor === null) {
                    continue;
                }
                $acceptBy = self::when((string) $vo['accept_by']);
                self::send('seller_new_order', (string) $vendor['email'],
                    self::sellerVars($vendor) + ['order_no' => (string) $vo['sub_order_no'], 'accept_by' => $acceptBy], [
                        'facts' => [
                            ['Order', (string) $vo['sub_order_no']],
                            ['Accept by', $acceptBy],
                            ['Deliver to', trim(($a['city'] ?? '') . ', ' . ($a['state'] ?? '') . ' ' . ($a['pincode'] ?? ''), ' ,')],
                        ],
                        'notice' => ['tone' => 'warn', 'text' => 'Orders not accepted by ' . $acceptBy . ' are cancelled automatically and the customer is refunded.'],
                        'packages' => [['heading' => 'Items to pack', 'lines' => self::sellerLines($vo['items'])]],
                        'cta_url' => self::portalUrl('/vendor/orders/' . (int) $vo['id']),
                    ]);
                $owner = self::owner((int) $vendor['id']);
                if ($owner !== null) {
                    self::inApp('vendor_user', (int) $owner['id'], 'order.new', 'New order ' . $vo['sub_order_no'],
                        count($vo['items']) . ' item(s) to pack', '/vendor/orders/' . (int) $vo['id']);
                }
            }

            // Store team
            self::sendTeam('team_order_paid', ['order_no' => (string) $o['order_no'], 'amount' => $total], [
                'facts' => [
                    ['Order', (string) $o['order_no']],
                    ['Amount', $total],
                    ['Sellers', implode(', ', array_map(static fn ($vo) => (string) $vo['vendor_name'], $o['vendor_orders']))],
                    ['Deliver to', trim(($a['city'] ?? '') . ', ' . ($a['state'] ?? ''), ' ,')],
                    ['Channel', (string) ($o['source'] ?? 'web')],
                ],
                'cta_url' => self::portalUrl('/admin/store/orders/' . (int) $o['id']),
            ]);
        } catch (\Throwable $e) {
            error_log('[StoreNotifier::orderPaid] ' . $e->getMessage());
        }
    }

    /** Seller accepted / packed a package → tell the customer. */
    public static function packageAdvanced(int $vendorOrderId, string $to): void
    {
        try {
            $key = ['accepted' => 'customer_order_accepted', 'packed' => 'customer_order_packed'][$to] ?? null;
            $vo = QueryBuilder::table('store_vendor_orders')->where('id', '=', $vendorOrderId)->first();
            $o = $vo !== null ? QueryBuilder::table('store_orders')->where('id', '=', (int) $vo['order_id'])->first() : null;
            $vendor = $vo !== null ? VendorService::find((int) $vo['vendor_id']) : null;
            if ($key === null || $o === null || $vendor === null) {
                return;
            }
            $lines = self::packageLines($vendorOrderId);
            self::send($key, (string) $o['contact_email'],
                ['name' => self::first((string) $o['contact_name']), 'order_no' => (string) $o['order_no'], 'seller' => (string) $vendor['display_name']], [
                    'facts' => [['Order number', (string) $o['order_no']], ['Sold by', (string) $vendor['display_name']]],
                    'packages' => $lines ? [['heading' => 'In this package', 'lines' => $lines]] : [],
                    'cta_url' => self::orderUrl((string) $o['order_no']),
                ]);
        } catch (\Throwable $e) {
            error_log('[StoreNotifier::packageAdvanced] ' . $e->getMessage());
        }
    }

    /** Unpaid order expired and its stock was released. */
    public static function orderExpired(int $orderId): void
    {
        try {
            $o = QueryBuilder::table('store_orders')->where('id', '=', $orderId)->first();
            if ($o === null) {
                return;
            }
            self::send('customer_payment_expired', (string) $o['contact_email'],
                ['name' => self::first((string) $o['contact_name']), 'order_no' => (string) $o['order_no'], 'amount' => self::rs((int) $o['grand_total_paise'])], [
                    'facts' => [['Order number', (string) $o['order_no']], ['Amount', self::rs((int) $o['grand_total_paise'])], ['Charged', 'Nothing']],
                    'cta_url' => self::storeUrl('/store/cart'),
                ]);
        } catch (\Throwable $e) {
            error_log('[StoreNotifier::orderExpired] ' . $e->getMessage());
        }
    }

    /** After a cancellation + refund: tell the customer; tell the seller(s) unless they did it themselves. */
    public static function itemsCancelled(int $orderId, string $refundNo, int $amount, string $actorType, string $reason): void
    {
        try {
            $o = OrderService::load($orderId);
            if ($o === null) {
                return;
            }
            $amt = self::rs($amount);
            $who = match ($actorType) {
                'customer' => 'as you requested',
                'vendor_user' => 'by the seller',
                'admin' => 'by the eClinicPro team',
                default => 'because the seller did not confirm the order in time',
            };

            // Which lines this refund covers (customer + seller both need them).
            $st = Database::connection()->prepare(
                'SELECT oi.vendor_id, vo.id AS vo_id, vo.sub_order_no, vo.status AS vo_status, oi.sku, oi.name, oi.variant_title, oi.image_path, ri.qty
                   FROM store_refunds rf
                   JOIN store_refund_items ri ON ri.refund_id = rf.id
                   JOIN store_order_items oi ON oi.id = ri.order_item_id
                   JOIN store_vendor_orders vo ON vo.id = oi.vendor_order_id
                  WHERE rf.refund_no = :r'
            );
            $st->execute(['r' => $refundNo]);
            $rows = $st->fetchAll();

            self::send('customer_refund', (string) $o['contact_email'], [
                'name' => self::first((string) $o['contact_name']), 'order_no' => (string) $o['order_no'],
                'amount' => $amt, 'refund_no' => $refundNo, 'who' => $who, 'reason' => $reason,
            ], [
                'facts' => [['Order number', (string) $o['order_no']], ['Refund amount', $amt], ['Refund reference', $refundNo], ['Reason', $reason]],
                'packages' => $rows ? [[
                    'heading' => 'Cancelled items',
                    'lines' => array_map(static fn ($r) => [
                        'name' => (string) $r['name'],
                        'meta' => trim(($r['variant_title'] ? $r['variant_title'] . ' · ' : '') . 'Qty ' . (int) $r['qty']),
                        'image' => $r['image_path'] ?? null,
                    ], $rows),
                ]] : [],
                'cta_url' => self::orderUrl((string) $o['order_no']),
            ]);

            if ($actorType !== 'vendor_user') {
                $sellerWho = match ($actorType) {
                    'customer' => 'as the customer requested',
                    'admin' => 'by the eClinicPro team',
                    default => 'because the order was not accepted in time',
                };
                $byVendor = [];
                foreach ($rows as $row) {
                    $vid = (int) $row['vendor_id'];
                    $byVendor[$vid]['sub'] = (string) $row['sub_order_no'];
                    $byVendor[$vid]['vo_id'] = (int) $row['vo_id'];
                    $byVendor[$vid]['whole'] = !in_array($row['vo_status'], ['new', 'accepted', 'packed', 'ready_to_ship'], true);
                    $byVendor[$vid]['lines'][] = [
                        'name' => (string) $row['name'],
                        'meta' => 'SKU ' . $row['sku'] . ($row['variant_title'] ? ' · ' . $row['variant_title'] : ''),
                        'amount' => '× ' . (int) $row['qty'],
                        'image' => $row['image_path'] ?? null,
                    ];
                }
                foreach ($byVendor as $vid => $v) {
                    $vendor = VendorService::find($vid);
                    if ($vendor === null) {
                        continue;
                    }
                    self::send($v['whole'] ? 'seller_order_cancelled' : 'seller_items_cancelled', (string) $vendor['email'],
                        self::sellerVars($vendor) + ['order_no' => $v['sub'], 'who' => $sellerWho, 'reason' => $reason], [
                            'facts' => [['Order', $v['sub']], ['Reason', $reason]],
                            'notice' => ['tone' => 'warn', 'text' => $v['whole'] ? 'Please do not ship this order.' : 'Ship only the remaining items in this order.'],
                            'packages' => [['heading' => 'Cancelled items', 'lines' => $v['lines']]],
                            'cta_url' => self::portalUrl('/vendor/orders/' . $v['vo_id']),
                        ]);
                }
            }
            if ($actorType === 'system') {
                self::sendTeam('team_order_autocancelled', ['order_no' => (string) $o['order_no'], 'amount' => $amt], [
                    'facts' => [['Order', (string) $o['order_no']], ['Refunded', $amt], ['Refund reference', $refundNo], ['Reason', $reason]],
                    'cta_url' => self::portalUrl('/admin/store/orders/' . (int) $o['id']),
                ]);
            }
        } catch (\Throwable $e) {
            error_log('[StoreNotifier::itemsCancelled] ' . $e->getMessage());
        }
    }

    /** Shipment milestones: customer hears "shipped"/"delivered"; the team hears about problems. */
    public static function shipmentUpdate(int $shipmentId, string $event): void
    {
        try {
            $s = QueryBuilder::table('store_shipments')->where('id', '=', $shipmentId)->first();
            if ($s === null) {
                return;
            }
            $o = QueryBuilder::table('store_orders')->where('id', '=', (int) $s['order_id'])->first();
            $vo = QueryBuilder::table('store_vendor_orders')->where('id', '=', (int) $s['vendor_order_id'])->first();
            $vendor = VendorService::find((int) $s['vendor_id']);
            if ($o === null || $vo === null) {
                return;
            }
            $from = (string) ($vendor['display_name'] ?? 'the seller');
            $awb = (string) ($s['awb_code'] ?? '');

            if (in_array($event, ['shipped', 'delivered'], true)) {
                $facts = [['Order number', (string) $o['order_no']], ['Sold by', $from]];
                if (!empty($s['courier_name'])) {
                    $facts[] = ['Courier', (string) $s['courier_name']];
                }
                if ($awb !== '') {
                    $facts[] = ['Tracking number', $awb];
                }
                $lines = self::packageLines((int) $vo['id']);
                $shipped = $event === 'shipped';
                self::send($shipped ? 'customer_shipped' : 'customer_delivered', (string) $o['contact_email'],
                    ['name' => self::first((string) $o['contact_name']), 'order_no' => (string) $o['order_no'], 'seller' => $from, 'awb' => $awb], [
                        'facts' => $facts,
                        'packages' => $lines ? [['heading' => 'In this package', 'lines' => $lines]] : [],
                        'cta_url' => $shipped && $awb !== ''
                            ? 'https://shiprocket.co/tracking/' . rawurlencode($awb)
                            : self::orderUrl((string) $o['order_no']),
                    ]);
            }
            if (in_array($event, ['ndr', 'pickup_failed', 'lost', 'damaged', 'rto_initiated', 'rto'], true)) {
                $label = ucfirst(str_replace('_', ' ', $event));
                self::sendTeam('team_shipment_problem', ['order_no' => (string) $vo['sub_order_no'], 'problem' => $label], [
                    'facts' => [['Package', (string) $vo['sub_order_no']], ['Seller', $from], ['Tracking number', $awb],
                        ['Last courier status', (string) ($s['last_raw_status'] ?? '')]],
                    'cta_url' => self::portalUrl('/admin/store/orders/' . (int) $o['id']),
                ]);
                if ($vendor !== null && in_array($event, ['pickup_failed', 'rto_initiated'], true)) {
                    self::send($event === 'pickup_failed' ? 'seller_pickup_failed' : 'seller_rto', (string) $vendor['email'],
                        self::sellerVars($vendor) + ['order_no' => (string) $vo['sub_order_no']], [
                            'facts' => [['Order', (string) $vo['sub_order_no']], ['Tracking number', $awb], ['Courier status', (string) ($s['last_raw_status'] ?? '')]],
                            'cta_url' => self::portalUrl('/vendor/orders/' . (int) $vo['id']),
                        ]);
                }
            }
        } catch (\Throwable $e) {
            error_log('[StoreNotifier::shipmentUpdate] ' . $e->getMessage());
        }
    }

    // ------------------------------------------------------------------
    // Returns
    // ------------------------------------------------------------------

    public static function returnEvent(int $returnId, string $event): void
    {
        try {
            $r = ReturnService::find($returnId, null);
            if ($r === null) {
                return;
            }
            $o = QueryBuilder::table('store_orders')->where('id', '=', (int) $r['order_id'])->first();
            $vendor = VendorService::find((int) $r['vendor_id']);
            $lines = array_map(static fn ($i) => [
                'name' => (string) $i['name'],
                'meta' => 'SKU ' . $i['sku'],
                'amount' => '× ' . (int) $i['qty'],
                'image' => $i['image_path'] ?? null,
            ], $r['items']);
            $reason = ReturnService::REASONS[$r['reason_code']] ?? (string) $r['reason_code'];
            $custEmail = (string) ($o['contact_email'] ?? '');
            $custVars = ['name' => self::first((string) $r['contact_name']), 'return_no' => (string) $r['return_no'], 'order_no' => (string) $r['order_no']];
            $orderUrl = self::orderUrl((string) $r['order_no']);
            $refund = self::rs((int) $r['refund_amount_paise']);

            switch ($event) {
                case 'requested':
                    if ($vendor !== null) {
                        $facts = [['Return', (string) $r['return_no']], ['Order', (string) $r['sub_order_no']], ['Reason', $reason]];
                        if (!empty($r['customer_note'])) {
                            $facts[] = ['Customer note', (string) $r['customer_note']];
                        }
                        self::send('seller_return_requested', (string) $vendor['email'],
                            self::sellerVars($vendor) + ['return_no' => (string) $r['return_no'], 'order_no' => (string) $r['sub_order_no'], 'reason' => $reason], [
                                'facts' => $facts,
                                'notice' => ['tone' => 'warn', 'text' => 'Please decide within 2 days.'],
                                'packages' => [['heading' => 'Items to be returned', 'lines' => $lines]],
                                'cta_url' => self::portalUrl('/vendor/returns/' . $returnId),
                            ]);
                    }
                    self::sendTeam('team_return_requested', ['return_no' => (string) $r['return_no'], 'seller' => (string) $r['vendor_name']], [
                        'facts' => [['Return', (string) $r['return_no']], ['Seller', (string) $r['vendor_name']], ['Reason', $reason]],
                        'packages' => [['heading' => 'Items', 'lines' => $lines]],
                        'cta_url' => self::portalUrl('/admin/store/returns/' . $returnId),
                    ]);
                    break;
                case 'approved':
                case 'pickup_scheduled':
                    self::send('customer_return_approved', $custEmail, $custVars + [
                        'pickup_text' => $event === 'pickup_scheduled'
                            ? 'A courier will collect the item from your delivery address. Please keep it packed with all tags and accessories.'
                            : 'We will contact you to arrange the pickup.',
                    ], [
                        'facts' => [['Return number', (string) $r['return_no']], ['Order number', (string) $r['order_no']], ['Reason', $reason]],
                        'packages' => [['heading' => 'Items being returned', 'lines' => $lines]],
                        'cta_url' => $orderUrl,
                    ]);
                    break;
                case 'rejected':
                    self::send('customer_return_rejected', $custEmail, $custVars + ['reason' => (string) ($r['vendor_note'] ?? '')], [
                        'facts' => [['Return number', (string) $r['return_no']], ['Seller\'s reason', (string) ($r['vendor_note'] ?? '')]],
                        'cta_url' => $orderUrl,
                    ]);
                    break;
                case 'qc_failed':
                    self::sendTeam('team_return_qc_failed', ['return_no' => (string) $r['return_no'], 'seller' => (string) $r['vendor_name']], [
                        'facts' => [['Return', (string) $r['return_no']], ['Seller', (string) $r['vendor_name']], ['Seller\'s note', (string) ($r['vendor_note'] ?? '')]],
                        'cta_url' => self::portalUrl('/admin/store/returns/' . $returnId),
                    ]);
                    break;
                case 'refunded':
                    self::send('customer_return_refunded', $custEmail, $custVars + ['amount' => $refund], [
                        'facts' => [['Return number', (string) $r['return_no']], ['Order number', (string) $r['order_no']], ['Refund amount', $refund]],
                        'cta_url' => $orderUrl,
                    ]);
                    if ($vendor !== null) {
                        self::send('seller_return_refunded', (string) $vendor['email'],
                            self::sellerVars($vendor) + ['return_no' => (string) $r['return_no'], 'order_no' => (string) $r['sub_order_no'], 'amount' => $refund], [
                                'facts' => [['Return', (string) $r['return_no']], ['Order', (string) $r['sub_order_no']], ['Refunded', $refund]],
                                'packages' => [['heading' => 'Returned items', 'lines' => $lines]],
                                'cta_url' => self::portalUrl('/vendor/payouts'),
                            ]);
                    }
                    break;
            }
        } catch (\Throwable $e) {
            error_log('[StoreNotifier::returnEvent] ' . $e->getMessage());
        }
    }

    // ------------------------------------------------------------------
    // Reviews
    // ------------------------------------------------------------------

    /** New review saved: seller hears about it if it's already live; otherwise the team moderates. */
    public static function reviewCreated(int $reviewId): void
    {
        try {
            $rv = self::review($reviewId);
            if ($rv === null) {
                return;
            }
            if ($rv['status'] === 'published') {
                self::sellerReviewEmail($rv);
            } else {
                self::sendTeam('team_review_pending', ['product' => (string) $rv['product_name'], 'rating' => (string) $rv['rating']], [
                    'facts' => [['Product', (string) $rv['product_name']], ['Seller', (string) $rv['vendor_name']], ['Rating', $rv['rating'] . ' / 5'],
                        ['Headline', (string) ($rv['title'] ?? '')]],
                    'cta_url' => self::portalUrl('/admin/store/reviews'),
                ]);
            }
        } catch (\Throwable $e) {
            error_log('[StoreNotifier::reviewCreated] ' . $e->getMessage());
        }
    }

    /** Admin published a moderated review → customer + seller. */
    public static function reviewPublished(int $reviewId): void
    {
        try {
            $rv = self::review($reviewId);
            if ($rv === null) {
                return;
            }
            self::send('customer_review_published', (string) ($rv['customer_email'] ?? ''),
                ['name' => self::first((string) ($rv['customer_name'] ?? '')), 'product' => (string) $rv['product_name']], [
                    'cta_url' => self::storeUrl('/store/p/' . $rv['product_slug']),
                ]);
            self::sellerReviewEmail($rv);
        } catch (\Throwable $e) {
            error_log('[StoreNotifier::reviewPublished] ' . $e->getMessage());
        }
    }

    /** Seller replied to a review → the customer who wrote it. */
    public static function reviewReplied(int $reviewId): void
    {
        try {
            $rv = self::review($reviewId);
            if ($rv === null || trim((string) ($rv['vendor_reply'] ?? '')) === '' || $rv['status'] !== 'published') {
                return;
            }
            self::send('customer_review_reply', (string) ($rv['customer_email'] ?? ''), [
                'name' => self::first((string) ($rv['customer_name'] ?? '')), 'product' => (string) $rv['product_name'],
                'seller' => (string) $rv['vendor_name'], 'reply' => (string) $rv['vendor_reply'],
            ], ['cta_url' => self::storeUrl('/store/p/' . $rv['product_slug'])]);
        } catch (\Throwable $e) {
            error_log('[StoreNotifier::reviewReplied] ' . $e->getMessage());
        }
    }

    private static function sellerReviewEmail(array $rv): void
    {
        $vendor = VendorService::find((int) $rv['vendor_id']);
        if ($vendor === null) {
            return;
        }
        $facts = [['Product', (string) $rv['product_name']], ['Rating', str_repeat('★', (int) $rv['rating']) . str_repeat('☆', 5 - (int) $rv['rating'])]];
        if (!empty($rv['title'])) {
            $facts[] = ['Headline', (string) $rv['title']];
        }
        if (!empty($rv['body'])) {
            $facts[] = ['Review', mb_strimwidth((string) $rv['body'], 0, 400, '…')];
        }
        self::send('seller_new_review', (string) $vendor['email'],
            self::sellerVars($vendor) + ['product' => (string) $rv['product_name'], 'rating' => (string) $rv['rating']], [
                'facts' => $facts,
                'cta_url' => self::portalUrl('/vendor/reviews'),
            ]);
    }

    /** @return array<string, mixed>|null review + product + seller + customer contact */
    private static function review(int $reviewId): ?array
    {
        $st = Database::connection()->prepare(
            'SELECT r.*, p.name AS product_name, p.slug AS product_slug, v.display_name AS vendor_name,
                    pi.email AS customer_email, pi.name AS customer_name
               FROM store_reviews r
               JOIN store_products p ON p.id = r.product_id
               JOIN store_vendors v ON v.id = r.vendor_id
               LEFT JOIN patient_identities pi ON pi.id = r.identity_id
              WHERE r.id = :id'
        );
        $st->execute(['id' => $reviewId]);

        return $st->fetch() ?: null;
    }

    // ------------------------------------------------------------------
    // Sellers: account, documents, products, terms
    // ------------------------------------------------------------------

    public static function vendorRegistered(int $vendorId): void
    {
        try {
            $v = VendorService::find($vendorId);
            if ($v === null) {
                return;
            }
            self::send('seller_welcome', (string) $v['email'], self::sellerVars($v), [
                'facts' => [['Seller account', (string) $v['display_name']], ['Login email', (string) $v['email']]],
                'cta_url' => self::portalUrl('/vendor/dashboard'),
            ]);
            self::sendTeam('team_seller_registered', ['seller' => (string) $v['display_name']], [
                'facts' => self::vendorFacts($v),
                'cta_url' => self::portalUrl('/admin/store/vendors/' . $vendorId),
            ]);
        } catch (\Throwable $e) {
            error_log('[StoreNotifier::vendorRegistered] ' . $e->getMessage());
        }
    }

    /** Seller submitted their account for review → tell the store team. */
    public static function vendorSubmitted(int $vendorId): void
    {
        try {
            $v = VendorService::find($vendorId);
            if ($v === null) {
                return;
            }
            self::sendTeam('team_seller_submitted', ['seller' => (string) $v['display_name']], [
                'facts' => self::vendorFacts($v),
                'cta_url' => self::portalUrl('/admin/store/vendors/' . $vendorId),
            ]);
        } catch (\Throwable $e) {
            error_log('[StoreNotifier::vendorSubmitted] ' . $e->getMessage());
        }
    }

    /** Admin approved / rejected / suspended / reactivated / closed a seller → tell the seller. */
    public static function vendorStatusChanged(int $vendorId, string $action, string $reason): void
    {
        try {
            $v = VendorService::find($vendorId);
            $key = ['approve' => 'seller_approved', 'reject' => 'seller_rejected', 'suspend' => 'seller_suspended',
                'reactivate' => 'seller_reactivated', 'close' => 'seller_closed'][$action] ?? null;
            if ($v === null || $key === null) {
                return;
            }
            $ctaPath = match ($action) {
                'approve' => '/vendor/products/new',
                'close' => null,                     // closed accounts get no portal button
                default => '/vendor/dashboard',
            };
            $extra = ['facts' => [['Seller account', (string) $v['display_name']]]];
            if (trim($reason) !== '') {
                $extra['notice'] = ['tone' => in_array($action, ['approve', 'reactivate'], true) ? 'ok' : 'warn', 'text' => 'Note from our team: ' . trim($reason)];
            }
            if ($ctaPath !== null) {
                $extra['cta_url'] = self::portalUrl($ctaPath);
            }
            self::send($key, (string) $v['email'], self::sellerVars($v) + ['reason' => trim($reason)], $extra);
        } catch (\Throwable $e) {
            error_log('[StoreNotifier::vendorStatusChanged] ' . $e->getMessage());
        }
    }

    public static function documentRejected(int $documentId): void
    {
        try {
            $d = QueryBuilder::table('store_vendor_documents')->where('id', '=', $documentId)->first();
            $v = $d !== null ? VendorService::find((int) $d['vendor_id']) : null;
            if ($d === null || $v === null) {
                return;
            }
            $label = VendorService::DOC_TYPES[$d['doc_type']] ?? ucfirst(str_replace('_', ' ', (string) $d['doc_type']));
            self::send('seller_document_rejected', (string) $v['email'],
                self::sellerVars($v) + ['document' => $label, 'reason' => (string) ($d['reject_reason'] ?? '')], [
                    'facts' => [['Document', $label], ['Reason', (string) ($d['reject_reason'] ?? '')]],
                    'cta_url' => self::portalUrl('/vendor/documents'),
                ]);
        } catch (\Throwable $e) {
            error_log('[StoreNotifier::documentRejected] ' . $e->getMessage());
        }
    }

    /** Seller sent a product for review → team. */
    public static function productSubmitted(int $productId): void
    {
        try {
            $p = QueryBuilder::table('store_products')->where('id', '=', $productId)->first();
            $v = $p !== null ? VendorService::find((int) $p['vendor_id']) : null;
            if ($p === null || $v === null) {
                return;
            }
            self::sendTeam('team_product_review', ['product' => (string) $p['name'], 'seller' => (string) $v['display_name']], [
                'facts' => [['Product', (string) $p['name']], ['Seller', (string) $v['display_name']], ['HSN', (string) ($p['hsn_code'] ?? '')]],
                'cta_url' => self::portalUrl('/admin/store/products/' . $productId),
            ]);
        } catch (\Throwable $e) {
            error_log('[StoreNotifier::productSubmitted] ' . $e->getMessage());
        }
    }

    /** Admin approved / sent back / took down a product → seller. */
    public static function productDecision(int $productId, string $action, string $note): void
    {
        try {
            $key = ['approve' => 'seller_product_approved', 'reject' => 'seller_product_rejected', 'disable' => 'seller_product_disabled'][$action] ?? null;
            $p = QueryBuilder::table('store_products')->where('id', '=', $productId)->first();
            $v = $p !== null ? VendorService::find((int) $p['vendor_id']) : null;
            if ($key === null || $p === null || $v === null) {
                return;
            }
            $extra = [
                'facts' => [['Product', (string) $p['name']]],
                'cta_url' => self::portalUrl($action === 'reject' ? '/vendor/products/' . $productId : '/vendor/products'),
            ];
            if (trim($note) !== '') {
                $extra['notice'] = ['tone' => 'warn', 'text' => ($action === 'reject' ? 'What to change: ' : 'Reason: ') . trim($note)];
            }
            self::send($key, (string) $v['email'], self::sellerVars($v) + ['product' => (string) $p['name'], 'reason' => trim($note)], $extra);
        } catch (\Throwable $e) {
            error_log('[StoreNotifier::productDecision] ' . $e->getMessage());
        }
    }

    /** New seller-terms version → every seller who still sells or is onboarding. */
    public static function termsUpdated(int $version): void
    {
        try {
            $rows = Database::connection()->query(
                "SELECT * FROM store_vendors WHERE status IN ('approved','pending_review','suspended')"
            )->fetchAll();
            foreach ($rows as $v) {
                self::send('seller_terms_updated', (string) $v['email'], self::sellerVars($v) + ['version' => (string) $version], [
                    'cta_url' => self::portalUrl('/vendor/terms'),
                ]);
            }
        } catch (\Throwable $e) {
            error_log('[StoreNotifier::termsUpdated] ' . $e->getMessage());
        }
    }

    /** "Forgot password?" link for a seller login. */
    public static function passwordReset(array $user, string $resetUrl): void
    {
        try {
            $v = VendorService::find((int) $user['vendor_id']);
            self::send('seller_password_reset', (string) $user['email'],
                ['name' => self::first((string) ($user['name'] ?? '')), 'seller' => (string) ($v['display_name'] ?? '')], [
                    'cta_url' => $resetUrl,
                ]);
        } catch (\Throwable $e) {
            error_log('[StoreNotifier::passwordReset] ' . $e->getMessage());
        }
    }

    /**
     * Once a day per seller: email the list of live variants at/below their low-stock level.
     * Deduped via store_notifications (event stock.low, created today).
     */
    public static function lowStockDigests(): int
    {
        $sent = 0;
        try {
            $pdo = Database::connection();
            $rows = $pdo->query(
                "SELECT sv.vendor_id, p.name, sv.title, sv.sku, GREATEST(CAST(sv.stock_qty AS SIGNED) - CAST(sv.reserved_qty AS SIGNED), 0) AS left_qty
                   FROM store_product_variants sv JOIN store_products p ON p.id = sv.product_id
                  WHERE sv.is_active = 1 AND p.status = 'live' AND p.deleted_at IS NULL
                    AND sv.stock_qty <= sv.reserved_qty + sv.low_stock_threshold
                  ORDER BY sv.vendor_id, left_qty"
            )->fetchAll();
            $byVendor = [];
            foreach ($rows as $r) {
                $byVendor[(int) $r['vendor_id']][] = $r;
            }
            foreach ($byVendor as $vid => $items) {
                $owner = self::owner($vid);
                if ($owner === null) {
                    continue;
                }
                $st = $pdo->prepare("SELECT 1 FROM store_notifications WHERE recipient_type = 'vendor_user' AND recipient_id = :u AND event = 'stock.low' AND created_at >= CURDATE() LIMIT 1");
                $st->execute(['u' => (int) $owner['id']]);
                if ($st->fetchColumn() !== false) {
                    continue;   // already told today
                }
                $vendor = VendorService::find($vid);
                if ($vendor === null) {
                    continue;
                }
                $shown = array_slice($items, 0, 40);
                self::send('seller_low_stock', (string) $vendor['email'], self::sellerVars($vendor) + ['count' => (string) count($items)], [
                    'packages' => [[
                        'heading' => count($items) . ' product(s)' . (count($items) > count($shown) ? ' (first ' . count($shown) . ' shown)' : ''),
                        'lines' => array_map(static fn ($i) => [
                            'name' => (string) $i['name'] . ($i['title'] ? ' (' . $i['title'] . ')' : ''),
                            'meta' => 'SKU ' . $i['sku'],
                            'amount' => (int) $i['left_qty'] === 0 ? 'Out of stock' : $i['left_qty'] . ' left',
                            'alert' => (int) $i['left_qty'] === 0,
                        ], $shown),
                    ]],
                    'cta_url' => self::portalUrl('/vendor/products'),
                ]);
                self::inApp('vendor_user', (int) $owner['id'], 'stock.low', count($items) . ' product(s) low on stock', 'Restock to avoid cancellations', '/vendor/dashboard');
                $sent++;
            }
        } catch (\Throwable $e) {
            error_log('[StoreNotifier::lowStockDigests] ' . $e->getMessage());
        }

        return $sent;
    }

    // ------------------------------------------------------------------
    // Payouts
    // ------------------------------------------------------------------

    /** Seller clicked "Request payout" → confirmation to the seller + alert to the team. */
    public static function payoutRequested(int $payoutId): void
    {
        try {
            [$p, $v] = self::payout($payoutId);
            if ($p === null || $v === null) {
                return;
            }
            $net = self::rs((int) $p['net_paise']);
            $req = QueryBuilder::table('store_payout_requests')->where('payout_id', '=', $payoutId)->first();
            $facts = [['Payout', (string) $p['payout_no']], ['Amount', $net], ['Bank account', 'ending ' . ($p['bank_last4'] ?? '')]];
            self::send('seller_payout_requested', (string) $v['email'], self::sellerVars($v) + ['amount' => $net, 'payout_no' => (string) $p['payout_no']], [
                'facts' => $facts,
                'cta_url' => self::portalUrl('/vendor/payouts'),
            ]);
            if (!empty($req['note'])) {
                $facts[] = ['Seller\'s note', (string) $req['note']];
            }
            self::sendTeam('team_payout_requested', ['seller' => (string) $v['display_name'], 'amount' => $net, 'payout_no' => (string) $p['payout_no']], [
                'facts' => array_merge([['Seller', (string) $v['display_name']]], $facts, [['IFSC', (string) ($p['bank_ifsc'] ?? '')]]),
                'cta_url' => self::portalUrl('/admin/store/payouts/' . $payoutId),
            ]);
        } catch (\Throwable $e) {
            error_log('[StoreNotifier::payoutRequested] ' . $e->getMessage());
        }
    }

    public static function payoutPaid(int $payoutId): void
    {
        try {
            [$p, $v] = self::payout($payoutId);
            if ($p === null || $v === null) {
                return;
            }
            $net = self::rs((int) $p['net_paise']);
            self::send('seller_payout_paid', (string) $v['email'],
                self::sellerVars($v) + ['amount' => $net, 'payout_no' => (string) $p['payout_no'], 'utr' => (string) ($p['reference'] ?? '')], [
                    'facts' => [['Payout', (string) $p['payout_no']], ['Amount', $net], ['Bank account', 'ending ' . ($p['bank_last4'] ?? '')],
                        ['Bank reference (UTR)', (string) ($p['reference'] ?? '')]],
                    'cta_url' => self::portalUrl('/vendor/payouts'),
                ]);
            $owner = self::owner((int) $v['id']);
            if ($owner !== null) {
                self::inApp('vendor_user', (int) $owner['id'], 'payout.paid', 'Payout ' . $p['payout_no'] . ' sent',
                    $net . ' · UTR ' . $p['reference'], '/vendor/payouts');
            }
        } catch (\Throwable $e) {
            error_log('[StoreNotifier::payoutPaid] ' . $e->getMessage());
        }
    }

    /** Bank transfer failed, or admin declined a seller's request. */
    public static function payoutNotPaid(int $payoutId, string $how, string $reason): void
    {
        try {
            [$p, $v] = self::payout($payoutId);
            if ($p === null || $v === null) {
                return;
            }
            $net = self::rs((int) $p['net_paise']);
            self::send($how === 'failed' ? 'seller_payout_failed' : 'seller_payout_declined', (string) $v['email'],
                self::sellerVars($v) + ['amount' => $net, 'payout_no' => (string) $p['payout_no'], 'reason' => $reason], [
                    'facts' => [['Payout', (string) $p['payout_no']], ['Amount', $net], ['Reason', $reason]],
                    'cta_url' => self::portalUrl($how === 'failed' ? '/vendor/bank' : '/vendor/payouts'),
                ]);
        } catch (\Throwable $e) {
            error_log('[StoreNotifier::payoutNotPaid] ' . $e->getMessage());
        }
    }

    /** @return array{0: ?array, 1: ?array} payout row + its seller */
    private static function payout(int $payoutId): array
    {
        $p = QueryBuilder::table('store_payouts')->where('id', '=', $payoutId)->first();

        return [$p, $p !== null ? VendorService::find((int) $p['vendor_id']) : null];
    }

    public static function inApp(string $recipientType, int $recipientId, string $event, string $title, string $body, string $link): void
    {
        try {
            QueryBuilder::table('store_notifications')->insert([
                'recipient_type' => $recipientType,
                'recipient_id' => $recipientId,
                'event' => $event,
                'title' => mb_substr($title, 0, 190),
                'body' => mb_substr($body, 0, 500),
                'link' => $link,
            ]);
        } catch (\Throwable $e) {
            error_log('[StoreNotifier::inApp] ' . $e->getMessage());
        }
    }

    // ------------------------------------------------------------------
    // Admin: preview + test (sample data)
    // ------------------------------------------------------------------

    /** Full HTML of one email with sample data (admin preview). */
    public static function preview(string $key): string
    {
        [$email] = self::build($key, StoreEmailTemplates::sampleVars($key), self::sampleExtras($key));

        return $email;
    }

    /** @return array{ok: bool, error?: string} */
    public static function sendTest(string $key, string $to): array
    {
        if (!StoreEmailTemplates::isKnown($key) || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'error' => 'Choose an email and enter a valid address.'];
        }
        [$html, $subject] = self::build($key, StoreEmailTemplates::sampleVars($key), self::sampleExtras($key));
        $res = SmtpMailService::send($to, '[Test] ' . $subject, $html,
            StoreEmailTemplates::fromEmail(), StoreEmailTemplates::fromName(), StoreEmailTemplates::replyTo(), true);
        StoreEmailTemplates::log($key, $to, '[Test] ' . $subject, $res['ok'] ? 'sent' : 'failed', $res['ok'] ? null : (string) ($res['error'] ?? ''));

        return $res['ok'] ? ['ok' => true] : ['ok' => false, 'error' => (string) ($res['error'] ?? 'Send failed')];
    }

    /** Generic automatic parts so previews/tests look like the real thing. */
    private static function sampleExtras(string $key): array
    {
        $group = StoreEmailTemplates::registry()[$key]['group'] ?? 'customer';
        $v = StoreEmailTemplates::sampleVars($key);
        $facts = [];
        foreach (['order_no' => 'Order number', 'return_no' => 'Return number', 'payout_no' => 'Payout', 'amount' => 'Amount',
            'product' => 'Product', 'document' => 'Document', 'reason' => 'Reason', 'accept_by' => 'Accept by', 'utr' => 'Bank reference (UTR)'] as $k => $label) {
            if (($v[$k] ?? '') !== '') {
                $facts[] = [$label, (string) $v[$k]];
            }
        }
        $withItems = preg_match('/order|shipped|delivered|refund|return|cancel|low_stock/', $key) === 1;

        return [
            'facts' => $facts,
            'packages' => $withItems ? [[
                'heading' => $group === 'customer' ? 'Sold by Wellness Mart' : 'Items',
                'lines' => [
                    ['name' => 'Vitamin D3 60K (4 capsules)', 'meta' => 'Qty 2', 'amount' => '₹398', 'image' => null],
                    ['name' => 'Digital thermometer', 'meta' => 'Qty 1', 'amount' => '₹851', 'image' => null],
                ],
            ]] : [],
            'cta_url' => $group === 'customer' ? self::storeUrl('/store/') : self::portalUrl($group === 'team' ? '/admin/store/dashboard' : '/vendor/dashboard'),
        ];
    }

    // ------------------------------------------------------------------
    // Sending
    // ------------------------------------------------------------------

    /**
     * @param array<string, string> $vars placeholder values for the wording
     * @param array<string, mixed> $extra automatic parts (see render()) + cta_url
     */
    private static function send(string $key, string $to, array $vars, array $extra = []): void
    {
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return;
        }
        $tpl = StoreEmailTemplates::resolve($key, $vars);
        if (!$tpl['enabled']) {
            StoreEmailTemplates::log($key, $to, $tpl['subject'], 'disabled');

            return;
        }
        [$html, $subject] = self::build($key, $vars, $extra, $tpl);
        // Customers and sellers can reply to a person; team mail needs no Reply-To.
        $res = SmtpMailService::send($to, $subject, $html, StoreEmailTemplates::fromEmail(), StoreEmailTemplates::fromName(),
            $tpl['group'] === 'team' ? null : StoreEmailTemplates::replyTo(), true);
        StoreEmailTemplates::log($key, $to, $subject, $res['ok'] ? 'sent' : 'failed', $res['ok'] ? null : (string) ($res['error'] ?? ''));
        if (!$res['ok']) {
            error_log('[StoreNotifier] ' . $key . ' to ' . $to . ' failed: ' . ($res['error'] ?? ''));
        }
    }

    /** Team alerts go to every address in Store → Email settings. */
    private static function sendTeam(string $key, array $vars, array $extra): void
    {
        foreach (StoreEmailTemplates::teamEmails() as $to) {
            self::send($key, $to, $vars, $extra);
        }
    }

    /**
     * Wording (template) + automatic parts → [html, subject].
     *
     * @param array{enabled: bool, group: string, subject: string, title: string, paragraphs: list<string>, cta: string, note: list<string>}|null $tpl
     * @return array{0: string, 1: string}
     */
    private static function build(string $key, array $vars, array $extra, ?array $tpl = null): array
    {
        $tpl ??= StoreEmailTemplates::resolve($key, $vars);
        $email = $extra + [
            'audience' => $tpl['group'],
            'title' => $tpl['title'],
            'paragraphs' => $tpl['paragraphs'],
            'preheader' => $tpl['paragraphs'][1] ?? $tpl['title'],
        ];
        $email['after'] = array_merge($tpl['note'], $extra['after'] ?? []);
        if ($tpl['cta'] !== '' && !empty($extra['cta_url'])) {
            $email['cta'] = ['label' => $tpl['cta'], 'url' => (string) $extra['cta_url']];
        }

        return [self::render($email), $tpl['subject']];
    }

    /**
     * One layout for every store email (table-based + inline styles, which is what
     * email clients support).
     *
     * @param array{
     *   audience?: string, preheader?: string, title?: string,
     *   paragraphs?: list<string>, facts?: list<array{0: string, 1: string}>,
     *   notice?: array{tone: string, text: string},
     *   packages?: list<array{heading: string, sub?: string, lines: list<array<string, mixed>>}>,
     *   totals?: list<array{0: string, 1: string, 2?: bool}>, totals_note?: string,
     *   address_label?: string, address?: list<string>,
     *   after?: list<string>, cta?: array{label: string, url: string}
     * } $e
     */
    private static function render(array $e): string
    {
        $h = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
        $ink = '#0f172a';
        $muted = '#64748b';
        $line = '#e2e8f0';
        $green = '#047857';
        $font = "-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif";
        $p = static fn (string $s): string => '<p style="margin:0 0 14px;font-size:15px;line-height:1.6;color:' . $ink . ';">' . nl2br($h($s)) . '</p>';
        $audience = (string) ($e['audience'] ?? 'customer');

        $body = '';
        if (!empty($e['title'])) {
            $body .= '<h1 style="margin:0 0 18px;font-size:22px;line-height:1.3;font-weight:700;color:' . $ink . ';">' . $h((string) $e['title']) . '</h1>';
        }
        foreach ($e['paragraphs'] ?? [] as $para) {
            $body .= $p((string) $para);
        }

        if (!empty($e['notice']['text'])) {
            $warn = ($e['notice']['tone'] ?? '') === 'warn';
            $body .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:4px 0 18px;"><tr>'
                . '<td style="padding:12px 14px;border-radius:8px;font-size:14px;line-height:1.5;'
                . ($warn ? 'background:#fffbeb;border:1px solid #fde68a;color:#92400e;' : 'background:#ecfdf5;border:1px solid #a7f3d0;color:#065f46;')
                . '">' . $h((string) $e['notice']['text']) . '</td></tr></table>';
        }

        $facts = array_values(array_filter($e['facts'] ?? [], static fn ($f) => (string) ($f[1] ?? '') !== ''));
        if ($facts) {
            $rows = '';
            foreach ($facts as $i => $f) {
                $top = $i > 0 ? 'border-top:1px solid ' . $line . ';' : '';
                $rows .= '<tr><td style="padding:9px 14px;font-size:13px;color:' . $muted . ';' . $top . 'width:36%;vertical-align:top;">' . $h((string) $f[0]) . '</td>'
                    . '<td style="padding:9px 14px;font-size:14px;color:' . $ink . ';font-weight:600;' . $top . 'vertical-align:top;">' . nl2br($h((string) $f[1])) . '</td></tr>';
            }
            $body .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:6px 0 20px;border:1px solid ' . $line . ';border-radius:8px;background:#f8fafc;">' . $rows . '</table>';
        }

        foreach ($e['packages'] ?? [] as $pkg) {
            if (empty($pkg['lines'])) {
                continue;
            }
            $body .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 18px;border:1px solid ' . $line . ';border-radius:8px;">'
                . '<tr><td colspan="3" style="padding:10px 14px;background:#f8fafc;border-bottom:1px solid ' . $line . ';border-radius:8px 8px 0 0;">'
                . '<span style="font-size:14px;font-weight:700;color:' . $ink . ';">' . $h((string) $pkg['heading']) . '</span>'
                . (!empty($pkg['sub']) ? '<span style="float:right;font-size:13px;color:' . $muted . ';">' . $h((string) $pkg['sub']) . '</span>' : '')
                . '</td></tr>';
            foreach ($pkg['lines'] as $i => $l) {
                $top = $i > 0 ? 'border-top:1px solid ' . $line . ';' : '';
                $img = self::imageUrl($l['image'] ?? null);
                $body .= '<tr>'
                    . '<td style="padding:10px 0 10px 14px;width:52px;vertical-align:top;' . $top . '">'
                    . ($img !== null
                        ? '<img src="' . $h($img) . '" width="44" height="44" alt="" style="display:block;width:44px;height:44px;object-fit:cover;border-radius:6px;border:1px solid ' . $line . ';">'
                        : '<div style="width:44px;height:44px;border-radius:6px;background:#f1f5f9;"></div>')
                    . '</td>'
                    . '<td style="padding:10px 10px;vertical-align:top;' . $top . '">'
                    . '<div style="font-size:14px;line-height:1.4;color:' . $ink . ';font-weight:600;">' . $h((string) $l['name']) . '</div>'
                    . (!empty($l['meta']) ? '<div style="font-size:13px;line-height:1.4;color:' . $muted . ';margin-top:2px;">' . $h((string) $l['meta']) . '</div>' : '')
                    . '</td>'
                    . '<td style="padding:10px 14px 10px 0;vertical-align:top;text-align:right;white-space:nowrap;font-size:14px;'
                    . (!empty($l['alert']) ? 'color:#b91c1c;font-weight:700;' : 'color:' . $ink . ';') . $top . '">'
                    . $h((string) ($l['amount'] ?? '')) . '</td></tr>';
            }
            $body .= '</table>';
        }

        if (!empty($e['totals'])) {
            $rows = '';
            foreach ($e['totals'] as $t) {
                $strong = !empty($t[2]);
                $style = $strong
                    ? 'padding:10px 0 0;font-size:16px;font-weight:700;color:' . $ink . ';border-top:1px solid ' . $line . ';'
                    : 'padding:3px 0;font-size:14px;color:' . $muted . ';';
                $rows .= '<tr><td style="' . $style . '">' . $h((string) $t[0]) . '</td><td style="' . $style . 'text-align:right;">' . $h((string) $t[1]) . '</td></tr>';
            }
            $body .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 6px;">' . $rows . '</table>';
            if (!empty($e['totals_note'])) {
                $body .= '<p style="margin:0 0 20px;font-size:12px;color:' . $muted . ';text-align:right;">' . $h((string) $e['totals_note']) . '</p>';
            }
        }

        if (!empty($e['address'])) {
            $body .= '<p style="margin:8px 0 4px;font-size:12px;font-weight:700;letter-spacing:.04em;text-transform:uppercase;color:' . $muted . ';">'
                . $h((string) ($e['address_label'] ?? 'Address')) . '</p>'
                . '<p style="margin:0 0 20px;font-size:14px;line-height:1.55;color:' . $ink . ';">'
                . implode('<br>', array_map($h, array_map('strval', $e['address']))) . '</p>';
        }

        foreach ($e['after'] ?? [] as $para) {
            $body .= $p((string) $para);
        }

        if (!empty($e['cta']['url']) && !empty($e['cta']['label'])) {
            $body .= '<table role="presentation" cellpadding="0" cellspacing="0" style="margin:10px 0 4px;"><tr>'
                . '<td style="border-radius:8px;background:' . $green . ';">'
                . '<a href="' . $h((string) $e['cta']['url']) . '" target="_blank" '
                . 'style="display:inline-block;padding:12px 26px;font-size:15px;font-weight:700;color:#ffffff;text-decoration:none;border-radius:8px;">'
                . $h((string) $e['cta']['label']) . '</a></td></tr></table>';
        }

        $help = StoreEmailTemplates::replyTo();
        $footer = match ($audience) {
            'seller' => 'You are receiving this because you sell on eClinicPro Store. Questions? Reply to this email or write to '
                . '<a href="mailto:' . $h($help) . '" style="color:' . $muted . ';">' . $h($help) . '</a>.',
            'team' => 'Internal eClinicPro Store notification.',
            default => 'Questions about your order? Reply to this email or write to '
                . '<a href="mailto:' . $h($help) . '" style="color:' . $muted . ';">' . $h($help) . '</a> with your order number.<br>'
                . 'Products are sold by the sellers named in this email.',
        };
        $logo = $_ENV['EMAIL_LOGO_URL'] ?? 'https://eclinicpro.com/assets/img/logos/logo.png';
        $preheader = (string) ($e['preheader'] ?? '');

        return '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">'
            . '<meta name="viewport" content="width=device-width,initial-scale=1"><title>' . $h((string) ($e['title'] ?? 'eClinicPro Store')) . '</title></head>'
            . '<body style="margin:0;padding:0;background:#f1f5f9;">'
            // Hidden preview text shown next to the subject in the inbox list.
            . ($preheader !== '' ? '<div style="display:none;max-height:0;overflow:hidden;opacity:0;">' . $h($preheader) . '</div>' : '')
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f9;padding:24px 12px;"><tr><td align="center">'
            . '<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#ffffff;border:1px solid ' . $line . ';border-radius:12px;overflow:hidden;font-family:' . $font . ';">'
            . '<tr><td style="padding:20px 28px;border-bottom:1px solid ' . $line . ';">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr>'
            . '<td><img src="' . $h($logo) . '" alt="eClinicPro" height="40" style="height:40px;width:auto;display:block;border:0;"></td>'
            . '<td style="text-align:right;font-size:12px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:' . $green . ';">'
            . ($audience === 'seller' ? 'Seller portal' : ($audience === 'team' ? 'Store admin' : 'Store')) . '</td>'
            . '</tr></table></td></tr>'
            . '<tr><td style="padding:28px;">' . $body . '</td></tr>'
            . '<tr><td style="padding:18px 28px;border-top:1px solid ' . $line . ';background:#f8fafc;">'
            . '<p style="margin:0 0 6px;font-size:12px;line-height:1.6;color:' . $muted . ';">' . $footer . '</p>'
            . '<p style="margin:0;font-size:12px;line-height:1.6;color:' . $muted . ';">© ' . date('Y') . ' eClinicPro Store · operated by Silver Webbuzz Pvt Ltd</p>'
            . '</td></tr></table></td></tr></table></body></html>';
    }

    // ------------------------------------------------------------------
    // Small helpers
    // ------------------------------------------------------------------

    private static function rs(int $paise): string
    {
        return '₹' . ProductService::rupees($paise);
    }

    /** "1 Oct, 4:42 pm" */
    private static function when(string $datetime): string
    {
        $t = strtotime($datetime);

        return $t ? date('j M, g:i a', $t) : '';
    }

    private static function first(string $name): string
    {
        $first = trim((string) strtok(trim($name), ' '));

        return $first !== '' ? $first : 'there';
    }

    /** @return array{name: string, seller: string} */
    private static function sellerVars(array $vendor): array
    {
        return ['name' => self::first((string) ($vendor['contact_name'] ?? $vendor['display_name'] ?? '')), 'seller' => (string) ($vendor['display_name'] ?? '')];
    }

    /** @return list<array{0: string, 1: string}> */
    private static function vendorFacts(array $v): array
    {
        return [
            ['Seller', (string) $v['display_name']],
            ['Legal name', (string) ($v['legal_name'] ?? '')],
            ['GSTIN', (string) ($v['gstin'] ?? '')],
            ['Contact', trim(($v['contact_name'] ?? '') . ' · ' . ($v['email'] ?? '') . ' · ' . ($v['phone'] ?? ''), ' ·')],
        ];
    }

    /** @return list<array<string, mixed>> */
    private static function sellerLines(array $items): array
    {
        return array_map(static fn ($it) => [
            'name' => (string) $it['name'],
            'meta' => trim('SKU ' . $it['sku'] . ($it['variant_title'] ? ' · ' . $it['variant_title'] : '')),
            'amount' => '× ' . (int) $it['qty'],
            'image' => $it['image_path'] ?? null,
        ], $items);
    }

    private static function owner(int $vendorId): ?array
    {
        return QueryBuilder::table('store_vendor_users')->where('vendor_id', '=', $vendorId)->where('role', '=', 'owner')->first();
    }

    /** @return list<string> */
    private static function addressLines(array $a): array
    {
        return array_values(array_filter([
            trim(($a['name'] ?? '') . (!empty($a['phone']) ? ' · ' . $a['phone'] : '')),
            trim(($a['line1'] ?? '') . (!empty($a['line2']) ? ', ' . $a['line2'] : '')),
            !empty($a['landmark']) ? 'Near ' . $a['landmark'] : '',
            trim(($a['city'] ?? '') . ', ' . ($a['state'] ?? '') . ' ' . ($a['pincode'] ?? ''), ' ,'),
        ], static fn ($s) => $s !== ''));
    }

    /** Remaining (not cancelled) lines of one package, for shipment emails. @return list<array<string, mixed>> */
    private static function packageLines(int $vendorOrderId): array
    {
        $st = Database::connection()->prepare(
            'SELECT name, variant_title, image_path, qty - qty_cancelled AS q FROM store_order_items
              WHERE vendor_order_id = :v AND qty > qty_cancelled ORDER BY id'
        );
        $st->execute(['v' => $vendorOrderId]);

        return array_map(static fn ($r) => [
            'name' => (string) $r['name'],
            'meta' => trim(($r['variant_title'] ? $r['variant_title'] . ' · ' : '') . 'Qty ' . (int) $r['q']),
            'image' => $r['image_path'] ?? null,
        ], $st->fetchAll());
    }

    /** Product photos are uploaded on the portal domain; email needs absolute URLs. */
    private static function imageUrl(mixed $path): ?string
    {
        $path = trim((string) $path);
        if ($path === '') {
            return null;
        }

        return str_starts_with($path, 'http') ? $path : self::portalUrl('/' . ltrim($path, '/'));
    }

    private static function orderUrl(string $orderNo): string
    {
        return self::storeUrl('/store/order/' . rawurlencode($orderNo));
    }

    private static function storeUrl(string $path): string
    {
        return rtrim((string) ($_ENV['STORE_BASE_URL'] ?? 'https://eclinicpro.com'), '/') . $path;
    }

    private static function portalUrl(string $path): string
    {
        return rtrim((string) ($_ENV['APP_URL'] ?? 'https://app.eclinicpro.com'), '/') . $path;
    }
}
