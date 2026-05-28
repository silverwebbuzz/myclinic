<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\RequestContext;
use App\Http\Request;
use App\Http\Response;
use App\Services\CsrfService;
use App\Services\WhatsAppService;
use App\Support\MessagingSettings;
use App\Support\View;
use PDO;

/**
 * MessagingAdminController — /admin/messaging (super-admin only).
 *
 * One page, sections: Connection (creds + test send), Templates (registry),
 * Rules (per audience/event/tier channel + caps), Quota (base allowance),
 * Log (recent sends + delivery + fallback monitor).
 */
final class MessagingAdminController
{
    public function index(Request $request): Response
    {
        $pdo = Database::connection();

        $settings = [];
        try {
            foreach ($pdo->query('SELECT setting_key, setting_value, is_secret FROM platform_settings')->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $settings[$r['setting_key']] = $r;
            }
        } catch (\Throwable $e) {}

        $templates = self::safeAll($pdo, 'SELECT * FROM wa_templates ORDER BY template_key');
        $rules     = self::safeAll($pdo, 'SELECT * FROM messaging_rules ORDER BY audience, event_key, plan_tier');
        $log       = self::safeAll($pdo, 'SELECT * FROM notifications ORDER BY id DESC LIMIT 50');

        // Webhook URL to paste into Meta.
        $base = rtrim($_ENV['APP_URL'] ?? 'https://app.eclinicpro.com', '/');
        $webhookUrl = $base . '/webhooks/whatsapp';

        return Response::html(View::render('admin/messaging', [
            'admin' => RequestContext::superAdmin(),
            'csrf' => CsrfService::token(),
            'settings' => $settings,
            'templates' => $templates,
            'rules' => $rules,
            'log' => $log,
            'webhookUrl' => $webhookUrl,
            'message' => $request->query['message'] ?? null,
        ]));
    }

    /** POST /admin/messaging/connection — save creds + toggles. */
    public function saveConnection(Request $request): Response
    {
        if (!CsrfService::verify($request->post['_csrf'] ?? null)) {
            return Response::redirect('/admin/messaging');
        }
        $keys = [
            'messaging_enabled', 'wa_access_token', 'wa_phone_number_id', 'wa_business_id',
            'wa_webhook_verify_token', 'wa_app_secret', 'sms_provider', 'sms_auth_key',
            'sms_sender_id', 'quota_whatsapp_base', 'quota_sms_base',
            'messaging_quiet_start', 'messaging_quiet_end', 'messaging_global_monthly_cap',
        ];
        foreach ($keys as $k) {
            if (array_key_exists($k, $request->post)) {
                $val = trim((string) $request->post[$k]);
                // Don't overwrite a secret with the masked placeholder.
                if ($val === '••••••') {
                    continue;
                }
                MessagingSettings::set($k, $val);
            }
        }
        // Checkbox: messaging_enabled is '1' only when present.
        MessagingSettings::set('messaging_enabled', isset($request->post['messaging_enabled']) ? '1' : '0');

        return Response::redirect('/admin/messaging?message=connection_saved');
    }

    /** POST /admin/messaging/template/{id} — update one template. */
    public function saveTemplate(Request $request, string $id): Response
    {
        if (!CsrfService::verify($request->post['_csrf'] ?? null)) {
            return Response::redirect('/admin/messaging');
        }
        $stmt = Database::connection()->prepare(
            'UPDATE wa_templates
                SET meta_name = :mn, language = :lang, category = :cat,
                    body_text = :body, sms_fallback_text = :sms, status = :st, is_active = :act
              WHERE id = :id'
        );
        $stmt->execute([
            ':mn' => trim((string) ($request->post['meta_name'] ?? '')),
            ':lang' => trim((string) ($request->post['language'] ?? 'en')),
            ':cat' => in_array($request->post['category'] ?? '', ['utility','marketing','authentication'], true)
                       ? $request->post['category'] : 'utility',
            ':body' => (string) ($request->post['body_text'] ?? ''),
            ':sms' => trim((string) ($request->post['sms_fallback_text'] ?? '')) ?: null,
            ':st' => in_array($request->post['status'] ?? '', ['draft','submitted','approved','rejected','paused'], true)
                      ? $request->post['status'] : 'draft',
            ':act' => isset($request->post['is_active']) ? 1 : 0,
            ':id' => (int) $id,
        ]);
        return Response::redirect('/admin/messaging?message=template_saved#templates');
    }

    /** POST /admin/messaging/rule/{id} — update one rule row. */
    public function saveRule(Request $request, string $id): Response
    {
        if (!CsrfService::verify($request->post['_csrf'] ?? null)) {
            return Response::redirect('/admin/messaging');
        }
        $intOrNull = static fn ($v) => ($v === '' || $v === null) ? null : (int) $v;
        $stmt = Database::connection()->prepare(
            'UPDATE messaging_rules
                SET channel = :ch, per_day_cap = :d, per_week_cap = :w,
                    per_month_cap = :m, is_active = :act
              WHERE id = :id'
        );
        $stmt->execute([
            ':ch' => in_array($request->post['channel'] ?? '', ['whatsapp','sms','push','off'], true)
                      ? $request->post['channel'] : 'off',
            ':d' => $intOrNull($request->post['per_day_cap'] ?? null),
            ':w' => $intOrNull($request->post['per_week_cap'] ?? null),
            ':m' => $intOrNull($request->post['per_month_cap'] ?? null),
            ':act' => isset($request->post['is_active']) ? 1 : 0,
            ':id' => (int) $id,
        ]);
        return Response::redirect('/admin/messaging?message=rule_saved#rules');
    }

    /** POST /admin/messaging/test — send a test message to a number. */
    public function sendTest(Request $request): Response
    {
        if (!CsrfService::verify($request->post['_csrf'] ?? null)) {
            return Response::redirect('/admin/messaging');
        }
        $to = trim((string) ($request->post['test_number'] ?? ''));
        $tpl = trim((string) ($request->post['test_template'] ?? 'patient_confirmed'));
        if ($to === '') {
            return Response::redirect('/admin/messaging?message=test_no_number#connection');
        }
        $result = WhatsAppService::send($to, $tpl, [
            'patient_name' => 'Test',
            'doctor_name' => 'eClinicPro',
            'datetime' => date('d M Y, g:i A'),
            'clinic_phone' => $to,
        ]);
        $msg = $result['ok'] ? 'test_sent' : 'test_failed';
        return Response::redirect('/admin/messaging?message=' . $msg . '#connection');
    }

    // ---- Cron triggers (POST from system cron, authenticated as superadmin) ----

    /** Drain the notifications queue (every 1-2 min). */
    public function runProcess(Request $request): Response
    {
        $n = \App\Services\NotificationProcessor::processQueue(100);
        return Response::json(['ok' => true, 'processed' => $n]);
    }

    /** Lead soft-nudge + appointment reminders (every 15 min). */
    public function runLeadNudges(Request $request): Response
    {
        $n = \App\Services\LeadFlowService::runNudges();
        return Response::json(['ok' => true, 'queued' => $n]);
    }

    /** Expire stale unconfirmed leads (hourly). */
    public function runLeadExpire(Request $request): Response
    {
        $n = \App\Services\LeadFlowService::expireStale();
        return Response::json(['ok' => true, 'expired' => $n]);
    }

    private static function safeAll(PDO $pdo, string $sql): array
    {
        try {
            return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            return [];
        }
    }
}
