<?php

declare(strict_types=1);

namespace App\Services\Store;

use App\Core\Database;
use App\Core\QueryBuilder;

/**
 * Shopping cart. Guests get a cart keyed by a random token (cookie `ecp_cart`);
 * logged-in customers (patient identities) get one cart keyed by identity_id.
 * When a guest logs in, their guest cart is merged into their own.
 *
 * Carts only hold variant ids + quantities. Prices, stock and availability
 * are ALWAYS re-read (items()), and checkout re-prices server-side.
 */
final class CartService
{
    public const MAX_QTY_PER_LINE = 10;
    public const MAX_LINES = 30;
    private const TTL_DAYS = 30;

    /**
     * Find (optionally create) the cart for this visitor.
     *
     * @return array{cart: ?array, new_token: ?string} new_token = cookie to set
     */
    public static function resolve(?int $identityId, ?string $token, bool $create): array
    {
        $token = ($token !== null && preg_match('/^[a-f0-9]{64}$/', $token)) ? $token : null;
        $guest = $token !== null
            ? QueryBuilder::table('store_carts')->where('token', '=', $token)->where('identity_id', 'IS')->first()
            : null;

        if ($identityId !== null && $identityId > 0) {
            $own = QueryBuilder::table('store_carts')->where('identity_id', '=', $identityId)->orderBy('id', 'DESC')->first();
            if ($own === null && $guest !== null) {
                // Claim the guest cart.
                QueryBuilder::table('store_carts')->where('id', '=', (int) $guest['id'])->update(['identity_id' => $identityId]);
                $guest['identity_id'] = $identityId;

                return ['cart' => $guest, 'new_token' => null];
            }
            if ($own !== null && $guest !== null && (int) $guest['id'] !== (int) $own['id']) {
                self::merge((int) $guest['id'], (int) $own['id']);
            }
            if ($own === null && $create) {
                $own = self::create($identityId);

                return ['cart' => $own, 'new_token' => $own['token']];
            }

            return ['cart' => $own, 'new_token' => null];
        }

        if ($guest === null && $create) {
            $guest = self::create(null);

            return ['cart' => $guest, 'new_token' => $guest['token']];
        }

        return ['cart' => $guest, 'new_token' => null];
    }

    /**
     * Lines with live product data + a per-line problem (null when buyable).
     *
     * @return list<array<string, mixed>>
     */
    public static function items(int $cartId): array
    {
        $st = Database::connection()->prepare(
            "SELECT ci.id AS item_id, ci.qty, ci.price_seen_paise,
                    sv.id AS variant_id, sv.sku, sv.title AS variant_title, sv.mrp_paise, sv.price_paise,
                    sv.stock_qty, sv.reserved_qty, sv.is_active AS variant_active, sv.weight_g,
                    p.id AS product_id, p.slug, p.name, p.status AS product_status, p.deleted_at, p.gst_bp, p.hsn_code,
                    p.category_id, pc.parent_id AS dept_id, pc.is_active AS cat_active, pc.listing_mode, pc.no_promotion,
                    v.id AS vendor_id, v.display_name AS vendor_name, v.slug AS vendor_slug, v.status AS vendor_status,
                    (SELECT i.path FROM store_product_images i WHERE i.product_id = p.id ORDER BY i.sort_order, i.id LIMIT 1) AS cover
               FROM store_cart_items ci
               JOIN store_product_variants sv ON sv.id = ci.variant_id
               JOIN store_products p ON p.id = sv.product_id
               JOIN store_vendors v ON v.id = p.vendor_id
               LEFT JOIN store_categories pc ON pc.id = p.category_id
              WHERE ci.cart_id = :c
              ORDER BY v.display_name, ci.id"
        );
        $st->execute(['c' => $cartId]);
        $rows = $st->fetchAll();
        foreach ($rows as &$r) {
            $available = max(0, (int) $r['stock_qty'] - (int) $r['reserved_qty']);
            $r['available_qty'] = $available;
            $r['problem'] = null;
            if (!self::sellable($r)) {
                $r['problem'] = 'No longer available';
            } elseif ($available <= 0) {
                $r['problem'] = 'Out of stock';
            } elseif ($available < (int) $r['qty']) {
                $r['problem'] = 'Only ' . $available . ' left: reduce the quantity';
            }
            $r['price_changed'] = (int) $r['price_seen_paise'] !== (int) $r['price_paise'];
        }
        unset($r);

        return $rows;
    }

    /** Same visibility rule as the storefront (store/_lib.php store_visible_sql()). */
    public static function sellable(array $r): bool
    {
        return ($r['product_status'] ?? '') === 'live'
            && empty($r['deleted_at'])
            && ($r['vendor_status'] ?? '') === 'approved'
            && (int) ($r['variant_active'] ?? 0) === 1
            && (int) ($r['cat_active'] ?? 0) === 1
            && ($r['listing_mode'] ?? '') !== 'blocked';
    }

    /** @return array{ok: bool, error?: string, qty?: int} */
    public static function add(int $cartId, int $variantId, int $qty): array
    {
        $qty = max(1, min(self::MAX_QTY_PER_LINE, $qty));
        $row = self::variantRow($variantId);
        if ($row === null || !self::sellable($row)) {
            return ['ok' => false, 'error' => 'This product is not available right now.'];
        }
        $available = max(0, (int) $row['stock_qty'] - (int) $row['reserved_qty']);
        if ($available <= 0) {
            return ['ok' => false, 'error' => 'Sorry, this is out of stock.'];
        }
        $existing = QueryBuilder::table('store_cart_items')->where('cart_id', '=', $cartId)->where('variant_id', '=', $variantId)->first();
        if ($existing === null && QueryBuilder::table('store_cart_items')->where('cart_id', '=', $cartId)->count() >= self::MAX_LINES) {
            return ['ok' => false, 'error' => 'Your cart is full. Check out or remove some items first.'];
        }
        $newQty = min(self::MAX_QTY_PER_LINE, $available, ($existing !== null ? (int) $existing['qty'] : 0) + $qty);
        if ($existing !== null) {
            QueryBuilder::table('store_cart_items')->where('id', '=', (int) $existing['id'])
                ->update(['qty' => $newQty, 'price_seen_paise' => (int) $row['price_paise']]);
        } else {
            QueryBuilder::table('store_cart_items')->insert([
                'cart_id' => $cartId, 'variant_id' => $variantId, 'qty' => $newQty, 'price_seen_paise' => (int) $row['price_paise'],
            ]);
        }
        self::touch($cartId);

        return ['ok' => true, 'qty' => $newQty];
    }

    /** qty 0 removes the line. */
    public static function setQty(int $cartId, int $itemId, int $qty): bool
    {
        $item = QueryBuilder::table('store_cart_items')->where('id', '=', $itemId)->where('cart_id', '=', $cartId)->first();
        if ($item === null) {
            return false;
        }
        if ($qty <= 0) {
            QueryBuilder::table('store_cart_items')->where('id', '=', $itemId)->delete();
        } else {
            $row = self::variantRow((int) $item['variant_id']);
            $cap = $row !== null ? max(1, (int) $row['stock_qty'] - (int) $row['reserved_qty']) : 1;
            QueryBuilder::table('store_cart_items')->where('id', '=', $itemId)->update([
                'qty' => max(1, min(self::MAX_QTY_PER_LINE, $cap, $qty)),
                // Seeing the cart again = customer has seen the current price.
                'price_seen_paise' => $row !== null ? (int) $row['price_paise'] : (int) $item['price_seen_paise'],
            ]);
        }
        self::touch($cartId);

        return true;
    }

    /** Accept current prices (clears "price changed" notices). */
    public static function acknowledgePrices(int $cartId): void
    {
        Database::connection()->prepare(
            'UPDATE store_cart_items ci JOIN store_product_variants sv ON sv.id = ci.variant_id
                SET ci.price_seen_paise = sv.price_paise WHERE ci.cart_id = :c'
        )->execute(['c' => $cartId]);
    }

    public static function clear(int $cartId): void
    {
        QueryBuilder::table('store_cart_items')->where('cart_id', '=', $cartId)->delete();
    }

    public static function count(int $cartId): int
    {
        $st = Database::connection()->prepare('SELECT COALESCE(SUM(qty), 0) FROM store_cart_items WHERE cart_id = :c');
        $st->execute(['c' => $cartId]);

        return (int) $st->fetchColumn();
    }

    // ---- internals ------------------------------------------------------------

    private static function create(?int $identityId): array
    {
        $token = bin2hex(random_bytes(32));
        $id = QueryBuilder::table('store_carts')->insert([
            'token' => $token,
            'identity_id' => $identityId,
            'expires_at' => date('Y-m-d H:i:s', time() + self::TTL_DAYS * 86400),
        ]);

        return QueryBuilder::table('store_carts')->where('id', '=', $id)->first() ?? ['id' => $id, 'token' => $token, 'identity_id' => $identityId];
    }

    /** Move guest lines into the customer's cart (quantities add up, capped), then drop the guest cart. */
    private static function merge(int $fromId, int $toId): void
    {
        $pdo = Database::connection();
        $pdo->prepare(
            'INSERT INTO store_cart_items (cart_id, variant_id, qty, price_seen_paise)
             SELECT :to, g.variant_id, g.qty, g.price_seen_paise FROM store_cart_items g WHERE g.cart_id = :from
             ON DUPLICATE KEY UPDATE qty = LEAST(store_cart_items.qty + VALUES(qty), ' . self::MAX_QTY_PER_LINE . ')'
        )->execute(['to' => $toId, 'from' => $fromId]);
        $pdo->prepare('DELETE FROM store_cart_items WHERE cart_id = :c')->execute(['c' => $fromId]);
        $pdo->prepare('DELETE FROM store_carts WHERE id = :c')->execute(['c' => $fromId]);
        self::touch($toId);
    }

    private static function touch(int $cartId): void
    {
        QueryBuilder::table('store_carts')->where('id', '=', $cartId)
            ->update(['expires_at' => date('Y-m-d H:i:s', time() + self::TTL_DAYS * 86400)]);
    }

    private static function variantRow(int $variantId): ?array
    {
        $st = Database::connection()->prepare(
            'SELECT sv.id, sv.price_paise, sv.stock_qty, sv.reserved_qty, sv.is_active AS variant_active,
                    p.status AS product_status, p.deleted_at, v.status AS vendor_status,
                    pc.is_active AS cat_active, pc.listing_mode
               FROM store_product_variants sv
               JOIN store_products p ON p.id = sv.product_id
               JOIN store_vendors v ON v.id = p.vendor_id
               LEFT JOIN store_categories pc ON pc.id = p.category_id
              WHERE sv.id = :v'
        );
        $st->execute(['v' => $variantId]);

        return $st->fetch() ?: null;
    }
}
