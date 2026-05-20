<?php

declare(strict_types=1);

namespace App\Support;

final class View
{
    /** @param array<string, mixed> $data */
    public static function render(string $name, array $data = []): string
    {
        extract($data, EXTR_SKIP);
        ob_start();
        require dirname(__DIR__, 2) . '/views/' . $name . '.php';

        return (string) ob_get_clean();
    }
}
