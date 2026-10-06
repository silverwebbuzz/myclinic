<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Http\Request;
use App\Http\Response;
use App\Services\CsrfService;
use App\Support\AppUpdatePolicy;
use App\Support\View;
use PDO;

/**
 * /admin/app-versions — what the patient app's update check
 * (api/mobile/v1/app_version.php) tells each platform: latest build, minimum
 * supported build, store link and the dialog text. Super-admin only (route group).
 */
final class AppVersionAdminController
{
    public function index(Request $request): Response
    {
        return $this->render($request, self::load(), [], (string) ($request->query['message'] ?? ''));
    }

    public function save(Request $request): Response
    {
        if (!CsrfService::verify($request->post['_csrf'] ?? null)) {
            return Response::redirect('/admin/app-versions');
        }
        $values = [];
        $errors = [];
        foreach (array_keys(AppUpdatePolicy::PLATFORMS) as $p) {
            $in = [];
            foreach (AppUpdatePolicy::FIELDS as $f) {
                $in[$f] = $request->post[$p . '_' . $f] ?? '';
            }
            $res = AppUpdatePolicy::validate($p, $in);
            $errors = array_merge($errors, $res['errors']);
            foreach ($res['values'] as $f => $v) {
                $values[AppUpdatePolicy::key($p, $f)] = $v;
            }
        }
        if ($errors) {
            // Re-show the form with what was typed; nothing is saved.
            return $this->render($request, $values, $errors, '');
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'INSERT INTO platform_settings (setting_key, setting_value)
             VALUES (:k, :v)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
        );
        $pdo->beginTransaction();
        try {
            foreach ($values as $k => $v) {
                $stmt->execute([':k' => $k, ':v' => $v]);
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            error_log('[AppVersionAdmin] save failed: ' . $e->getMessage());

            return $this->render($request, $values, ['Could not save: ' . $e->getMessage()], '');
        }

        return Response::redirect('/admin/app-versions?message=saved');
    }

    /** @return array<string, string> */
    private static function load(): array
    {
        $out = array_fill_keys(AppUpdatePolicy::keys(), '');
        try {
            $in = implode(',', array_fill(0, count($out), '?'));
            $st = Database::connection()->prepare("SELECT setting_key, setting_value FROM platform_settings WHERE setting_key IN ($in)");
            $st->execute(array_keys($out));
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $out[(string) $r['setting_key']] = (string) ($r['setting_value'] ?? '');
            }
        } catch (\Throwable) {
            // platform_settings unreadable → empty form
        }

        return $out;
    }

    /**
     * @param array<string, string> $values platform_settings key => value (saved or as typed)
     * @param list<string> $errors
     */
    private function render(Request $request, array $values, array $errors, string $message): Response
    {
        // Preview: the exact JSON the app gets for a sample build. Uses the SAVED
        // settings (what the app sees right now), not unsaved form input.
        $saved = $errors ? self::load() : $values;
        $previews = [];
        foreach (array_keys(AppUpdatePolicy::PLATFORMS) as $p) {
            $latest = AppUpdatePolicy::posInt((string) ($saved[AppUpdatePolicy::key($p, 'latest_build')] ?? ''));
            $sample = AppUpdatePolicy::posInt((string) ($request->query[$p . '_build'] ?? ''))
                ?? ($latest !== null ? max(1, $latest - 1) : 1);
            $previews[$p] = [
                'build' => $sample,
                'json' => json_encode(['ok' => true] + AppUpdatePolicy::answer($saved, $p, $sample),
                    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ];
        }

        return Response::html(View::render('admin/app_versions', [
            'csrf' => CsrfService::token(),
            'values' => $values,
            'errors' => $errors,
            'saved' => $message === 'saved',
            'previews' => $previews,
        ]));
    }
}
