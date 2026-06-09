<?php

declare(strict_types=1);

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$file = __DIR__ . $uri;

if ($uri !== '/' && is_file($file)) {
    return false;
}

require __DIR__ . '/partials/request_router.php';

if (ecp_dispatch_clean_url($_SERVER['REQUEST_URI'] ?? '/')) {
    return true;
}

if ($uri === '/' || $uri === '') {
    require __DIR__ . '/index.php';

    return true;
}

http_response_code(404);
require __DIR__ . '/404.php';

return true;
