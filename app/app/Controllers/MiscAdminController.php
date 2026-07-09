<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Http\Request;
use App\Http\Response;
use App\Services\CsrfService;
use App\Support\View;
use PDO;

final class MiscAdminController
{
    public function index(Request $request): Response
    {
        $settings = [];
        try {
            foreach (Database::connection()
                ->query('SELECT setting_key, setting_value FROM platform_settings')
                ->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $settings[(string) $row['setting_key']] = (string) ($row['setting_value'] ?? '');
            }
        } catch (\Throwable) {
            // Ignore and use defaults.
        }

        return Response::html(View::render('admin/misc', [
            'csrf' => CsrfService::token(),
            'message' => $request->query['message'] ?? null,
            'patientDailyBookingLimit' => (int) ($settings['patient_daily_booking_limit'] ?? 0),
        ]));
    }

    public function save(Request $request): Response
    {
        if (!CsrfService::verify($request->post['_csrf'] ?? null)) {
            return Response::redirect('/admin/misc');
        }

        $limit = max(0, (int) ($request->post['patient_daily_booking_limit'] ?? 0));
        $stmt = Database::connection()->prepare(
            'INSERT INTO platform_settings (setting_key, setting_value)
             VALUES (:k, :v)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
        );
        $stmt->execute([':k' => 'patient_daily_booking_limit', ':v' => (string) $limit]);

        return Response::redirect('/admin/misc?message=saved');
    }
}
