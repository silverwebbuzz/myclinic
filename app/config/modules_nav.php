<?php

declare(strict_types=1);

/**
 * Sidebar navigation: module_id => item metadata.
 *
 * Visibility rules (resolved in SidebarService):
 *   - 'feature_flag' key set → item shown only when that feature_flag
 *     is enabled for this clinic (Bucket-3 staged rollout).
 *   - Otherwise → item shown when clinic has an active clinic_modules
 *     row for module_id (or any of 'any_of').
 *
 * Bucket-3 items keep their module_id (so paid activation still works
 * once promoted), but the feature_flag gates discoverability.
 */
return [
    'clinical' => [
        'label' => 'Clinical',
        'items' => [
            'patients' => ['label' => 'Patients', 'icon' => '👤', 'href' => '/patients'],
            'emr' => ['label' => 'Visits / EMR', 'icon' => '📋', 'href' => '/visits'],
            'prescription' => ['label' => 'Prescriptions', 'icon' => '💊', 'href' => '/prescriptions'],
            'vitals' => ['label' => 'Vitals', 'icon' => '❤️', 'href' => '/vitals'],
        ],
    ],
    'operations' => [
        'label' => 'Operations',
        'items' => [
            'appointments_basic' => ['label' => 'Appointments', 'icon' => '📅', 'href' => '/appointments'],
            'appointments_calendar' => ['label' => 'Calendar', 'icon' => '📅', 'href' => '/appointments/calendar', 'any_of' => ['appointments_basic']],
            'invoicing_basic' => ['label' => 'Patient Bills', 'icon' => '🧾', 'href' => '/billing', 'any_of' => ['invoicing_basic', 'billing_pro']],
        ],
    ],
    'reports' => [
        'label' => 'Reports',
        'items' => [
            'analytics' => ['label' => 'Analytics', 'icon' => '📊', 'href' => '/analytics',
                'feature_flag' => 'advanced_analytics'],
            'staff' => ['label' => 'Staff', 'icon' => '👥', 'href' => '/staff/attendance'],
        ],
    ],
];
