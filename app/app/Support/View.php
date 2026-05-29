<?php

declare(strict_types=1);

namespace App\Support;

final class View
{
    /** @param array<string, mixed> $data */
    public static function render(string $name, array $data = []): string
    {
        // UI component helpers (ui_icon, ui_card, ui_button, …) must be
        // available to every view, including content views rendered before
        // the layout. require_once is safe to call repeatedly.
        require_once dirname(__DIR__, 2) . '/views/components/ui.php';

        extract($data, EXTR_SKIP);
        ob_start();
        require dirname(__DIR__, 2) . '/views/' . $name . '.php';

        return (string) ob_get_clean();
    }
}
