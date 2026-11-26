<?php

declare(strict_types=1);

namespace App\Services;

use Mpdf\Mpdf;
use Mpdf\Output\Destination;

final class DietPdfService
{
    /** @param array<string, mixed> $plan @param array<string, mixed> $patient @param array<string, mixed> $clinic */
    public static function generate(array $plan, array $patient, array $clinic): string
    {
        // Private storage (see InvoicePdfService): patients open it via a
        // signed link, the clinic via the logged-in visit route.
        [$dir, $relDir] = \App\Support\PrivateFile::dir('diet/' . (int) $clinic['id']);

        $path = $dir . '/diet-' . (int) $plan['id'] . '.pdf';
        $rel = $relDir . '/diet-' . (int) $plan['id'] . '.pdf';

        $html = '<h2>' . htmlspecialchars((string) $clinic['name']) . ' — Diet plan</h2>'
            . '<p><strong>Patient:</strong> ' . htmlspecialchars((string) $patient['name']) . '</p>'
            . '<p><strong>Condition:</strong> ' . htmlspecialchars((string) ($plan['condition'] ?? '')) . '</p>'
            . '<p><strong>Diet type:</strong> ' . htmlspecialchars((string) ($plan['veg_type'] ?? '')) . '</p>';

        if (!empty($plan['antidotes_shown'])) {
            $html .= '<h3>Restrictions / antidotes</h3><p>' . nl2br(htmlspecialchars((string) $plan['antidotes_shown'])) . '</p>';
        }

        $html .= '<table width="100%" cellpadding="6" style="border-collapse:collapse;margin-top:12px;">'
            . '<tr style="background:#f1f5f9;"><th>Day</th><th>Breakfast</th><th>Lunch</th><th>Dinner</th></tr>';

        $week = is_array($plan['plan_json'] ?? null) ? $plan['plan_json'] : [];
        foreach ($week as $day => $meals) {
            if (!is_array($meals)) {
                continue;
            }
            $html .= '<tr><td>' . htmlspecialchars((string) $day)
                . '</td><td>' . htmlspecialchars((string) ($meals['breakfast'] ?? ''))
                . '</td><td>' . htmlspecialchars((string) ($meals['lunch'] ?? ''))
                . '</td><td>' . htmlspecialchars((string) ($meals['dinner'] ?? '')) . '</td></tr>';
        }
        $html .= '</table>';

        if (class_exists(Mpdf::class)) {
            $mpdf = new Mpdf(['format' => 'A4']);
            $mpdf->WriteHTML($html);
            $mpdf->Output($path, Destination::FILE);
        } else {
            file_put_contents($path, strip_tags($html));
        }

        return $rel;
    }
}
