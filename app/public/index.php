<?php

declare(strict_types=1);

use App\Core\Application;

$base = dirname(__DIR__);
$autoload = $base . '/vendor/autoload.php';

if (! is_file($autoload)) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    echo "Missing dependency bootstrap: vendor/autoload.php\n";
    echo "Run these commands from app folder:\n";
    echo "1) composer install --ignore-platform-req=php-64bit\n";
    echo "2) php database/migrate.php\n";
    echo "3) php database/seed.php\n";
    exit(1);
}

require $autoload;
$app = Application::boot($base);

if (is_file($base . '/.env')) {
    \App\Support\SentryBootstrap::register();
}

$app->run();
