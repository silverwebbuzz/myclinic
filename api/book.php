<?php

declare(strict_types=1);

require_once __DIR__ . '/../partials/book_bridge.php';

$slug = (string) ($_GET['slug'] ?? '');

ecp_book_slots($slug)->send();
