<?php

declare(strict_types=1);

require_once __DIR__ . '/partials/book_bridge.php';

$slug = strtolower(trim((string) ($_GET['slug'] ?? '')));
if ($slug === '' || !preg_match('/^[a-z0-9-]+$/', $slug)) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}

$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

if ($method === 'GET') {
    ecp_book_bootstrap();
    $clinic = \App\Services\PublicBookingService::clinicBySlug($slug);
    if ($clinic === null) {
        http_response_code(404);
        require __DIR__ . '/404.php';
        exit;
    }

    $profilePath = \App\Services\DirectoryProfileUrlService::profilePathForTenant((int) $clinic['id']);
    if ($profilePath !== null) {
        header('Location: ' . ecp_site_url($profilePath . '#book'), true, 301);
        exit;
    }

    ecp_book_render_standalone_page($slug, $clinic);
    exit;
}

$action = $method === 'POST' ? 'book' : 'show';
ecp_book_dispatch($action, $slug)->send();
