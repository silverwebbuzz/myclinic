<?php

declare(strict_types=1);

require_once __DIR__ . '/partials/book_bridge.php';

$slug = (string) ($_GET['slug'] ?? '');
$action = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' ? 'book' : 'show';

ecp_book_dispatch($action, $slug)->send();
