<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\RequestContext;
use App\Gates\FeatureGate;
use App\Gates\ModuleGate;

final class SidebarService
{
    /** @return array{groups: list<array{label: string, items: list<array<string, string>>}>, dashboard: array<string, string>} */
    public static function build(): array
    {
        $clinicId = RequestContext::clinicId();
        if ($clinicId === null) {
            return ['groups' => [], 'dashboard' => ['label' => 'Dashboard', 'href' => '/dashboard', 'icon' => '🏠']];
        }

        $active = array_flip(ModuleGate::activeModules($clinicId));
        $config = require dirname(__DIR__, 2) . '/config/modules_nav.php';
        $groups = [];

        foreach ($config as $group) {
            $items = [];
            foreach ($group['items'] as $moduleId => $item) {
                // Bucket-3 items: hidden until the feature_flag is enabled
                // for this clinic. Once promoted to a paid add-on, drop the
                // feature_flag key from the config and the item falls back
                // to the standard clinic_modules check below.
                if (!empty($item['feature_flag'])
                    && !FeatureGate::check((string) $item['feature_flag'])) {
                    continue;
                }

                $anyOf = $item['any_of'] ?? [$moduleId];
                $visible = false;
                foreach ($anyOf as $m) {
                    if (isset($active[$m])) {
                        $visible = true;
                        break;
                    }
                }
                if (!$visible) {
                    continue;
                }
                $items[] = [
                    'module_id' => $moduleId,
                    'label' => $item['label'],
                    'icon' => $item['icon'],
                    'href' => $item['href'],
                ];
            }
            if ($items !== []) {
                $groups[] = [
                    'label' => $group['label'],
                    'items' => $items,
                ];
            }
        }

        return [
            'dashboard' => ['label' => 'Dashboard', 'href' => '/dashboard', 'icon' => '🏠'],
            'groups' => $groups,
        ];
    }
}
