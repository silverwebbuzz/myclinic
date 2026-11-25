<?php

declare(strict_types=1);

namespace App\Controllers\Corporate;

use App\Http\Request;
use App\Http\Response;
use App\Services\Corporate\CorpAuth;
use App\Services\Corporate\CorpContext;
use App\Services\Corporate\ReminderService;
use App\Services\CsrfService;
use App\Support\SessionFlash;
use App\Support\View;

/** /company/settings/reminders — which automatic emails go out (design §6, R5). */
final class CorpReminderSettingsController
{
    public function index(Request $request): Response
    {
        if ($deny = CorpAuth::guard('company.edit')) {
            return $deny;
        }

        return Response::html(View::render('corporate/reminder_settings', [
            'on' => ReminderService::settings($this->cid()),
            'csrf' => CsrfService::token(),
            'company' => CorpContext::company() ?? [],
            'user' => CorpContext::user() ?? [],
            'loc' => CorpContext::locale(),
            'flashOk' => SessionFlash::pull('corp_ok'),
            'flashErr' => SessionFlash::pull('corp_err'),
        ]));
    }

    public function save(Request $request): Response
    {
        if ($deny = CorpAuth::guard('company.edit')) {
            return $deny;
        }
        ReminderService::saveSettings($this->cid(), (int) (CorpContext::user()['id'] ?? 0), $request->post);
        SessionFlash::put('corp_ok', 'Reminder settings saved.');

        return Response::redirect('/company/settings/reminders');
    }

    private function cid(): int
    {
        return (int) (CorpContext::company()['id'] ?? 0);
    }
}
