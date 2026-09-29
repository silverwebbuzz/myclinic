<?php

declare(strict_types=1);

namespace App\Services\Store;

use App\Core\Database;
use App\Core\QueryBuilder;

/**
 * Every store email's editable WORDING, with its built-in default.
 *
 * StoreNotifier decides WHEN an email goes out and fills the automatic parts
 * (order details box, item lines, totals, address, deadlines). This class owns
 * the words around them: subject, heading, message, button label and an extra
 * note. Admin can override any of those and switch any email off
 * (/admin/store/email-templates, table store_email_templates). No row = default
 * wording, email on (unless 'on' => false below).
 *
 * Text uses {{placeholders}}; the values are escaped when the email is drawn,
 * so admin-typed text can't inject HTML. Body = paragraphs separated by a
 * blank line.
 */
final class StoreEmailTemplates
{
    public const GROUPS = ['customer' => 'Customer emails', 'seller' => 'Seller emails', 'team' => 'Store team emails'];

    /**
     * key => [group, label, subject, title, body, cta, note, vars, on?]
     * vars = placeholders this email understands (with a sample value for previews).
     */
    private const T = [
        // ---------------- Customer ----------------
        'customer_order_confirmed' => ['customer', 'Order confirmed (payment received)',
            'Order confirmed: {{order_no}}', 'Thank you, your order is confirmed',
            "Hi {{name}},\n\nWe have received your payment of {{amount}}. Each seller now packs their items, and we will email you when each package ships.",
            'View your order', '', ['name' => 'Priya', 'order_no' => 'ECS260929-7K3MQ', 'amount' => '₹1,249']],
        'customer_order_accepted' => ['customer', 'Seller is preparing the order',
            'Your order {{order_no}} is being prepared', 'The seller is preparing your order',
            "Hi {{name}},\n\n{{seller}} has confirmed your order and is getting it ready. We will email you as soon as it ships.",
            'View your order', '', ['name' => 'Priya', 'order_no' => 'ECS260929-7K3MQ', 'seller' => 'Wellness Mart']],
        'customer_order_packed' => ['customer', 'Package packed',
            'Packed: your package from {{seller}}', 'Your package is packed',
            "Hi {{name}},\n\n{{seller}} has packed your items. The courier will pick them up soon.",
            'View your order', '', ['name' => 'Priya', 'order_no' => 'ECS260929-7K3MQ', 'seller' => 'Wellness Mart'], false],
        'customer_shipped' => ['customer', 'Package shipped',
            'Shipped: your package from {{seller}}', 'Your package is on its way',
            "Hi {{name}},\n\nGood news: your package from {{seller}} has been handed to the courier.",
            'Track your package', '', ['name' => 'Priya', 'order_no' => 'ECS260929-7K3MQ', 'seller' => 'Wellness Mart', 'awb' => '1234567890']],
        'customer_delivered' => ['customer', 'Package delivered',
            'Delivered: your package from {{seller}}', 'Your package has been delivered',
            "Hi {{name}},\n\nYour package from {{seller}} has been delivered. We hope it helps!",
            'View your order', 'If something is wrong with an item (damaged, wrong or expired), you can request a return from your order page within the return window.',
            ['name' => 'Priya', 'order_no' => 'ECS260929-7K3MQ', 'seller' => 'Wellness Mart']],
        'customer_refund' => ['customer', 'Items cancelled + refund',
            'Refund of {{amount}} for order {{order_no}}', 'Your refund is on its way',
            "Hi {{name}},\n\nSome items in your order were cancelled {{who}}. We have refunded {{amount}} to your original payment method.",
            'View your order', 'Refunds usually reach your account in 5–7 working days, depending on your bank.',
            ['name' => 'Priya', 'order_no' => 'ECS260929-7K3MQ', 'amount' => '₹499', 'refund_no' => 'RF260930-A1B2C3', 'who' => 'as you requested', 'reason' => 'Changed my mind']],
        'customer_payment_expired' => ['customer', 'Order not paid (expired)',
            'Your order {{order_no}} was not completed', 'Your order was not completed',
            "Hi {{name}},\n\nWe didn't receive payment for order {{order_no}} in time, so the items were released and nothing was charged.\n\nYour cart is still saved. You can check out again whenever you're ready.",
            'Go to your cart', '', ['name' => 'Priya', 'order_no' => 'ECS260929-7K3MQ', 'amount' => '₹1,249']],
        'customer_return_approved' => ['customer', 'Return approved',
            'Return approved: {{return_no}}', 'Your return was approved',
            "Hi {{name}},\n\n{{pickup_text}}",
            'View your order', 'Your refund is issued once the seller receives and checks the item.',
            ['name' => 'Priya', 'return_no' => 'RT260930-9F8E7D', 'order_no' => 'ECS260929-7K3MQ', 'pickup_text' => 'A courier will collect the item from your delivery address. Please keep it packed with all tags and accessories.']],
        'customer_return_rejected' => ['customer', 'Return not accepted',
            'About your return {{return_no}}', 'Your return could not be accepted',
            "Hi {{name}},\n\nWe're sorry, the seller could not accept this return.",
            'View your order', 'If you think this is wrong, reply to this email with your order number and we will look into it.',
            ['name' => 'Priya', 'return_no' => 'RT260930-9F8E7D', 'order_no' => 'ECS260929-7K3MQ', 'reason' => 'Seal was opened']],
        'customer_return_refunded' => ['customer', 'Return refunded',
            'Refund of {{amount}} for return {{return_no}}', 'Your refund is on its way',
            "Hi {{name}},\n\nWe have refunded {{amount}} to your original payment method.",
            'View your order', 'Refunds usually reach your account in 5–7 working days, depending on your bank.',
            ['name' => 'Priya', 'return_no' => 'RT260930-9F8E7D', 'order_no' => 'ECS260929-7K3MQ', 'amount' => '₹499']],
        'customer_review_published' => ['customer', 'Review published',
            'Your review of {{product}} is live', 'Thanks for your review',
            "Hi {{name}},\n\nYour review of {{product}} is now live on eClinicPro Store. It helps other customers choose well.",
            'See your review', '', ['name' => 'Priya', 'product' => 'Vitamin D3 60K'], false],
        'customer_review_reply' => ['customer', 'Seller replied to a review',
            '{{seller}} replied to your review', 'The seller replied to your review',
            "Hi {{name}},\n\n{{seller}} replied to your review of {{product}}:\n\n\"{{reply}}\"",
            'See the reply', '', ['name' => 'Priya', 'product' => 'Vitamin D3 60K', 'seller' => 'Wellness Mart', 'reply' => 'Thank you for your feedback!']],

        // ---------------- Seller ----------------
        'seller_welcome' => ['seller', 'Welcome (new registration)',
            'Welcome to eClinicPro Store, {{seller}}', 'Welcome to eClinicPro Store',
            "Hi {{name}},\n\nThanks for registering {{seller}} as a seller. To start selling, complete your checklist in the seller portal: business details, pickup address, bank account and documents. Then submit it for review.\n\nOur team usually reviews new sellers within 2 working days.",
            'Complete your checklist', '', ['name' => 'Rahul', 'seller' => 'Wellness Mart']],
        'seller_password_reset' => ['seller', 'Password reset link',
            'Reset your eClinicPro Store seller password', 'Reset your password',
            "Hi {{name}},\n\nWe received a request to reset the password for your seller account. The button below works once and expires in 60 minutes.",
            'Choose a new password', 'If you didn\'t ask for this, you can ignore this email. Your password stays the same.',
            ['name' => 'Rahul', 'seller' => 'Wellness Mart']],
        'seller_approved' => ['seller', 'Account approved',
            'Your seller account is approved', 'Welcome to eClinicPro Store',
            "Hi {{name}},\n\nGood news: {{seller}} has been approved. You can now add products; each product goes live after a quick review.",
            'Add your products', '', ['name' => 'Rahul', 'seller' => 'Wellness Mart', 'reason' => '']],
        'seller_rejected' => ['seller', 'Application needs changes',
            'Update needed on your seller application', 'Your application needs changes',
            "Hi {{name}},\n\nWe could not approve {{seller}} yet. Please fix the points below and submit again from your seller portal.",
            'Update your application', '', ['name' => 'Rahul', 'seller' => 'Wellness Mart', 'reason' => 'GST certificate is not readable']],
        'seller_suspended' => ['seller', 'Account suspended',
            'Your seller account is suspended', 'Your seller account is suspended',
            "Hi {{name}},\n\n{{seller}} has been suspended, so your products are hidden from customers for now. Please reply to this email to resolve it.",
            'Open your seller portal', '', ['name' => 'Rahul', 'seller' => 'Wellness Mart', 'reason' => 'Repeated late dispatch']],
        'seller_reactivated' => ['seller', 'Account reactivated',
            'Your seller account is active again', 'Your account is active again',
            "Hi {{name}},\n\n{{seller}} has been reactivated and your live products are visible to customers again.",
            'Open your seller portal', '', ['name' => 'Rahul', 'seller' => 'Wellness Mart', 'reason' => '']],
        'seller_closed' => ['seller', 'Account closed',
            'Your seller account has been closed', 'Your seller account is closed',
            "Hi {{name}},\n\nYour seller account {{seller}} on eClinicPro Store has been closed.",
            '', '', ['name' => 'Rahul', 'seller' => 'Wellness Mart', 'reason' => 'Closed at your request']],
        'seller_document_rejected' => ['seller', 'Document rejected',
            'Please re-upload: {{document}}', 'A document needs to be uploaded again',
            "Hi {{name}},\n\nWe couldn't accept the {{document}} you uploaded. Please upload a clear, valid copy from the Documents page.",
            'Upload the document', '', ['name' => 'Rahul', 'seller' => 'Wellness Mart', 'document' => 'GST certificate', 'reason' => 'Image is blurred']],
        'seller_product_approved' => ['seller', 'Product approved (live)',
            'Live: {{product}}', 'Your product is live',
            "Hi {{name}},\n\n{{product}} has been approved and is now visible to customers on eClinicPro Store.",
            'View your products', '', ['name' => 'Rahul', 'product' => 'Vitamin D3 60K']],
        'seller_product_rejected' => ['seller', 'Product needs changes',
            'Changes needed: {{product}}', 'Your product needs changes',
            "Hi {{name}},\n\nWe couldn't publish {{product}} yet. Please make the changes below and submit it again.",
            'Edit the product', '', ['name' => 'Rahul', 'product' => 'Vitamin D3 60K', 'reason' => 'Add the FSSAI licence number']],
        'seller_product_disabled' => ['seller', 'Product taken down',
            'Taken down: {{product}}', 'A product was taken down',
            "Hi {{name}},\n\n{{product}} has been hidden from customers for the reason below. Reply to this email if you have questions.",
            'View your products', '', ['name' => 'Rahul', 'product' => 'Vitamin D3 60K', 'reason' => 'Unsupported health claim in the title']],
        'seller_new_order' => ['seller', 'New order',
            'New order {{order_no}}: accept by {{accept_by}}', 'You have a new order',
            "Hi {{name}},\n\nA customer has paid for the items below. Please accept the order, then pack it with the invoice.",
            'Open this order', '', ['name' => 'Rahul', 'order_no' => 'ECS260929-7K3MQ-A', 'accept_by' => '1 Oct, 4:42 pm']],
        'seller_order_cancelled' => ['seller', 'Order cancelled',
            'Order cancelled: {{order_no}}', 'An order was cancelled',
            "Hi {{name}},\n\nIn order {{order_no}}, the items below were cancelled {{who}}.",
            'Open this order', '', ['name' => 'Rahul', 'order_no' => 'ECS260929-7K3MQ-A', 'who' => 'by the eClinicPro team', 'reason' => 'Customer request']],
        'seller_items_cancelled' => ['seller', 'Some items cancelled',
            'Items cancelled: {{order_no}}', 'Some items were cancelled',
            "Hi {{name}},\n\nIn order {{order_no}}, the items below were cancelled {{who}}.",
            'Open this order', '', ['name' => 'Rahul', 'order_no' => 'ECS260929-7K3MQ-A', 'who' => 'as the customer requested', 'reason' => 'Changed my mind']],
        'seller_pickup_failed' => ['seller', 'Courier missed pickup',
            'Courier update for {{order_no}}: pickup missed', 'Pickup was missed',
            "Hi {{name}},\n\nThe courier could not collect this package. Please keep it packed and ready; pickup will be re-attempted.",
            'Open this order', '', ['name' => 'Rahul', 'order_no' => 'ECS260929-7K3MQ-A']],
        'seller_rto' => ['seller', 'Package returning to seller',
            'Courier update for {{order_no}}: returning to you', 'Package is being returned to you',
            "Hi {{name}},\n\nThe courier could not deliver this package and is returning it to your pickup address.",
            'Open this order', '', ['name' => 'Rahul', 'order_no' => 'ECS260929-7K3MQ-A']],
        'seller_return_requested' => ['seller', 'Return requested',
            'Return requested: {{return_no}} ({{order_no}})', 'A customer requested a return',
            "Hi {{name}},\n\nPlease review the request and the customer's photos, then approve or reject it.",
            'Review the return', '', ['name' => 'Rahul', 'return_no' => 'RT260930-9F8E7D', 'order_no' => 'ECS260929-7K3MQ-A', 'reason' => 'Arrived damaged']],
        'seller_return_refunded' => ['seller', 'Return refunded',
            'Return refunded: {{return_no}}', 'A return was refunded',
            "Hi {{name}},\n\nThe customer has been refunded for the items below. The amount has been adjusted in your earnings.",
            'See your payouts', '', ['name' => 'Rahul', 'return_no' => 'RT260930-9F8E7D', 'order_no' => 'ECS260929-7K3MQ-A', 'amount' => '₹499']],
        'seller_new_review' => ['seller', 'New customer review',
            'New {{rating}}★ review: {{product}}', 'You have a new review',
            "Hi {{name}},\n\nA customer rated {{product}} {{rating}} out of 5. You can reply to it from the Reviews page; your reply is shown under the review.",
            'Reply to the review', '', ['name' => 'Rahul', 'product' => 'Vitamin D3 60K', 'rating' => '5']],
        'seller_low_stock' => ['seller', 'Low stock (daily)',
            'Low stock: {{count}} product(s) need restocking', 'Some products are running low',
            "Hi {{name}},\n\nRestock these soon. Products that run out stop selling, and orders you can't fulfil are cancelled.",
            'Update stock', '', ['name' => 'Rahul', 'count' => '3']],
        'seller_payout_requested' => ['seller', 'Payout request received',
            'Payout request received: {{amount}}', 'We received your payout request',
            "Hi {{name}},\n\nWe received your request to be paid {{amount}}. Our team will check it and transfer it to your bank account, usually within 2 working days. We'll email you when it's sent.",
            'See your payouts', '', ['name' => 'Rahul', 'amount' => '₹12,480', 'payout_no' => 'PO260930-AB12CD']],
        'seller_payout_declined' => ['seller', 'Payout request declined',
            'About your payout request {{payout_no}}', 'Your payout request was declined',
            "Hi {{name}},\n\nWe couldn't process your payout request of {{amount}}. The amount is back in your available balance, and you can request it again once the issue below is fixed.",
            'See your payouts', '', ['name' => 'Rahul', 'amount' => '₹12,480', 'payout_no' => 'PO260930-AB12CD', 'reason' => 'Bank account not verified yet']],
        'seller_payout_paid' => ['seller', 'Payout sent',
            'Payout sent: {{amount}} ({{payout_no}})', 'Your payout has been sent',
            "Hi {{name}},\n\nWe have transferred {{amount}} to your bank account.",
            'Download the statement', 'It can take a few hours to show in your bank account. The statement lists every order in this payout.',
            ['name' => 'Rahul', 'amount' => '₹12,480', 'payout_no' => 'PO260930-AB12CD', 'utr' => 'UTIB0000123456']],
        'seller_payout_failed' => ['seller', 'Payout failed',
            'Payout failed: {{payout_no}}', 'Your payout could not be completed',
            "Hi {{name}},\n\nThe bank transfer of {{amount}} could not be completed. The amount is back in your available balance. Please check your bank details; we'll retry once they're correct.",
            'Check your bank details', '', ['name' => 'Rahul', 'amount' => '₹12,480', 'payout_no' => 'PO260930-AB12CD', 'reason' => 'Account number / IFSC mismatch']],
        'seller_terms_updated' => ['seller', 'Seller terms updated',
            'We\'ve updated the seller terms', 'The seller terms have been updated',
            "Hi {{name}},\n\nWe've published a new version of the eClinicPro Store seller terms. Please read and accept them in your seller portal to keep selling without interruption.",
            'Review the terms', '', ['name' => 'Rahul', 'version' => '3']],

        // ---------------- Store team ----------------
        'team_order_paid' => ['team', 'New paid order',
            'Store order paid: {{order_no}} ({{amount}})', 'New paid order', '', 'Open in admin', '',
            ['order_no' => 'ECS260929-7K3MQ', 'amount' => '₹1,249']],
        'team_order_autocancelled' => ['team', 'Order auto-cancelled (seller too slow)',
            'Auto-cancelled (seller SLA): {{order_no}}', 'Order auto-cancelled',
            'A seller did not accept in time, so the items were cancelled and refunded automatically.', 'Open in admin', '',
            ['order_no' => 'ECS260929-7K3MQ', 'amount' => '₹499']],
        'team_shipment_problem' => ['team', 'Shipment problem',
            'Shipment problem ({{problem}}): {{order_no}}', 'Shipment problem: {{problem}}', '', 'Open in admin', '',
            ['order_no' => 'ECS260929-7K3MQ-A', 'problem' => 'Pickup failed']],
        'team_return_requested' => ['team', 'Return requested',
            'Return requested: {{return_no}} · {{seller}}', 'Return requested', '', 'Open in admin', '',
            ['return_no' => 'RT260930-9F8E7D', 'seller' => 'Wellness Mart']],
        'team_return_qc_failed' => ['team', 'Return needs a decision',
            'Return QC failed: {{return_no}} · {{seller}}', 'Return needs a decision',
            'The seller says the returned item failed their check. Decide whether to refund anyway or reject.', 'Decide in admin', '',
            ['return_no' => 'RT260930-9F8E7D', 'seller' => 'Wellness Mart']],
        'team_seller_registered' => ['team', 'New seller registered',
            'New seller registered: {{seller}}', 'New seller registered',
            'A new seller created an account. They still need to complete their checklist and submit it for review.', 'View the seller', '',
            ['seller' => 'Wellness Mart']],
        'team_seller_submitted' => ['team', 'Seller waiting for approval',
            'Seller waiting for approval: {{seller}}', 'New seller to review', '', 'Review the seller', '',
            ['seller' => 'Wellness Mart']],
        'team_product_review' => ['team', 'Product waiting for review',
            'Product to review: {{product}} ({{seller}})', 'Product waiting for review', '', 'Review the product', '',
            ['product' => 'Vitamin D3 60K', 'seller' => 'Wellness Mart']],
        'team_review_pending' => ['team', 'Customer review to moderate',
            'Review to moderate: {{product}} ({{rating}}★)', 'Customer review waiting for approval', '', 'Moderate reviews', '',
            ['product' => 'Vitamin D3 60K', 'rating' => '4']],
        'team_payout_requested' => ['team', 'Seller requested a payout',
            'Payout requested: {{seller}} ({{amount}})', 'Seller requested a payout',
            'Check the payout, transfer it from the bank, then mark it paid with the UTR.', 'Open the payout', '',
            ['seller' => 'Wellness Mart', 'amount' => '₹12,480', 'payout_no' => 'PO260930-AB12CD']],
    ];

    /** @return array<string, array{group: string, label: string, vars: list<string>, on: bool}> */
    public static function registry(): array
    {
        $out = [];
        foreach (self::T as $key => $t) {
            $out[$key] = ['group' => $t[0], 'label' => $t[1], 'vars' => array_keys($t[7]), 'on' => $t[8] ?? true];
        }

        return $out;
    }

    public static function isKnown(string $key): bool
    {
        return isset(self::T[$key]);
    }

    /** @return array{subject: string, title: string, body: string, cta: string, note: string} */
    public static function defaults(string $key): array
    {
        $t = self::T[$key];

        return ['subject' => $t[2], 'title' => $t[3], 'body' => $t[4], 'cta' => $t[5], 'note' => $t[6]];
    }

    /** @return array<string, string> */
    public static function sampleVars(string $key): array
    {
        return self::T[$key][7] ?? [];
    }

    /**
     * The wording to send: admin edits over defaults, placeholders filled.
     *
     * @param array<string, string|int> $vars
     * @return array{enabled: bool, group: string, subject: string, title: string, paragraphs: list<string>, cta: string, note: list<string>}
     */
    public static function resolve(string $key, array $vars): array
    {
        $t = self::T[$key];
        $d = self::defaults($key);
        $row = self::rows()[$key] ?? null;
        $pick = static fn (string $col, string $def): string => $row !== null && trim((string) ($row[$col] ?? '')) !== '' ? (string) $row[$col] : $def;
        $fill = static fn (string $s): string => (string) preg_replace_callback('/\{\{\s*([a-z0-9_]+)\s*\}\}/i',
            static fn ($m) => array_key_exists($m[1], $vars) ? (string) $vars[$m[1]] : '', $s);

        return [
            'enabled' => $row !== null ? (int) $row['is_enabled'] === 1 : ($t[8] ?? true),
            'group' => $t[0],
            'subject' => trim($fill($pick('subject', $d['subject']))),
            'title' => trim($fill($pick('title', $d['title']))),
            'paragraphs' => self::paragraphs($fill($pick('body', $d['body']))),
            'cta' => trim($fill($pick('cta_label', $d['cta']))),
            'note' => self::paragraphs($fill($pick('note', $d['note']))),
        ];
    }

    /** @return list<string> */
    private static function paragraphs(string $s): array
    {
        $parts = preg_split('/\R[ \t]*\R/', trim($s)) ?: [];

        return array_values(array_filter(array_map('trim', $parts), static fn ($p) => $p !== ''));
    }

    /** @return array<string, array<string, mixed>> admin rows keyed by template_key */
    public static function rows(): array
    {
        static $rows = null;
        if ($rows !== null) {
            return $rows;
        }
        $rows = [];
        try {
            foreach (Database::connection()->query('SELECT * FROM store_email_templates')->fetchAll() as $r) {
                $rows[(string) $r['template_key']] = $r;
            }
        } catch (\Throwable) {
            // patch 2026_10_03 not imported yet → defaults only
        }

        return $rows;
    }

    /** @param array{subject?: string, title?: string, body?: string, cta_label?: string, note?: string, is_enabled?: bool} $in */
    public static function save(string $key, array $in, ?string $by): bool
    {
        if (!self::isKnown($key)) {
            return false;
        }
        $d = self::defaults($key);
        // Store only what differs from the default, so later default improvements still apply.
        $keep = static fn (string $v, string $def): ?string => trim($v) === '' || trim($v) === trim($def) ? null : trim($v);
        $data = [
            'subject' => $keep((string) ($in['subject'] ?? ''), $d['subject']),
            'title' => $keep((string) ($in['title'] ?? ''), $d['title']),
            'body' => $keep(str_replace("\r\n", "\n", (string) ($in['body'] ?? '')), $d['body']),
            'cta_label' => $keep((string) ($in['cta_label'] ?? ''), $d['cta']),
            'note' => $keep(str_replace("\r\n", "\n", (string) ($in['note'] ?? '')), $d['note']),
            'is_enabled' => !empty($in['is_enabled']) ? 1 : 0,
            'updated_by' => $by !== null ? mb_substr($by, 0, 190) : null,
        ];
        try {
            if (QueryBuilder::table('store_email_templates')->where('template_key', '=', $key)->first() !== null) {
                QueryBuilder::table('store_email_templates')->where('template_key', '=', $key)->update($data);
            } else {
                QueryBuilder::table('store_email_templates')->insert(['template_key' => $key] + $data);
            }
        } catch (\Throwable $e) {
            error_log('[StoreEmailTemplates::save] ' . $e->getMessage());

            return false;
        }

        return true;
    }

    public static function reset(string $key): void
    {
        try {
            QueryBuilder::table('store_email_templates')->where('template_key', '=', $key)->delete();
        } catch (\Throwable $e) {
            error_log('[StoreEmailTemplates::reset] ' . $e->getMessage());
        }
    }

    // ------------------------------------------------------------------
    // Delivery settings (platform_settings, edited on /admin/store/email)
    // ------------------------------------------------------------------

    public static function fromName(): string
    {
        return StoreSettings::get('store_email_from_name', 'eClinicPro Store') ?: 'eClinicPro Store';
    }

    public static function fromEmail(): string
    {
        $v = StoreSettings::get('store_email_from');

        return filter_var($v, FILTER_VALIDATE_EMAIL) ? $v : (string) ($_ENV['NOREPLY_FROM'] ?? 'noreply@eclinicpro.com');
    }

    public static function replyTo(): string
    {
        $v = StoreSettings::get('store_email_reply_to');

        return filter_var($v, FILTER_VALIDATE_EMAIL) ? $v : (string) ($_ENV['HELP_FROM'] ?? 'help@eclinicpro.com');
    }

    /** @return list<string> where store team alerts go (comma-separated setting) */
    public static function teamEmails(): array
    {
        $raw = StoreSettings::get('store_email_team');
        $list = array_values(array_filter(array_map('trim', explode(',', $raw)), static fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL)));

        return $list ?: [(string) ($_ENV['STORE_ADMIN_EMAIL'] ?? $_ENV['HELP_FROM'] ?? 'help@eclinicpro.com')];
    }

    // ------------------------------------------------------------------
    // Log
    // ------------------------------------------------------------------

    public static function log(string $key, string $to, string $subject, string $status, ?string $error = null): void
    {
        try {
            QueryBuilder::table('store_email_log')->insert([
                'template_key' => $key,
                'recipient' => mb_substr($to, 0, 190),
                'subject' => mb_substr($subject, 0, 255),
                'status' => $status,
                'error' => $error !== null ? mb_substr($error, 0, 500) : null,
            ]);
        } catch (\Throwable) {
            // log table missing (patch not imported) — never block sending
        }
    }

    /** @return list<array<string, mixed>> */
    public static function recentLog(int $limit = 100, string $status = ''): array
    {
        try {
            $sql = 'SELECT * FROM store_email_log' . ($status !== '' ? ' WHERE status = :s' : '') . ' ORDER BY id DESC LIMIT ' . max(1, min(500, $limit));
            $st = Database::connection()->prepare($sql);
            $st->execute($status !== '' ? ['s' => $status] : []);

            return $st->fetchAll();
        } catch (\Throwable) {
            return [];
        }
    }

    /** @return array{sent: int, failed: int, disabled: int} last 7 days */
    public static function stats(): array
    {
        $out = ['sent' => 0, 'failed' => 0, 'disabled' => 0];
        try {
            foreach (Database::connection()->query(
                'SELECT status, COUNT(*) n FROM store_email_log WHERE created_at >= NOW() - INTERVAL 7 DAY GROUP BY status'
            )->fetchAll() as $r) {
                $out[(string) $r['status']] = (int) $r['n'];
            }
        } catch (\Throwable) {
        }

        return $out;
    }
}
