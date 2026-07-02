<?php

declare(strict_types=1);

// Cron: daily — php workers/photo_cache_prune.php
// Deletes cached Google Places photos older than 35 days so
// storage/photo_cache/ doesn't grow unbounded. The proxy (api/photo.php)
// re-fetches after 30 days anyway, so anything past 35 is dead weight.
// (No autoload/env needed — pure filesystem.)

const PHOTO_CACHE_MAX_AGE = 35 * 86400; // 35 days

// storage/ lives at the repo root, one level above app/.
$cacheDir = dirname(__DIR__, 2) . '/storage/photo_cache';

if (!is_dir($cacheDir)) {
    echo "Photo cache dir not found (nothing to prune): {$cacheDir}\n";
    exit;
}

$now = time();
$deleted = 0;
$kept = 0;

foreach (glob($cacheDir . '/*') ?: [] as $file) {
    if (!is_file($file)) {
        continue;
    }
    if (($now - filemtime($file)) > PHOTO_CACHE_MAX_AGE) {
        if (@unlink($file)) {
            $deleted++;
        }
    } else {
        $kept++;
    }
}

echo "Photo cache prune — deleted: {$deleted} · kept: {$kept}\n";
