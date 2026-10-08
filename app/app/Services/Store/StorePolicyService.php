<?php

declare(strict_types=1);

namespace App\Services\Store;

use App\Core\Database;
use App\Core\QueryBuilder;

/**
 * Admin-editable policy pages (seller rules & terms). Every save is a new version;
 * sellers must accept the current version (banner in the seller portal until they do).
 *
 * Body format: simple markdown — "## Heading", "### Sub-heading", "- bullet", "1. step",
 * "**bold**", blank line = new paragraph. {{tokens}} are filled from live settings so the
 * numbers in the terms always match what the system actually does.
 */
final class StorePolicyService
{
    public const PAGES = [
        'seller_terms' => 'Seller rules & terms',
    ];

    /**
     * Numbers the seller terms promise, editable in Store settings → "Seller terms & payouts".
     * setting key => [label, min, max, default, help]. Each is also a {{token}} (key minus "store_").
     */
    public const TERMS_SETTINGS = [
        'store_return_window_hours' => ['Return window (hours from delivery)', 0, 720, 24,
            'Same for every product and seller. 24 = 1 day. Seller earnings become payable when it ends.'],
        'store_vendor_accept_sla_hours' => ['Seller must accept a new order within (hours)', 6, 168, 48,
            'Orders not accepted in time are cancelled and refunded automatically.'],
        'store_charge_dispute_days' => ['Seller can dispute a deduction within (days)', 1, 60, 7, ''],
        'store_charge_dispute_response_days' => ['eClinicPro answers a dispute within (days)', 1, 30, 7, ''],
        'store_terms_notice_days' => ['Notice before new seller terms apply (days)', 0, 90, 15,
            'A new version takes effect this many days after you publish it (you can mark a legal change as urgent).'],
        'store_suspension_notice_days' => ['Days a seller gets to respond before suspension', 0, 30, 7,
            'Not for fake or unsafe products or fraud: those are suspended at once.'],
        'store_final_settlement_days' => ['Final payout after a seller closes (days)', 1, 90, 30, 'Counted from the end of the last return window.'],
        'store_grievance_ack_hours' => ['Acknowledge a seller grievance within (hours)', 1, 168, 48, ''],
        'store_grievance_resolve_days' => ['Resolve a seller grievance within (days)', 1, 90, 30, ''],
    ];

    public const WEEKDAYS = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'];

    /** @return array{title: string, body: string, version: int, updated_at: ?string, effective_at: ?string} */
    public static function get(string $slug): array
    {
        $row = QueryBuilder::table('store_policy_pages')->where('slug', '=', $slug)->first();
        if ($row === null) {
            // First use: store the default text as version 1 so acceptances point at real text.
            $title = self::PAGES[$slug] ?? $slug;
            $body = self::defaultBody($slug);
            $pdo = Database::connection();
            $pdo->prepare('INSERT IGNORE INTO store_policy_pages (slug, title, body, version) VALUES (:s, :t, :b, 1)')
                ->execute(['s' => $slug, 't' => $title, 'b' => $body]);
            $pdo->prepare('INSERT IGNORE INTO store_policy_versions (slug, version, title, body) VALUES (:s, 1, :t, :b)')
                ->execute(['s' => $slug, 't' => $title, 'b' => $body]);
            $row = QueryBuilder::table('store_policy_pages')->where('slug', '=', $slug)->first()
                ?? ['title' => $title, 'body' => $body, 'version' => 1, 'updated_at' => null];
        }

        return ['title' => (string) $row['title'], 'body' => (string) $row['body'], 'version' => (int) $row['version'],
            'updated_at' => $row['updated_at'] ?? null, 'effective_at' => $row['effective_at'] ?? null];
    }

    /**
     * Save a new version. It takes effect after store_terms_notice_days (15) so sellers get
     * notice, unless $urgent (a change the law requires), which applies at once.
     *
     * @return array{ok: bool, error?: string, version?: int, effective_at?: string}
     */
    public static function save(string $slug, string $title, string $body, int $adminId, bool $urgent = false): array
    {
        if (!isset(self::PAGES[$slug])) {
            return ['ok' => false, 'error' => 'Unknown page.'];
        }
        $title = mb_substr(trim($title), 0, 190);
        $body = str_replace("\r\n", "\n", trim($body));
        if ($title === '' || mb_strlen($body) < 50) {
            return ['ok' => false, 'error' => 'Title and text are required.'];
        }
        $cur = self::get($slug);
        if ($cur['title'] === $title && $cur['body'] === $body) {
            return ['ok' => false, 'error' => 'Nothing changed.'];
        }
        $v = $cur['version'] + 1;
        $notice = $urgent ? 0 : max(0, StoreSettings::int('store_terms_notice_days', 15));
        $effective = date('Y-m-d H:i:s', time() + $notice * 86400);
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $pdo->prepare('UPDATE store_policy_pages SET title = :t, body = :b, version = :v, effective_at = :ef, updated_by = :u WHERE slug = :s')
                ->execute(['t' => $title, 'b' => $body, 'v' => $v, 'ef' => $effective, 'u' => $adminId, 's' => $slug]);
            $pdo->prepare('INSERT INTO store_policy_versions (slug, version, title, body, effective_at, created_by) VALUES (:s, :v, :t, :b, :ef, :u)')
                ->execute(['s' => $slug, 'v' => $v, 't' => $title, 'b' => $body, 'ef' => $effective, 'u' => $adminId]);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            error_log('[StorePolicy::save] ' . $e->getMessage());

            return ['ok' => false, 'error' => str_contains($e->getMessage(), 'effective_at')
                ? 'Import app/database/patches/2026_10_08_store_seller_trust.sql first.' : 'Could not save.'];
        }
        StoreAudit::log('policy.save', 'policy', null, ['slug' => $slug, 'version' => $cur['version']],
            ['slug' => $slug, 'version' => $v, 'effective_at' => $effective, 'urgent' => $urgent]);
        if ($slug === 'seller_terms') {
            StoreNotifier::termsUpdated($v, $effective);   // sellers must re-accept the new version
        }

        return ['ok' => true, 'version' => $v, 'effective_at' => $effective];
    }

    /** @return list<array<string, mixed>> */
    public static function versions(string $slug): array
    {
        $st = Database::connection()->prepare('SELECT * FROM store_policy_versions WHERE slug = :s ORDER BY version DESC LIMIT 50');
        $st->execute(['s' => $slug]);

        return $st->fetchAll();
    }

    public static function acceptedVersion(int $vendorId, string $slug): int
    {
        $st = Database::connection()->prepare('SELECT MAX(version) FROM store_policy_acceptances WHERE vendor_id = :v AND slug = :s');
        $st->execute(['v' => $vendorId, 's' => $slug]);

        return (int) $st->fetchColumn();
    }

    /** True when the seller hasn't accepted the current seller terms. Never throws (layout uses it). */
    public static function needsAcceptance(int $vendorId): bool
    {
        try {
            return self::acceptedVersion($vendorId, 'seller_terms') < self::get('seller_terms')['version'];
        } catch (\Throwable) {
            return false;   // patch not imported yet
        }
    }

    /** @return array{ok: bool, error?: string} */
    public static function accept(int $vendorId, int $userId, string $slug, int $version, string $ip): array
    {
        $cur = self::get($slug);
        if ($version !== $cur['version']) {
            return ['ok' => false, 'error' => 'The terms were updated while you were reading. Please review the latest version.'];
        }
        $packed = @inet_pton($ip);
        Database::connection()->prepare(
            'INSERT IGNORE INTO store_policy_acceptances (vendor_id, vendor_user_id, slug, version, ip) VALUES (:v, :u, :s, :n, :ip)'
        )->execute(['v' => $vendorId, 'u' => $userId, 's' => $slug, 'n' => $version, 'ip' => $packed !== false ? $packed : null]);
        StoreAudit::log('policy.accept', 'vendor', $vendorId, null, ['slug' => $slug, 'version' => $version, 'user' => $userId]);

        return ['ok' => true];
    }

    /**
     * The seller's copy of the terms they last accepted, with the acceptance record
     * (who, when, IP), for the printable certificate. Null if they never accepted.
     *
     * @return array{acceptance: array<string, mixed>, version: array<string, mixed>, html: string}|null
     */
    public static function certificate(int $vendorId, string $slug = 'seller_terms'): ?array
    {
        $pdo = Database::connection();
        $st = $pdo->prepare(
            'SELECT a.*, u.name AS user_name, u.email AS user_email
               FROM store_policy_acceptances a LEFT JOIN store_vendor_users u ON u.id = a.vendor_user_id
              WHERE a.vendor_id = :v AND a.slug = :s ORDER BY a.version DESC LIMIT 1'
        );
        $st->execute(['v' => $vendorId, 's' => $slug]);
        $acc = $st->fetch();
        if (!$acc) {
            return null;
        }
        $st = $pdo->prepare('SELECT * FROM store_policy_versions WHERE slug = :s AND version = :n');
        $st->execute(['s' => $slug, 'n' => (int) $acc['version']]);
        $ver = $st->fetch();
        if (!$ver) {
            return null;
        }
        $ip = !empty($acc['ip']) ? @inet_ntop((string) $acc['ip']) : false;
        $acc['ip_text'] = $ip !== false ? $ip : '';

        return ['acceptance' => $acc, 'version' => $ver, 'html' => self::render((string) $ver['body'])];
    }

    /** @return array{current: int, accepted: int, sellers: int} how many approved sellers accepted the current version */
    public static function stats(string $slug): array
    {
        $cur = self::get($slug)['version'];
        $pdo = Database::connection();
        $sellers = (int) $pdo->query("SELECT COUNT(*) FROM store_vendors WHERE status IN ('approved','pending_review','suspended')")->fetchColumn();
        $st = $pdo->prepare(
            "SELECT COUNT(DISTINCT a.vendor_id) FROM store_policy_acceptances a JOIN store_vendors v ON v.id = a.vendor_id
              WHERE a.slug = :s AND a.version = :n AND v.status IN ('approved','pending_review','suspended')"
        );
        $st->execute(['s' => $slug, 'n' => $cur]);

        return ['current' => $cur, 'accepted' => (int) $st->fetchColumn(), 'sellers' => $sellers];
    }

    /** Live numbers the text can reference as {{token}}. @return array<string, string> */
    public static function tokens(): array
    {
        $fees = SellerFeeService::config();
        $rs = static fn (int $p): string => '₹' . ProductService::rupees($p);

        $numbers = [];
        foreach (self::TERMS_SETTINGS as $key => [, $min, $max, $default]) {
            $numbers[substr($key, 6)] = (string) max($min, min($max, StoreSettings::int($key, $default)));
        }

        return $numbers + [
            'return_window' => ReturnService::windowLabel(),
            'payout_day' => self::WEEKDAYS[max(1, min(7, StoreSettings::int('store_payout_weekday', 2)))],
            'commission_pct' => rtrim(rtrim(number_format(StoreSettings::int('store_default_commission_bp', 1000) / 100, 2), '0'), '.') . '%',
            'accept_hours' => (string) StoreSettings::int('store_vendor_accept_sla_hours', 48),
            'return_days' => (string) (int) ceil(ReturnService::windowHours() / 24),   // older versions of the text
            'min_payout' => $rs(StoreSettings::int('store_min_payout_paise', 10000)),
            'shipping_fee' => $rs($fees['delivery_fee']),
            'free_shipping_above' => $fees['free_above'] !== null ? $rs($fees['free_above']) : 'never',
            'courier_first_500g' => $rs($fees['courier_base']),
            'courier_extra_500g' => $rs($fees['courier_addl']),
            'payment_window' => (string) StoreSettings::int('store_payment_window_minutes', 30),
            'platform_legal_name' => StoreSettings::get('store_platform_legal_name') ?: 'eClinicPro',
            'platform_address' => StoreSettings::get('store_platform_address') ?: 'its registered office',
            'legal_city' => StoreSettings::get('store_legal_jurisdiction_city') ?: 'the city of eClinicPro\'s registered office',
            'grievance_email' => StoreSettings::get('store_grievance_email') ?: 'hello@eclinicpro.com',
        ];
    }

    /** Markdown-lite → safe HTML (all text is escaped first). */
    public static function render(string $body): string
    {
        $tokens = self::tokens();
        $body = (string) preg_replace_callback('/\{\{\s*([a-z_]+)\s*\}\}/', static fn ($m) => $tokens[$m[1]] ?? $m[0], $body);
        $inline = static function (string $t): string {
            $t = htmlspecialchars($t, ENT_QUOTES, 'UTF-8');

            return (string) preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $t);
        };
        $html = '';
        $list = null;   // 'ul' | 'ol'
        $para = [];
        $flushPara = static function () use (&$para, &$html, $inline): void {
            if ($para) {
                $html .= '<p>' . $inline(implode(' ', $para)) . "</p>\n";
                $para = [];
            }
        };
        $closeList = static function () use (&$list, &$html): void {
            if ($list !== null) {
                $html .= "</$list>\n";
                $list = null;
            }
        };
        foreach (explode("\n", str_replace("\r\n", "\n", $body)) as $line) {
            $t = trim($line);
            if ($t === '') {
                $flushPara();
                $closeList();
            } elseif (preg_match('/^(#{2,3})\s+(.+)$/', $t, $m)) {
                $flushPara();
                $closeList();
                $tag = strlen($m[1]) === 2 ? 'h2' : 'h3';
                $html .= "<$tag>" . $inline($m[2]) . "</$tag>\n";
            } elseif (preg_match('/^[-*]\s+(.+)$/', $t, $m) || preg_match('/^\d+[.)]\s+(.+)$/', $t, $m)) {
                $flushPara();
                $want = preg_match('/^\d/', $t) ? 'ol' : 'ul';
                if ($list !== $want) {
                    $closeList();
                    $html .= "<$want>\n";
                    $list = $want;
                }
                $html .= '<li>' . $inline($m[1]) . "</li>\n";
            } else {
                $closeList();
                $para[] = $t;
            }
        }
        $flushPara();
        $closeList();

        return $html;
    }

    public static function defaultBody(string $slug): string
    {
        if ($slug !== 'seller_terms') {
            return '';
        }

        return <<<'MD'
These rules are an agreement between you (the "seller", including everyone who uses your seller account) and **{{platform_legal_name}}**, {{platform_address}}, which owns the eClinicPro brand and runs eClinicPro Store ("eClinicPro", "we"). By ticking the box and accepting them in the seller portal you agree to be bound by them. Numbers shown here (commission, time limits, fees) are the current settings and update automatically.

## 1. Who sells to the customer
- **You are the seller.** The contract of sale is between you and the customer. eClinicPro only provides the online marketplace (as an "intermediary" under the Information Technology Act, 2000 and an "e-commerce entity" under the Consumer Protection (E-Commerce) Rules, 2020), collects payment on your behalf and arranges delivery.
- eClinicPro does **not** make, own, stock, inspect or sell your products and gives **no warranty** about them. You alone are responsible for each product, its quality, safety, labelling, legal compliance, and every claim made about it.
- A GST tax invoice is generated **in your name and GSTIN** for every package when it is dispatched. You are responsible for reporting these sales, and any credit notes, in your GST returns.
- Keep your **HSN code and GST rate correct** for every product. If you are unsure, ask your CA before listing. Wrong tax details are your responsibility.
- Where the law requires eClinicPro to collect TCS (GST) or deduct TDS (income tax) on your sales, it will be deducted from your payouts and shown in your statement.

## 2. What you may sell
- Only **genuine, sealed, unexpired** products that you are legally allowed to sell, with the MRP, batch and expiry printed as the law requires.
- Every unit you dispatch must have **at least 3 months of shelf life left** (or the full shelf life, if the product's total shelf life is shorter).
- Hold and upload every licence your category needs (for example FSSAI, drug or AYUSH licences, medical-device registration). Keep them valid: products that need a licence can't be listed without a valid one, and may be hidden when it expires.
- **Not allowed:** prescription medicines, anything banned or restricted in India, counterfeit or grey-market goods, and products you have no right to sell.
- **No medical claims** in titles, descriptions or images (for example "cures diabetes"). Describe what the product is, not what it treats.
- Infant food and feeding bottles cannot be promoted or discounted (IMS Act); the store blocks coupons on them automatically.
- New products and major edits may be checked by eClinicPro before they go live. We may remove any listing that breaks these rules.

## 3. Prices and stock
- The price you set **includes GST** and must not be above MRP.
- Keep stock accurate. Stock is reserved when a customer places an order and released automatically if they don't pay within {{payment_window}} minutes.

## 4. Orders and dispatch
1. **Accept** every new order within **{{accept_hours}} hours**. Orders not accepted in time are cancelled automatically and the customer is refunded.
2. **Pack** the items securely, with the tax invoice, and mark the order packed.
3. **Book the courier** from the order page: enter the packed weight and box size, and **attach a photo of the packed parcel on a weighing scale** with the reading visible. Print the label and hand the package over at pickup from your registered pickup address.
- If you cannot fulfil an item (for example it is out of stock), cancel that item from the order page **before dispatch** with the reason. The customer is refunded automatically.
- Repeated cancellations or late dispatch may lead to your store being suspended.

## 5. Delivery charges
- **The customer** pays one delivery fee per order: **{{shipping_fee}}**, free when the order's items total **{{free_shipping_above}}** or more. eClinicPro invoices this fee in its own name.
- **You pay the courier charge for your package.** eClinicPro books the courier on its Shiprocket account, pays the courier, and deducts the charge from your earnings when the package is delivered.
- **The customer's delivery fee reduces your courier charge.** When a customer pays a delivery fee, the share for your package is taken off your courier charge. You are never charged less than zero; any remainder stays with eClinicPro.
- The courier charge depends on the **billed weight**: the larger of the actual weight and the volumetric weight (length × breadth × height in cm ÷ 5000, in kg), rounded up to the next 500 g. As a guide, it is about **{{courier_first_500g}} for the first 500 g and {{courier_extra_500g}} for each extra 500 g**, GST included; the actual courier charge applies. The earnings estimate on the product page uses these numbers.
- Keep the packed weight and box size of each product accurate. Small, light, well-packed parcels cost less to ship.

## 6. Weight disputes
- Couriers re-weigh parcels. If the courier bills a higher weight than you entered, the **extra charge is yours**, deducted from your payouts.
- The photo of the packed parcel on a scale is your evidence. eClinicPro will dispute wrong weights with the courier using your photo; if the dispute is won, the charge is reversed. Without a clear photo, we can't dispute the charge.

## 7. Returns and refunds
- **No "change of mind" returns.** A customer can return an item only when it arrived **damaged, defective, wrong, expired (or near expiry), not as described, or with parts missing**, within **{{return_window}} of delivery** (the same for every product and seller). Photos are required as evidence. After that, the sale is final.
- Review each return request **within 2 days** from the Returns page: approve it (a reverse pickup is arranged, or the customer is refunded without pickup) or reject it with a clear reason.
- When a returned package reaches you, inspect it and record the result. If you report a problem with the returned item, eClinicPro reviews the evidence and makes the final decision.
- **You pay the return pickup** for every approved return, and the forward courier charge you already paid is not refunded. Both are deducted from your payouts.
- **Items damaged in transit by the courier** (where the package was packed properly): eClinicPro pays the return pickup and claims from the courier.
- **Replacements are not offered at present:** an approved return is refunded to the customer, who can place a new order.
- When an item is refunded after dispatch, a **credit note** is issued automatically against your invoice for exactly those units, and your earnings, our commission and the GST on it are reversed for those units.
- Any payment-gateway or bank charge that is not given back to eClinicPro when a customer is refunded for a reason that is your responsibility may also be deducted from your payouts.

## 8. Packages that don't reach the customer
- **Returned to you by the courier** (the customer refused it or couldn't be reached, or for a reason caused by you such as late dispatch, a wrong label or a wrong item): the customer is refunded for the items, stock comes back to you, and there are no earnings on that package. **You pay the forward and return courier charges**, deducted from your payouts.
- **Lost or damaged by the courier:** the customer is refunded in full. As long as the package was packed properly, eClinicPro normally pays you what you would have earned and claims from the courier itself.

## 9. Commission and payouts
- Commission is the rate **agreed with you** (for your whole store, a category or a product; **{{commission_pct}}** where nothing else was agreed), charged on your selling price **before GST** (after any discount you fund), plus 18% GST on the commission. Example: a ₹118 item with 18% GST is ₹100 + ₹18 GST, so a 10% commission is ₹10 + ₹1.80 GST. The rate for each product is shown in its earnings estimate and in every order breakdown.
- **Customers pay eClinicPro on your behalf.** The sale is yours: your invoice, your GST. eClinicPro collects the money, keeps its commission and charges, and pays you the rest. Your payout statement lists the full amount collected and every deduction.
- Your earnings for a package become **available after delivery plus the return window**, so refunds can still be handled. A package with an open return is held until the return is closed.
- **Payouts every {{payout_day}}.** Every {{payout_day}}, eClinicPro pays all your earnings whose return window has ended to your **verified bank account**, when your available balance is at least {{min_payout}} (a smaller balance carries over to the next week). If {{payout_day}} is a bank holiday, payment is made on the next working day. Each payout comes with a statement listing every order and every deduction.
- Payouts are reduced by commission and GST on it, courier charges (section 5), weight-dispute and other charges under these rules, refunds and credit notes, any TCS/TDS the law requires, and agreed adjustments. Every deduction is listed in your payout statement and on the order page, and can be disputed (section 13). If your balance goes negative (for example after a refund), it is recovered from your next payouts.
- **Low-priced items:** on a small item, the courier charge can be a large part of the price. Check the earnings estimate on the product page before you set a price. Selling small items as packs of 2 or more usually works better.

## 10. Coupons and offers
- Coupons funded by eClinicPro **do not reduce** your earnings; we pay you the difference.
- Coupons funded by you reduce your selling price, and your invoice and commission follow the reduced price. eClinicPro sets up seller-funded coupons on your products only after agreeing them with you.

## 11. Reviews
- Only verified buyers can review, and reviews are checked before they appear.
- You may reply publicly and politely. Never offer anything in exchange for reviews, post reviews of your own products, or share customer details in a reply.

## 12. Customer data
- Use customer names, addresses and phone numbers **only to fulfil the order**. Never contact customers for marketing or share their data with anyone.

## 13. eClinicPro's commitments to you
- **Customers pay before you dispatch.** Every order is paid online through a regulated payment gateway; there is no cash on delivery, so you never chase payment.
- **We look after customers first.** eClinicPro is the customer's first point of contact for payments, order tracking, delivery, cancellations and refunds. We pass to you only questions about your product (for example ingredients, usage or specifications) and return requests, which you answer from the seller portal.
- **Paid on time, with a statement.** Payouts are made every {{payout_day}} as described in section 9. If a payout is late, write to **{{grievance_email}}** and we will pay it or explain the delay in writing within 2 working days.
- **Only the deductions in these rules.** Every deduction is shown on the order page with its reason and amount. You can **dispute any deduction within {{charge_dispute_days}} days** using "Dispute this charge" on the order page. While a dispute is open, that amount is **left out of your payouts**. We reply within **{{charge_dispute_response_days}} days** with the evidence (for example the courier's invoice or weight report); if we accept, the amount is credited in your next payout.
- **Decisions based on evidence.** Return and dispute decisions are based on the customer's photos, your parcel photo, courier records and the order history, and we tell you the reason for every decision.
- **No surprise changes.** Your agreed commission is never changed without your written agreement (email is enough). A new version of these rules takes effect **{{terms_notice_days}} days** after we publish it, and until then the version you accepted applies. Only a change required by law or a regulator can apply sooner.
- **Fair warning before suspension.** Except in the cases listed in section 21, we tell you the reason in writing and give you **{{suspension_notice_days}} days** to respond or fix the problem before your store is suspended.
- **Your data stays private.** Your sales, prices and business details are kept confidential and are never shared with other sellers.

## 14. Your promises to us
- The business, tax, bank and licence details and documents you give us are true, complete and yours, and you will keep them up to date.
- You hold every registration and licence the law requires for your business and products, and you follow all laws that apply to them, including the Consumer Protection Act, 2019 and E-Commerce Rules, the Legal Metrology (Packaged Commodities) Rules, the Drugs and Cosmetics Act, the Food Safety and Standards Act, BIS rules, GST law, and advertising and labelling rules.
- You have the right to sell every product you list, and your products, listings, photos and brand names do not infringe anyone's trademark, copyright or other rights.

## 15. Product responsibility and recalls
- **You are fully responsible for your products**: their quality, safety, authenticity, shelf life, packaging, labelling, warranties, and any harm, injury, loss or claim they cause. Any manufacturer's warranty is between you (or the manufacturer) and the customer.
- If a product is recalled, banned, or found unsafe or counterfeit, you must tell eClinicPro at once, stop selling it, and pay for the refunds, return pickups and any other costs that follow.
- You will answer customer complaints about your products promptly and co-operate with any authority, court or regulator that asks about them.

## 16. Indemnity
You will **indemnify and hold harmless** eClinicPro, its directors, employees and partners against every claim, demand, penalty, tax, loss, damage, cost and legal fee arising from: your products or listings; any breach of these rules or of any law by you; wrong tax or licence details; infringement of anyone's rights; your misuse of customer data; or anything done by people using your seller account. This continues after your account is closed.

## 17. Limits on eClinicPro's liability
- The marketplace is provided "as is". eClinicPro does not promise any level of sales or that the store will always be available or error-free.
- eClinicPro is not liable for indirect or consequential losses, including lost profit, sales or goodwill. Its total liability to you for any claim is limited to the commission it earned from your sales in the 3 months before the claim arose.
- eClinicPro is not responsible for delays or failures caused by couriers, banks, payment gateways, or events outside its reasonable control (for example natural disasters, strikes, government orders, pandemics, or internet and power outages).

## 18. Holds and set-off
- eClinicPro may **deduct any amount you owe** under these rules (courier charges, return and failed-delivery charges, refunds, penalties, taxes, indemnity amounts) from any payout, and recover any shortfall from your later payouts or ask you to pay it directly.
- eClinicPro may **hold payouts** while a customer complaint, return, chargeback, legal notice, investigation or tax query about your sales is open, or when your account is suspended or closed, until the matter is settled.

## 19. Content you upload
- You give eClinicPro a free, non-exclusive licence to use, copy, resize, translate and display your product names, descriptions, photos, logos and brand names on the store, its apps, and its marketing, while your products are listed and for a reasonable time after.
- You must not use eClinicPro's name or logo except as eClinicPro allows in writing.

## 20. Your account
- Keep your login details secret. Everything done through your seller account is treated as done by you.
- Do not try to move customers off the store to sell to them directly, avoid commission, or manipulate prices, reviews or search results.

## 21. Suspension and closing your account
- eClinicPro may hide listings or suspend or close a store that breaks these rules, gives false information, or receives repeated serious complaints, after the notice in section 13.
- eClinicPro may act **immediately**, without that notice, when a product is fake, unsafe, banned or recalled, in a case of fraud, when a court, regulator or the law requires it, or when customers' health is at risk. We still tell you the reason in writing.
- You may close your store at any time by writing to eClinicPro, after you have fulfilled or cancelled every open order.
- After closing, your remaining earnings, less any amounts you owe, are paid within **{{final_settlement_days}} days** after the last return window ends (or after any open matter is settled). Sections on product responsibility, indemnity, liability, set-off, customer data, and disputes continue to apply.

## 22. Relationship
You and eClinicPro are independent businesses. Nothing in these rules creates a partnership, joint venture, agency or employment.

## 23. Changes to these rules
These rules may be updated. You will be emailed and see the new version in the seller portal, with the date it takes effect ({{terms_notice_days}} days after publishing, unless the law requires sooner). Accept it before then to keep selling; if you don't agree, you may close your store under section 21 before it takes effect. Every acceptance is recorded with its version, date, time and IP address as an electronic record under the Information Technology Act, 2000.

## 24. Grievances and disputes
- Questions or complaints about these rules can be sent to **{{grievance_email}}**. We will acknowledge them within {{grievance_ack_hours}} hours and try to resolve them within {{grievance_resolve_days}} days.
- These rules are governed by the laws of India. Any dispute that cannot be resolved by discussion is subject to the exclusive jurisdiction of the courts at **{{legal_city}}**.
- If any part of these rules is found invalid, the rest still applies. These rules, with any commission or charges agreed with you in writing, are the whole agreement between you and eClinicPro about selling on eClinicPro Store.
MD;
    }
}
