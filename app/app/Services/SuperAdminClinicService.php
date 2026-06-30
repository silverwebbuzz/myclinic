<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\QueryBuilder;
use PDO;

final class SuperAdminClinicService
{
    /**
     * @return array{
     *   config: array<string, mixed>|null,
     *   users: list<array<string, mixed>>,
     *   doctors: list<array<string, mixed>>,
     *   counts: array<string, int>,
     *   working_hours: array<string, mixed>
     * }|null
     */
    public static function overview(int $clinicId): ?array
    {
        $tenant = QueryBuilder::table('tenants')->where('id', '=', $clinicId)->first();
        if ($tenant === null) {
            return null;
        }

        $config = OnboardingService::specialtyConfig($clinicId);
        $workingHours = $config['working_hours'] ?? null;
        if (is_string($workingHours)) {
            $workingHours = json_decode($workingHours, true) ?: [];
        }
        if (!is_array($workingHours)) {
            $workingHours = [];
        }

        $pdo = Database::connection();

        $users = QueryBuilder::table('users')
            ->where('clinic_id', '=', $clinicId)
            ->orderBy('role', 'ASC')
            ->orderBy('name', 'ASC')
            ->get();

        $stmt = $pdo->prepare(
            'SELECT dp.*, u.name AS user_name, u.email AS user_email
               FROM doctor_profiles dp
          LEFT JOIN users u ON u.id = dp.user_id
              WHERE dp.clinic_id = :cid
              ORDER BY u.name ASC'
        );
        $stmt->execute([':cid' => $clinicId]);
        $doctors = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $counts = [
            'patients' => QueryBuilder::table('patients')->where('clinic_id', '=', $clinicId)->count(),
            'appointments' => QueryBuilder::table('appointments')->where('clinic_id', '=', $clinicId)->count(),
            'visits' => QueryBuilder::table('visits')->where('clinic_id', '=', $clinicId)->count(),
            'invoices' => QueryBuilder::table('invoices')->where('clinic_id', '=', $clinicId)->count(),
            'users' => count($users),
        ];

        return [
            'config' => $config,
            'directory_listing' => ClinicSettingsService::publicListing($clinicId),
            'users' => $users,
            'doctors' => $doctors,
            'counts' => $counts,
            'working_hours' => $workingHours,
        ];
    }
}
