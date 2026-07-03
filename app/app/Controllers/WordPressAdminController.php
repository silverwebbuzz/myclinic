<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\RequestContext;
use App\Http\Request;
use App\Http\Response;
use App\Services\CsrfService;
use App\Services\WordPressDoctorService;
use App\Services\WordPressService;
use App\Support\View;
use App\Support\WordPressSettings;

final class WordPressAdminController
{
    public function settings(Request $request): Response
    {
        return Response::html(View::render('admin/wordpress_settings', [
            'admin' => RequestContext::superAdmin(),
            'csrf' => CsrfService::token(),
            'settings' => WordPressSettings::allForAdmin(),
            'wpConfigured' => WordPressSettings::isConfigured(),
            'message' => $request->query['message'] ?? null,
            'error' => $request->query['error'] ?? null,
        ]));
    }

    public function saveSettings(Request $request): Response
    {
        if (!CsrfService::verify($request->post['_csrf'] ?? null)) {
            return Response::redirect('/admin/wordpress-settings');
        }

        WordPressSettings::saveFromAdmin($request->post);

        return Response::redirect('/admin/wordpress-settings?message=settings_saved');
    }

    public function index(Request $request): Response
    {
        $search = trim((string) ($request->query['q'] ?? ''));
        $page = max(1, (int) ($request->query['page'] ?? 1));
        $synced = WordPressDoctorService::syncActiveLinks();
        $data = WordPressDoctorService::doctorsForAdmin($search, $page);

        $message = $request->query['message'] ?? null;
        if ($message === null && $synced > 0) {
            $message = 'wordpress_sync_revoked';
        }

        return Response::html(View::render('admin/wordpress_doctors', [
            'admin' => RequestContext::superAdmin(),
            'csrf' => CsrfService::token(),
            'doctors' => $data['doctors'],
            'total' => $data['total'],
            'page' => $data['page'],
            'pages' => $data['pages'],
            'search' => $search,
            'wpConfigured' => WordPressSettings::isConfigured(),
            'message' => $message,
            'error' => $request->query['error'] ?? null,
        ]));
    }

    public function grantAccess(Request $request): Response
    {
        $directoryDoctorId = (int) ($request->post['directory_doctor_id'] ?? $request->post['user_id'] ?? 0);
        $admin = RequestContext::superAdmin();
        $adminId = (int) ($admin['id'] ?? 0);

        $result = WordPressDoctorService::grantAccess($directoryDoctorId, $adminId);
        if (!$result['ok']) {
            return Response::redirect('/admin/wordpress-doctors?error=' . rawurlencode((string) ($result['error'] ?? 'Failed')));
        }

        $msg = !empty($result['linked_existing']) ? 'wordpress_access_linked' : 'wordpress_access_granted';

        return Response::redirect('/admin/wordpress-doctors?message=' . $msg);
    }

    public function revokeAccess(Request $request): Response
    {
        $directoryDoctorId = (int) ($request->post['directory_doctor_id'] ?? $request->post['user_id'] ?? 0);
        $admin = RequestContext::superAdmin();
        $adminId = (int) ($admin['id'] ?? 0);
        $deleteWpUser = ($request->post['delete_wp_user'] ?? '1') === '1';

        $result = WordPressDoctorService::revokeAccess($directoryDoctorId, $adminId, $deleteWpUser);
        if (!$result['ok']) {
            return Response::redirect('/admin/wordpress-doctors?error=' . rawurlencode((string) ($result['error'] ?? 'Failed')));
        }

        $msg = !empty($result['wp_deleted']) ? 'wordpress_access_revoked' : 'wordpress_access_revoked_unlinked';

        return Response::redirect('/admin/wordpress-doctors?message=' . $msg);
    }

    public function testConnection(Request $request): Response
    {
        if (!WordPressSettings::isConfigured()) {
            return Response::redirect('/admin/wordpress-settings?error=' . rawurlencode('WordPress API is not configured.'));
        }

        $resp = WordPressService::request('GET', '/wp/v2/users/me');
        if (!$resp['ok']) {
            return Response::redirect('/admin/wordpress-settings?error=' . rawurlencode((string) ($resp['error'] ?? 'Connection failed')));
        }

        return Response::redirect('/admin/wordpress-settings?message=wordpress_connection_ok');
    }
}
