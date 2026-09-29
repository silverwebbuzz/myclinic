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

    /** @return array{title: string, body: string, version: int, updated_at: ?string} */
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

        return ['title' => (string) $row['title'], 'body' => (string) $row['body'], 'version' => (int) $row['version'], 'updated_at' => $row['updated_at'] ?? null];
    }

    /** @return array{ok: bool, error?: string, version?: int} */
    public static function save(string $slug, string $title, string $body, int $adminId): array
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
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $pdo->prepare('UPDATE store_policy_pages SET title = :t, body = :b, version = :v, updated_by = :u WHERE slug = :s')
                ->execute(['t' => $title, 'b' => $body, 'v' => $v, 'u' => $adminId, 's' => $slug]);
            $pdo->prepare('INSERT INTO store_policy_versions (slug, version, title, body, created_by) VALUES (:s, :v, :t, :b, :u)')
                ->execute(['s' => $slug, 'v' => $v, 't' => $title, 'b' => $body, 'u' => $adminId]);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            error_log('[StorePolicy::save] ' . $e->getMessage());

            return ['ok' => false, 'error' => 'Could not save.'];
        }
        StoreAudit::log('policy.save', 'policy', null, ['slug' => $slug, 'version' => $cur['version']], ['slug' => $slug, 'version' => $v]);
        if ($slug === 'seller_terms') {
            StoreNotifier::termsUpdated($v);   // sellers must re-accept the new version
        }

        return ['ok' => true, 'version' => $v];
    }

    /** @return list<array<string, mixed>> */
    public static function versions(string $slug): array
    {
        $st = Database::connection()->prepare('SELECT version, title, created_by, created_at FROM store_policy_versions WHERE slug = :s ORDER BY version DESC LIMIT 50');
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

        return [
            'commission_pct' => rtrim(rtrim(number_format(StoreSettings::int('store_default_commission_bp', 1000) / 100, 2), '0'), '.') . '%',
            'accept_hours' => (string) StoreSettings::int('store_vendor_accept_sla_hours', 48),
            'return_days' => (string) StoreSettings::int('store_default_return_window_days', 7),
            'min_payout' => $rs(StoreSettings::int('store_min_payout_paise', 10000)),
            'shipping_fee' => $rs($fees['delivery_fee']),
            'free_shipping_above' => $fees['free_above'] !== null ? $rs($fees['free_above']) : 'never',
            'courier_first_500g' => $rs($fees['courier_base']),
            'courier_extra_500g' => $rs($fees['courier_addl']),
            'payment_window' => (string) StoreSettings::int('store_payment_window_minutes', 30),
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
These rules apply to every seller on eClinicPro Store. By accepting them in the seller portal you agree to follow them. Numbers shown here (commission, time limits, fees) are the current settings and update automatically.

## 1. Who sells to the customer
- **You are the seller.** The customer buys from you; eClinicPro runs the marketplace (the "e-commerce operator"), takes the payment and arranges delivery.
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
- Customers can request a return of eligible products within the product's return window (**{{return_days}} days from delivery** unless the product says otherwise). Photos are required when an item is reported damaged, wrong, expired or defective.
- Review each return request **within 2 days** from the Returns page: approve it (a reverse pickup is arranged, or the customer is refunded without pickup) or reject it with a clear reason.
- When a returned package reaches you, inspect it and record the result. If you report a problem with the returned item, eClinicPro reviews the evidence and makes the final decision.
- **Return pickup for wrong, expired, defective or badly packed items** (your responsibility): you pay it. The forward courier charge you already paid is not refunded.
- **Return pickup when the customer changed their mind:** eClinicPro pays it.
- **Items damaged in transit by the courier:** eClinicPro pays the return pickup and claims from the courier.
- **Replacements are not offered at present:** an approved return is refunded to the customer, who can place a new order.
- When an item is refunded after dispatch, a **credit note** is issued automatically against your invoice for exactly those units, and your earnings, our commission and the GST on it are reversed for those units.

## 8. Packages that don't reach the customer
- **Customer refused or couldn't be reached (returned to you by the courier):** the customer is refunded for the items, stock comes back to you, and there are no earnings on that package. **eClinicPro pays** the forward and return courier charges.
- **Caused by you** (for example late dispatch, wrong address label, wrong item packed, or marking a package ready when it wasn't): **you pay** the forward and return courier charges.
- **Lost or damaged by the courier:** the customer is refunded in full. As long as the package was packed properly, eClinicPro normally pays you what you would have earned and claims from the courier itself.

## 9. Commission and payouts
- Commission is the rate **agreed with you** (for your whole store, a category or a product; **{{commission_pct}}** where nothing else was agreed), charged on your selling price **before GST** (after any discount you fund), plus 18% GST on the commission. Example: a ₹118 item with 18% GST is ₹100 + ₹18 GST, so a 10% commission is ₹10 + ₹1.80 GST. The rate for each product is shown in its earnings estimate and in every order breakdown.
- **Customers pay eClinicPro on your behalf.** The sale is yours: your invoice, your GST. eClinicPro collects the money, keeps its commission and charges, and pays you the rest. Your payout statement lists the full amount collected and every deduction.
- Your earnings for a package become **available after delivery plus the return window**, so refunds can still be handled. A package with an open return is held until the return is closed.
- Payouts go to your **verified bank account** in eClinicPro's regular payout runs, once your available balance is at least {{min_payout}}. Each payout comes with a statement.
- Payouts are reduced by commission and GST on it, courier charges (section 5), weight-dispute and other charges under these rules, refunds and credit notes, any TCS/TDS the law requires, and agreed adjustments. Every deduction is listed in your payout statement. If your balance goes negative (for example after a refund), it is recovered from your next payouts.
- **Low-priced items:** on a small item, the courier charge can be a large part of the price. Check the earnings estimate on the product page before you set a price. Selling small items as packs of 2 or more usually works better.

## 10. Coupons and offers
- Coupons funded by eClinicPro **do not reduce** your earnings; we pay you the difference.
- Coupons funded by you reduce your selling price, and your invoice and commission follow the reduced price. eClinicPro sets up seller-funded coupons on your products only after agreeing them with you.

## 11. Reviews
- Only verified buyers can review, and reviews are checked before they appear.
- You may reply publicly and politely. Never offer anything in exchange for reviews, post reviews of your own products, or share customer details in a reply.

## 12. Customer data
- Use customer names, addresses and phone numbers **only to fulfil the order**. Never contact customers for marketing or share their data with anyone.

## 13. Suspension and changes
- eClinicPro may hide listings or suspend a store that breaks these rules, sells unsafe or fake products, or receives repeated serious complaints. Earnings needed to cover refunds may be held until they are settled.
- These rules may be updated. You will see the new version in the seller portal and must accept it to keep selling.
- These rules are governed by the laws of India.
MD;
    }
}
