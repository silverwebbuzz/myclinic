<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\RequestContext;
use App\Http\Request;
use App\Http\Response;
use App\Services\CsrfService;
use App\Services\SmtpMailService;
use App\Services\Store\StoreAudit;
use App\Services\Store\StoreEmailTemplates;
use App\Services\Store\StoreNotifier;
use App\Services\Store\StoreSettings;
use App\Services\Store\StoreWhatsAppTemplates;
use App\Services\WaTemplateService;
use App\Support\MessagingSettings;
use App\Support\SessionFlash;
use App\Support\View;

/**
 * Store emails in admin (super-admin only, /admin/store/*):
 *   /admin/store/email            sender, reply-to, team addresses, test send, mail log,
 *                                 customer WhatsApp updates (on/off per template + test send)
 *   /admin/store/email-templates  wording of every store email + on/off + preview
 * SMTP login itself is shared with clinic mail (app/.env, /admin/email).
 */
final class StoreEmailAdminController
{
    public function settings(Request $request): Response
    {
        $status = (string) ($request->query['status'] ?? '');
        $wa = [];
        foreach (StoreWhatsAppTemplates::registry() as $key => $meta) {
            $tpl = WaTemplateService::find($key);
            $wa[$key] = $meta + [
                'meta_status' => StoreWhatsAppTemplates::metaStatus($key),
                'body' => (string) ($tpl['body_text'] ?? ''),
                'on' => !in_array($key, StoreWhatsAppTemplates::disabledKeys(), true),
            ];
        }

        return $this->render('admin/store_email', [
            'smtpOk' => SmtpMailService::isConfigured(),
            'fromName' => StoreEmailTemplates::fromName(),
            'fromEmail' => StoreEmailTemplates::fromEmail(),
            'replyTo' => StoreEmailTemplates::replyTo(),
            'team' => implode(', ', StoreEmailTemplates::teamEmails()),
            'registry' => StoreEmailTemplates::registry(),
            'stats' => StoreEmailTemplates::stats(),
            'log' => StoreEmailTemplates::recentLog(100, in_array($status, ['sent', 'failed', 'disabled', 'queued', 'skipped'], true) ? $status : ''),
            'wa' => $wa,
            'waEnabled' => StoreWhatsAppTemplates::enabled(),
            'messagingOn' => MessagingSettings::enabled(),
            'whatsappConfigured' => MessagingSettings::whatsappConfigured(),
            'status' => $status,
            'adminEmail' => (string) (RequestContext::superAdmin()['email'] ?? ''),
        ]);
    }

    public function saveSettings(Request $request): Response
    {
        $action = (string) ($request->post['action'] ?? '');
        if ($action === 'test') {
            $key = (string) ($request->post['template'] ?? '');
            $to = trim((string) ($request->post['to'] ?? ''));
            $res = StoreNotifier::sendTest($key, $to);
            SessionFlash::put($res['ok'] ? 'store_ok' : 'store_err', $res['ok'] ? "Test email sent to $to." : 'Test failed: ' . ($res['error'] ?? ''));

            return Response::redirect('/admin/store/email');
        }
        if ($action === 'wa_save') {
            $on = array_values(array_filter((array) ($request->post['wa_on'] ?? []), [StoreWhatsAppTemplates::class, 'isKnown']));
            StoreWhatsAppTemplates::saveSwitches(!empty($request->post['wa_enabled']), $on);
            StoreAudit::log('store.whatsapp_settings', 'setting', null, null, ['enabled' => !empty($request->post['wa_enabled']), 'on' => $on]);
            SessionFlash::put('store_ok', 'WhatsApp settings saved.');

            return Response::redirect('/admin/store/email#whatsapp');
        }
        if ($action === 'wa_test') {
            $to = trim((string) ($request->post['to'] ?? ''));
            $res = StoreNotifier::sendWhatsAppTest((string) ($request->post['template'] ?? ''), $to);
            SessionFlash::put($res['ok'] ? 'store_ok' : 'store_err',
                $res['ok'] ? "Test WhatsApp sent to $to." . ($res['note'] ?? '') : 'Test failed: ' . ($res['error'] ?? ''));

            return Response::redirect('/admin/store/email#whatsapp');
        }

        $fromName = trim((string) ($request->post['from_name'] ?? ''));
        $fromEmail = trim((string) ($request->post['from_email'] ?? ''));
        $replyTo = trim((string) ($request->post['reply_to'] ?? ''));
        $team = array_values(array_filter(array_map('trim', explode(',', (string) ($request->post['team'] ?? '')))));
        $bad = array_filter(array_merge([$fromEmail, $replyTo], $team), static fn ($e) => $e !== '' && !filter_var($e, FILTER_VALIDATE_EMAIL));
        if ($bad) {
            SessionFlash::put('store_err', 'Not a valid email address: ' . implode(', ', $bad));

            return Response::redirect('/admin/store/email');
        }
        StoreSettings::set('store_email_from_name', mb_substr($fromName, 0, 80));
        StoreSettings::set('store_email_from', $fromEmail);
        StoreSettings::set('store_email_reply_to', $replyTo);
        StoreSettings::set('store_email_team', implode(', ', $team));
        StoreAudit::log('store.email_settings', 'setting', null, null, ['from' => $fromEmail, 'reply_to' => $replyTo, 'team' => $team]);
        SessionFlash::put('store_ok', 'Store email settings saved.');

        return Response::redirect('/admin/store/email');
    }

    public function templates(Request $request): Response
    {
        $registry = StoreEmailTemplates::registry();
        $defaults = [];
        foreach (array_keys($registry) as $key) {
            $defaults[$key] = StoreEmailTemplates::defaults($key);
        }

        return $this->render('admin/store_email_templates', [
            'registry' => $registry,
            'defaults' => $defaults,
            'rows' => StoreEmailTemplates::rows(),
            'open' => (string) ($request->query['open'] ?? ''),
        ]);
    }

    public function saveTemplate(Request $request, string $key): Response
    {
        if (!StoreEmailTemplates::isKnown($key)) {
            return Response::redirect('/admin/store/email-templates');
        }
        $ok = StoreEmailTemplates::save($key, [
            'subject' => (string) ($request->post['subject'] ?? ''),
            'title' => (string) ($request->post['title'] ?? ''),
            'body' => (string) ($request->post['body'] ?? ''),
            'cta_label' => (string) ($request->post['cta_label'] ?? ''),
            'note' => (string) ($request->post['note'] ?? ''),
            'is_enabled' => !empty($request->post['is_enabled']),
        ], (string) (RequestContext::superAdmin()['email'] ?? '') ?: null);
        StoreAudit::log('store.email_template_save', 'setting', null, null, ['key' => $key]);
        SessionFlash::put($ok ? 'store_ok' : 'store_err', $ok ? 'Saved. New emails use this wording.' : 'Could not save. Has the SQL patch 2026_10_03_store_emails.sql been run?');

        return Response::redirect('/admin/store/email-templates?open=' . rawurlencode($key) . '#' . $key);
    }

    public function resetTemplate(Request $request, string $key): Response
    {
        StoreEmailTemplates::reset($key);
        SessionFlash::put('store_ok', 'Back to the built-in wording.');

        return Response::redirect('/admin/store/email-templates?open=' . rawurlencode($key) . '#' . $key);
    }

    /** Full email with sample data, shown in an iframe on the templates page. */
    public function preview(Request $request, string $key): Response
    {
        if (!StoreEmailTemplates::isKnown($key)) {
            return Response::html('Unknown email', 404);
        }

        return Response::html(StoreNotifier::preview($key));
    }

    private function render(string $view, array $data): Response
    {
        return Response::html(View::render($view, $data + [
            'csrf' => CsrfService::token(),
            'flashOk' => SessionFlash::pull('store_ok'),
            'flashErr' => SessionFlash::pull('store_err'),
        ]));
    }
}
