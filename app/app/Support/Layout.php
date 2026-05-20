<?php

declare(strict_types=1);

namespace App\Support;

use App\Core\RequestContext;
use App\Services\CsrfService;
use App\Services\SidebarService;

final class Layout
{
    /** @param array<string, mixed> $data */
    public static function page(string $view, array $data = [], ?string $pageTitle = null): string
    {
        $clinic = RequestContext::clinic() ?? [];
        $user = RequestContext::user() ?? [];
        $data['csrf'] = CsrfService::token();

        $data['content'] = View::render($view, $data);
        $data['pageTitle'] = $pageTitle ?? 'eClinicPro';
        $data['title'] = ($pageTitle ? $pageTitle . ' — ' : '') . ($clinic['name'] ?? 'eClinicPro');
        $data['clinic'] = $clinic;
        $data['user'] = $user;
        $data['csrf'] = CsrfService::token();
        $data['nav'] = SidebarService::build();
        $data['brandColor'] = $clinic['brand_color'] ?? '#0F9B6E';
        $data['logoUrl'] = !empty($clinic['logo_path'])
            ? '/' . ltrim((string) $clinic['logo_path'], '/')
            : null;

        return View::render('layouts/base', $data);
    }

    /** @param array<string, mixed> $data */
    public static function portal(string $view, array $data = [], ?string $pageTitle = null): string
    {
        $clinic = RequestContext::clinic() ?? [];
        $data['content'] = View::render($view, $data);
        $data['pageTitle'] = $pageTitle ?? 'Patient Portal';
        $data['title'] = ($clinic['name'] ?? 'Clinic') . ' — Portal';
        $data['clinic'] = $clinic;
        $data['brandColor'] = $clinic['brand_color'] ?? '#0F9B6E';

        return View::render('layouts/portal', $data);
    }
}
