<?php

declare(strict_types=1);

namespace App\Services\Store;

use App\Core\Database;
use App\Core\QueryBuilder;

/**
 * HSN master: admin decides the GST rate for each HSN code; sellers only pick the code.
 * Matching is longest-prefix ("3004" covers "30049011"). If the master is empty or its
 * table isn't imported yet, the old free choice of rate still works (enforced() = false).
 */
final class HsnService
{
    /** @var list<array<string, mixed>>|null */
    private static ?array $cache = null;

    /** @return list<array<string, mixed>> active codes, longest first (for prefix matching) */
    public static function active(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }
        try {
            $rows = Database::connection()->query('SELECT * FROM store_hsn_codes WHERE is_active = 1 ORDER BY LENGTH(code) DESC, code')->fetchAll();
        } catch (\Throwable) {
            $rows = [];
        }

        return self::$cache = $rows;
    }

    public static function enforced(): bool
    {
        return self::active() !== [];
    }

    /** Best (longest-prefix) master row for a product HSN, or null. */
    public static function match(?string $hsn): ?array
    {
        $hsn = preg_replace('/\D/', '', (string) $hsn) ?? '';
        if ($hsn === '') {
            return null;
        }
        foreach (self::active() as $r) {   // longest codes first
            if (str_starts_with($hsn, (string) $r['code'])) {
                return $r;
            }
        }

        return null;
    }

    /** @return list<int> GST rates (bp) allowed for a master row */
    public static function allowedRates(array $row): array
    {
        $alt = array_values(array_filter(array_map('intval', explode(',', (string) ($row['alt_rates'] ?? ''))),
            static fn ($bp) => array_key_exists($bp, CatalogService::GST_RATES_BP)));

        return $alt ?: [(int) $row['gst_bp']];
    }

    /**
     * The rate a product must use. $chosen only matters when the code allows several.
     *
     * @return array{ok: bool, error?: string, gst_bp?: int, row?: array}
     */
    public static function resolve(string $hsn, ?int $chosen): array
    {
        $row = self::match($hsn);
        if ($row === null) {
            return ['ok' => false, 'error' => "HSN $hsn isn't in eClinicPro's HSN list. Pick a code from the list, or contact support to have it added (with the GST rate your CA confirms)."];
        }
        $allowed = self::allowedRates($row);
        if (count($allowed) === 1) {
            return ['ok' => true, 'gst_bp' => $allowed[0], 'row' => $row];
        }
        if ($chosen === null || !in_array($chosen, $allowed, true)) {
            return ['ok' => false, 'error' => 'For HSN ' . $row['code'] . ' choose the GST rate that applies to this product: '
                . implode(' or ', array_map(static fn ($bp) => ($bp / 100) . '%', $allowed)) . '.'];
        }

        return ['ok' => true, 'gst_bp' => $chosen, 'row' => $row];
    }

    /** For the product form: [{code, description, rates: [bp…]}] */
    public static function forForm(): array
    {
        $out = [];
        foreach (self::active() as $r) {
            $out[] = ['code' => (string) $r['code'], 'description' => (string) $r['description'], 'rates' => self::allowedRates($r)];
        }
        usort($out, static fn ($a, $b) => strcmp($a['code'], $b['code']));

        return $out;
    }

    /** @return list<array<string, mixed>> every code (admin list) */
    public static function all(): array
    {
        return Database::connection()->query('SELECT * FROM store_hsn_codes ORDER BY code')->fetchAll();
    }

    /**
     * Add or update a code. A rate change is applied to every product using it
     * (single-rate codes only); multi-rate products that no longer fit show up as mismatches.
     *
     * @return array{ok: bool, error?: string, updated?: int}
     */
    public static function save(?int $id, array $in, int $adminId): array
    {
        $code = preg_replace('/\D/', '', (string) ($in['code'] ?? '')) ?? '';
        if (!preg_match('/^\d{4,8}$/', $code)) {
            return ['ok' => false, 'error' => 'HSN code must be 4–8 digits.'];
        }
        $desc = mb_substr(trim((string) ($in['description'] ?? '')), 0, 300);
        if ($desc === '') {
            return ['ok' => false, 'error' => 'Add a description.'];
        }
        $bp = (int) ($in['gst_bp'] ?? -1);
        if (!array_key_exists($bp, CatalogService::GST_RATES_BP)) {
            return ['ok' => false, 'error' => 'Choose a valid GST rate.'];
        }
        $alt = array_values(array_unique(array_filter(array_map('intval', (array) ($in['alt_rates'] ?? [])),
            static fn ($r) => array_key_exists($r, CatalogService::GST_RATES_BP))));
        sort($alt);
        if ($alt && !in_array($bp, $alt, true)) {
            $alt[] = $bp;
            sort($alt);
        }
        $row = [
            'code' => $code, 'description' => $desc, 'gst_bp' => $bp,
            'alt_rates' => count($alt) > 1 ? implode(',', $alt) : null,
            'notes' => mb_substr(trim((string) ($in['notes'] ?? '')), 0, 300) ?: null,
            'is_active' => !empty($in['is_active']) ? 1 : 0,
            'updated_by' => $adminId,
        ];
        $dup = QueryBuilder::table('store_hsn_codes')->where('code', '=', $code)->first();
        if ($dup !== null && (int) $dup['id'] !== (int) $id) {
            return ['ok' => false, 'error' => "HSN $code is already in the list."];
        }
        $before = $id ? QueryBuilder::table('store_hsn_codes')->where('id', '=', $id)->first() : null;
        if ($id && $before === null) {
            return ['ok' => false, 'error' => 'Not found.'];
        }
        if ($id) {
            QueryBuilder::table('store_hsn_codes')->where('id', '=', $id)->update($row);
        } else {
            $id = QueryBuilder::table('store_hsn_codes')->insert($row);
        }
        StoreAudit::log('hsn.save', 'hsn', (int) $id, $before, $row);
        self::$cache = null;

        return ['ok' => true, 'updated' => self::applyRates()];
    }

    /**
     * Bring products in line with the master: single-rate codes force the rate.
     * Returns how many products changed. Old orders are untouched (their lines keep
     * the rate they were sold at).
     */
    public static function applyRates(): int
    {
        if (!self::enforced()) {
            return 0;
        }
        $pdo = Database::connection();
        $rows = $pdo->query("SELECT id, hsn_code, gst_bp FROM store_products WHERE deleted_at IS NULL AND hsn_code IS NOT NULL AND hsn_code <> ''")->fetchAll();
        $upd = $pdo->prepare('UPDATE store_products SET gst_bp = :bp WHERE id = :id');
        $n = 0;
        foreach ($rows as $p) {
            $m = self::match((string) $p['hsn_code']);
            if ($m === null) {
                continue;
            }
            $allowed = self::allowedRates($m);
            if (count($allowed) === 1 && (int) $p['gst_bp'] !== $allowed[0]) {
                $upd->execute(['bp' => $allowed[0], 'id' => (int) $p['id']]);
                $n++;
            }
        }
        if ($n > 0) {
            StoreAudit::log('hsn.apply_rates', 'hsn', null, null, ['products_updated' => $n]);
        }

        return $n;
    }

    /**
     * Products whose HSN isn't in the master, or whose rate isn't allowed for it.
     *
     * @return list<array<string, mixed>>
     */
    public static function mismatches(?int $vendorId = null, int $limit = 200): array
    {
        if (!self::enforced()) {
            return [];
        }
        $sql = "SELECT p.id, p.name, p.hsn_code, p.gst_bp, p.status, p.vendor_id, v.display_name AS vendor_name
                  FROM store_products p JOIN store_vendors v ON v.id = p.vendor_id
                 WHERE p.deleted_at IS NULL AND p.status NOT IN ('archived')" . ($vendorId !== null ? ' AND p.vendor_id = :v' : '') . ' ORDER BY p.id';
        $st = Database::connection()->prepare($sql);
        $st->execute($vendorId !== null ? ['v' => $vendorId] : []);
        $out = [];
        foreach ($st->fetchAll() as $p) {
            $m = self::match((string) $p['hsn_code']);
            if ($m === null) {
                $p['problem'] = empty($p['hsn_code']) ? 'No HSN code' : 'HSN not in the list';
            } elseif (!in_array((int) $p['gst_bp'], self::allowedRates($m), true)) {
                $p['problem'] = 'GST ' . ((int) $p['gst_bp'] / 100) . '% but HSN ' . $m['code'] . ' allows '
                    . implode(' / ', array_map(static fn ($bp) => ($bp / 100) . '%', self::allowedRates($m)));
            } else {
                continue;
            }
            $out[] = $p;
            if (count($out) >= $limit) {
                break;
            }
        }

        return $out;
    }
}
