<?php

declare(strict_types=1);

namespace App\Services\Store;

use App\Core\Database;
use App\Core\QueryBuilder;

/**
 * Read side of the store taxonomy (departments → subcategories, brands, health
 * goals) plus the compliance gate: which subcategories a given seller may list in.
 */
final class CatalogService
{
    public const GST_RATES_BP = [0 => '0%', 500 => '5%', 1200 => '12%', 1800 => '18%', 2800 => '28%'];

    public const CLASS_LABELS = [
        'general' => 'General',
        'cosmetic' => 'Cosmetic',
        'food_fssai' => 'Food (FSSAI)',
        'nutraceutical_fssai' => 'Nutraceutical (FSSAI)',
        'ayush' => 'AYUSH',
        'medical_device' => 'Medical device',
        'drug_restricted' => 'Drug (restricted)',
    ];

    /**
     * Departments with their active subcategories, for pickers and admin.
     *
     * @return list<array<string, mixed>> each department has a 'subs' list
     */
    public static function tree(bool $activeOnly = true): array
    {
        $rows = Database::connection()->query(
            'SELECT * FROM store_categories'
            . ($activeOnly ? ' WHERE is_active = 1' : '')
            . ' ORDER BY parent_id, sort_order, name'
        )->fetchAll();

        $depts = [];
        foreach ($rows as $r) {
            if ((int) $r['parent_id'] === 0) {
                $depts[(int) $r['id']] = $r + ['subs' => []];
            }
        }
        foreach ($rows as $r) {
            $pid = (int) $r['parent_id'];
            if ($pid !== 0 && isset($depts[$pid])) {
                $depts[$pid]['subs'][] = $r;
            }
        }
        uasort($depts, static fn ($a, $b) => (int) $a['sort_order'] <=> (int) $b['sort_order']);

        return array_values($depts);
    }

    public static function category(int $id): ?array
    {
        return QueryBuilder::table('store_categories')->where('id', '=', $id)->first();
    }

    /**
     * Document types this seller has APPROVED and unexpired, e.g. ['fssai_license' => 'LIC123'].
     *
     * @return array<string, string> doc_type => doc_number
     */
    public static function vendorLicences(int $vendorId): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT doc_type, doc_number FROM store_vendor_documents
              WHERE vendor_id = :v AND status = 'approved'
                AND (valid_until IS NULL OR valid_until >= CURDATE())
              ORDER BY id DESC"
        );
        $stmt->execute(['v' => $vendorId]);
        $out = [];
        foreach ($stmt->fetchAll() as $r) {
            $out[(string) $r['doc_type']] ??= (string) ($r['doc_number'] ?? '');
        }

        return $out;
    }

    /**
     * Can this seller list in this subcategory? Returns null when allowed, else the reason.
     *
     * @param array<string, string> $licences from vendorLicences()
     */
    public static function listingBlockReason(array $category, array $licences): ?string
    {
        if ((int) $category['parent_id'] === 0) {
            return 'Choose a subcategory, not a department.';
        }
        if (empty($category['is_active'])) {
            return 'This category is not open for listing.';
        }
        if (($category['listing_mode'] ?? '') === 'blocked') {
            return 'This category is not sold on the store (' . ($category['review_reason'] ?: 'restricted') . ').';
        }
        $doc = (string) ($category['required_vendor_doc'] ?? '');
        if ($doc !== '' && !array_key_exists($doc, $licences)) {
            $label = VendorService::DOC_TYPES[$doc] ?? $doc;

            return "Needs an approved $label on file. Upload it under Documents.";
        }

        return null;
    }

    /** @return list<array<string, mixed>> */
    public static function brands(bool $activeOnly = true): array
    {
        return Database::connection()->query(
            'SELECT * FROM store_brands' . ($activeOnly ? ' WHERE is_active = 1' : '') . ' ORDER BY name'
        )->fetchAll();
    }

    /** Finds a brand by name (case-insensitive) or creates it. */
    public static function brandIdFor(string $name): ?int
    {
        $name = trim(preg_replace('/\s+/', ' ', $name) ?? '');
        if ($name === '') {
            return null;
        }
        $slug = VendorService::slugify($name);
        if ($slug === '') {
            return null;
        }
        $existing = QueryBuilder::table('store_brands')->where('slug', '=', $slug)->first();
        if ($existing !== null) {
            return (int) $existing['id'];
        }
        $id = QueryBuilder::table('store_brands')->insert(['slug' => $slug, 'name' => mb_substr($name, 0, 160)]);
        StoreAudit::log('brand.create', 'brand', $id, null, ['name' => $name]);

        return $id;
    }

    /** @return list<array<string, mixed>> */
    public static function concerns(): array
    {
        return Database::connection()->query(
            'SELECT * FROM store_concerns WHERE is_active = 1 ORDER BY sort_order, name'
        )->fetchAll();
    }
}
