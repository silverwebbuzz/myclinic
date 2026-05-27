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
            'lab' => ['label' => 'Lab', 'icon' => '🔬', 'href' => '/lab/catalog',
                'feature_flag' => 'lab_module'],
            'radiology' => ['label' => 'Radiology', 'icon' => '🩻', 'href' => '/radiology',
                'feature_flag' => 'radiology_module'],
            'pharmacy' => ['label' => 'Pharmacy', 'icon' => '🏪', 'href' => '/pharmacy/pos',
                'feature_flag' => 'pharmacy_module'],
        ],
    ],
    'operations' => [
        'label' => 'Operations',
        'items' => [
            'appointments_basic' => ['label' => 'Appointments', 'icon' => '📅', 'href' => '/appointments'],
            'advanced_scheduling' => ['label' => 'Scheduling', 'icon' => '🗓️', 'href' => '/scheduling'],
            'invoicing_basic' => ['label' => 'Billing', 'icon' => '🧾', 'href' => '/billing', 'any_of' => ['invoicing_basic', 'billing_pro']],
            'whatsapp' => ['label' => 'WhatsApp', 'icon' => '💬', 'href' => '/settings?tab=notifications'],
            'qr' => ['label' => 'QR Cards', 'icon' => '📱', 'href' => '/patients'],
        ],
    ],
    'reports' => [
        'label' => 'Reports',
        'items' => [
            'analytics' => ['label' => 'Analytics', 'icon' => '📊', 'href' => '/analytics',
                'feature_flag' => 'advanced_analytics'],
            'crm' => ['label' => 'CRM & Leads', 'icon' => '🎯', 'href' => '/crm',
                'feature_flag' => 'crm_module'],
            'staff' => ['label' => 'Staff', 'icon' => '👥', 'href' => '/staff/attendance',
                'feature_flag' => 'incentive_module'],
        ],
    ],
];
