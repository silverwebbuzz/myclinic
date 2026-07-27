<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use PDO;

/**
 * Permanently removes lab catalog products and everything hanging off them.
 *
 * Why this exists: the catalog was bulk-seeded from the Thyrocare master feed,
 * but a given merchant account is only enabled for a SUBSET of those products.
 * Anything the account can't actually fulfil has to leave the catalog entirely
 * (not just be deactivated) so it can never be searched, linked or booked.
 *
 * All child tables (parameters, categories, pricing, images, aliases) declare
 * ON DELETE CASCADE against lab_products, so deleting the parent row is enough
 * for them. What cascade does NOT do is clean up the shared dictionaries:
 * lab_parameters and lab_categories are M:N targets, so a row there can be left
 * behind referencing nothing once its last product goes. We sweep those after.
 *
 * Mirrors TenantDeletionService (the clinic-delete flow) in shape: one static
 * entry point, one transaction, a report of what was removed.
 */
final class LabProductDeletionService
{
    /**
     * Delete products by id.
     *
     * @param list<int> $ids
     * @return array{products:int, pricing:int, parameters:int, categories:int, orphan_parameters:int, orphan_categories:int, names:list<string>}
     */
    public static function deleteByIds(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $i): bool => $i > 0)));
        if ($ids === []) {
            return self::emptyReport();
        }

        $pdo = Database::connection();
        $in  = implode(',', $ids); // ints only — sanitised above

        // Capture what we're about to destroy so the admin gets a real receipt
        // instead of just a row count.
        $names = $pdo->query("SELECT name FROM lab_products WHERE id IN ($in) ORDER BY name")
            ->fetchAll(PDO::FETCH_COLUMN) ?: [];
        if ($names === []) {
            return self::emptyReport();
        }

        $counts = [
            'pricing'    => (int) $pdo->query("SELECT COUNT(*) FROM lab_product_pricing    WHERE product_id IN ($in)")->fetchColumn(),
            'parameters' => (int) $pdo->query("SELECT COUNT(*) FROM lab_product_parameters WHERE product_id IN ($in)")->fetchColumn(),
            'categories' => (int) $pdo->query("SELECT COUNT(*) FROM lab_product_categories WHERE product_id IN ($in)")->fetchColumn(),
        ];

        $pdo->beginTransaction();
        try {
            // Explicit child deletes first. Cascade would handle these, but being
            // explicit keeps the operation correct even if a future deploy loses
            // the FKs (e.g. a table re-created without constraints by an import).
            foreach (['lab_product_pricing', 'lab_product_parameters', 'lab_product_categories',
                      'lab_product_images', 'lab_product_aliases'] as $child) {
                $pdo->exec("DELETE FROM `$child` WHERE product_id IN ($in)");
            }

            $deleted = $pdo->exec("DELETE FROM lab_products WHERE id IN ($in)");

            // Sweep dictionary rows that no surviving product references. These
            // are pure lookup atoms — an unreferenced one is dead weight that
            // still shows up in admin category dropdowns and parameter counts.
            $orphanParams = $pdo->exec(
                'DELETE par FROM lab_parameters par
                  WHERE NOT EXISTS (
                      SELECT 1 FROM lab_product_parameters lpp WHERE lpp.parameter_id = par.id
                  )'
            );
            $orphanCats = $pdo->exec(
                'DELETE c FROM lab_categories c
                  WHERE NOT EXISTS (
                      SELECT 1 FROM lab_product_categories lpc WHERE lpc.category_id = c.id
                  )'
            );

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        return [
            'products'          => (int) $deleted,
            'pricing'           => $counts['pricing'],
            'parameters'        => $counts['parameters'],
            'categories'        => $counts['categories'],
            'orphan_parameters' => (int) $orphanParams,
            'orphan_categories' => (int) $orphanCats,
            'names'             => array_map('strval', $names),
        ];
    }

    /**
     * Resolve a pasted list of product names/codes to ids, reporting which
     * lines matched nothing so the admin can fix typos instead of silently
     * deleting 28 of 33 intended rows.
     *
     * Matching is case-insensitive on the exact trimmed name, then falls back
     * to `code` / `thyrocare_code`. Deliberately NOT a LIKE match: a partial
     * match on a name like "COVID ANTIBODY - 1" could sweep up siblings the
     * merchant does still sell.
     *
     * @param list<string> $lines
     * @return array{matched: array<string, list<array{id:int,name:string}>>, unmatched: list<string>}
     */
    public static function resolveNames(array $lines): array
    {
        $terms = [];
        foreach ($lines as $line) {
            $t = trim(preg_replace('/\s+/', ' ', (string) $line) ?? '');
            if ($t !== '') {
                $terms[strtoupper($t)] = $t;
            }
        }
        if ($terms === []) {
            return ['matched' => [], 'unmatched' => []];
        }

        $pdo = Database::connection();
        $placeholders = implode(',', array_fill(0, count($terms), '?'));
        $values = array_values($terms);

        // Normalise runs of whitespace on BOTH sides. The Thyrocare feed has
        // rows with doubled spaces (e.g. "NEONATAL THYROID STIMULATING HORMONE #
        // (TSH)"), which a copy-paste from a report or spreadsheet collapses to
        // one — without this they'd never match and would look "already gone".
        // REGEXP_REPLACE needs MySQL 8; the app already targets it (JSON cols).
        $norm = "UPPER(TRIM(REGEXP_REPLACE(%s, '[[:space:]]+', ' ')))";
        $nName = sprintf($norm, '`name`');
        $nCode = sprintf($norm, '`code`');
        $nThyro = sprintf($norm, "COALESCE(`thyrocare_code`,'')");

        // One placeholder per position — the app runs native prepares, where a
        // reused named placeholder throws HY093. Positional params + a repeated
        // value list keeps that safe.
        $stmt = $pdo->prepare(
            "SELECT id, name, code, thyrocare_code
               FROM lab_products
              WHERE $nName IN ($placeholders)
                 OR $nCode IN ($placeholders)
                 OR $nThyro IN ($placeholders)"
        );
        $stmt->execute(array_merge($values, $values, $values));
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $matched = [];
        foreach ($rows as $r) {
            foreach ([$r['name'], $r['code'], $r['thyrocare_code'] ?? ''] as $field) {
                $key = strtoupper(trim(preg_replace('/\s+/', ' ', (string) $field) ?? ''));
                if ($key !== '' && isset($terms[$key])) {
                    $matched[$terms[$key]][] = ['id' => (int) $r['id'], 'name' => (string) $r['name']];
                    break;
                }
            }
        }

        $unmatched = [];
        foreach ($terms as $original) {
            if (!isset($matched[$original])) {
                $unmatched[] = $original;
            }
        }

        return ['matched' => $matched, 'unmatched' => $unmatched];
    }

    /** @return array{products:int, pricing:int, parameters:int, categories:int, orphan_parameters:int, orphan_categories:int, names:list<string>} */
    private static function emptyReport(): array
    {
        return [
            'products' => 0, 'pricing' => 0, 'parameters' => 0, 'categories' => 0,
            'orphan_parameters' => 0, 'orphan_categories' => 0, 'names' => [],
        ];
    }
}
