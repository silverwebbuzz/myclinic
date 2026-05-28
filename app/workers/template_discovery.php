<?php

declare(strict_types=1);

// Cron: weekly (Mon 04:00) — php workers/template_discovery.php
// Auto-discovers prescription templates from repeated drug combos.
require dirname(__DIR__) . '/vendor/autoload.php';

use App\Support\TemplateDiscovery;
use Dotenv\Dotenv;

$base = dirname(__DIR__);
if (is_file($base . '/.env')) {
    Dotenv::createImmutable($base)->safeLoad();
}

$n = TemplateDiscovery::run();
echo "Template suggestions created: {$n}\n";
