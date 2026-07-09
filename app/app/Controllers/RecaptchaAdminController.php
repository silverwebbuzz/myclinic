<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Services\CsrfService;
use App\Support\MessagingSettings;
use App\Support\View;

final class RecaptchaAdminController
{
    public function index(Request $request): Response
    {
        return Response::html(View::render('admin/recaptcha', [
            'csrf' => CsrfService::token(),
            'message' => $request->query['message'] ?? null,
            'enabled' => MessagingSettings::get('recaptcha_enabled', '0') === '1',
            'siteKey' => MessagingSettings::get('recaptcha_site_key', '') ?? '',
            'secretKey' => MessagingSettings::get('recaptcha_secret_key', '') ?? '',
        ]));
    }

    public function save(Request $request): Response
    {
        if (!CsrfService::verify($request->post['_csrf'] ?? null)) {
            return Response::redirect('/admin/recaptcha');
        }

        MessagingSettings::set('recaptcha_enabled', isset($request->post['recaptcha_enabled']) ? '1' : '0');
        MessagingSettings::set('recaptcha_site_key', trim((string) ($request->post['recaptcha_site_key'] ?? '')));
        MessagingSettings::set('recaptcha_secret_key', trim((string) ($request->post['recaptcha_secret_key'] ?? '')));

        return Response::redirect('/admin/recaptcha?message=saved');
    }
}
