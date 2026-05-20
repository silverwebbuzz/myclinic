<?php

declare(strict_types=1);

use App\Core\Application;

require dirname(__DIR__) . '/vendor/autoload.php';

$base = dirname(__DIR__);
$app = Application::boot($base);

if (is_file($base . '/.env')) {
    \App\Support\SentryBootstrap::register();
}

$app->run();
