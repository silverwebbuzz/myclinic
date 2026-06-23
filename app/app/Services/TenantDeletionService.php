<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\QueryBuilder;
use PDO;
use RuntimeException;

/**
 * Permanently removes a clinic and all portal data tied to it.
 * Intended for super-admin cleanup of demo / test tenants.
 */
final class TenantDeletionService
{
    public static function delete(int $clinicId): void
    {
        $tenant = QueryBuilder::table('tenants')->where('id', '=', $clinicId)->first();
        if ($tenant === null) {
            throw new RuntimeException('Clinic not found.');
        }

        $slug = (string) ($tenant['slug'] ?? '');
        $logoPath = (string) ($tenant['logo_path'] ?? '');

        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $pdo->exec('SET FOREIGN_KEY_CHECKS=0');

            self::deleteNestedRows($pdo, $clinicId);

            foreach (self::tablesWithClinicId($pdo) as $table) {
                if ($table === 'tenants') {
                    continue;
                }
                $stmt = $pdo->prepare("DELETE FROM `{$table}` WHERE clinic_id = :cid");
                $stmt->execute([':cid' => $clinicId]);
            }

            $stmt = $pdo->prepare('DELETE FROM tenants WHERE id = :id');
            $stmt->execute([':id' => $clinicId]);

            $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            try {
                $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
            } catch (\Throwable) {
                // ignore
            }
            throw $e;
        }

        self::purgeFiles($clinicId, $logoPath);
        if ($slug !== '') {
            RedisClient::del('tenant:slug:' . $slug);
        }
    }

    private static function deleteNestedRows(PDO $pdo, int $clinicId): void
    {
        $nested = [
            'DELETE ii FROM invoice_items ii INNER JOIN invoices i ON i.id = ii.invoice_id WHERE i.clinic_id = :cid',
            'DELETE dl FROM doctor_locations dl INNER JOIN doctor_profiles dp ON dp.id = dl.doctor_id WHERE dp.clinic_id = :cid',
            'DELETE lr FROM lab_results lr INNER JOIN lab_orders lo ON lo.id = lr.order_id WHERE lo.clinic_id = :cid',
            'DELETE psi FROM pharmacy_sale_items psi INNER JOIN pharmacy_sales ps ON ps.id = psi.sale_id WHERE ps.clinic_id = :cid',
            'DELETE pti FROM prescription_template_items pti INNER JOIN prescription_templates pt ON pt.id = pti.template_id WHERE pt.clinic_id = :cid',
        ];

        foreach ($nested as $sql) {
            try {
                $stmt = $pdo->prepare($sql);
                $stmt->execute([':cid' => $clinicId]);
            } catch (\Throwable) {
                // Table may not exist on older DBs — skip.
            }
        }
    }

    /** @return list<string> */
    private static function tablesWithClinicId(PDO $pdo): array
    {
        $db = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
        $stmt = $pdo->prepare(
            'SELECT TABLE_NAME
               FROM INFORMATION_SCHEMA.COLUMNS
              WHERE TABLE_SCHEMA = :db
                AND COLUMN_NAME = :col
              ORDER BY TABLE_NAME'
        );
        $stmt->execute([':db' => $db, ':col' => 'clinic_id']);

        return array_map(
            static fn (array $row): string => (string) $row['TABLE_NAME'],
            $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [],
        );
    }

    private static function purgeFiles(int $clinicId, string $logoPath): void
    {
        $base = dirname(__DIR__, 2);

        if ($logoPath !== '' && str_starts_with($logoPath, '/uploads/')) {
            $file = $base . '/public' . $logoPath;
            if (is_file($file)) {
                @unlink($file);
            }
        }

        $patientDir = $base . '/public/uploads/patients/' . $clinicId;
        if (is_dir($patientDir)) {
            self::rmTree($patientDir);
        }

        foreach (['prescriptions', 'saas_invoices', 'exports'] as $subdir) {
            $dir = $base . '/storage/' . $subdir . '/' . $clinicId;
            if (is_dir($dir)) {
                self::rmTree($dir);
            }
        }
    }

    private static function rmTree(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = scandir($dir) ?: [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                self::rmTree($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }
}
