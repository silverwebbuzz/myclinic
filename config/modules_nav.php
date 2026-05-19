<?php

declare(strict_types=1);

/**
 * Sidebar navigation: module_id => item metadata.
 * Only items whose module_id is active for the clinic are shown.
 */
return [
    'clinical' => [
        'label' => 'Clinical',
        'items' => [
            'patients' => ['label' => 'Patients', 'icon' => '👤', 'href' => '/patients'],
            'emr' => ['label' => 'Visits / EMR', 'icon' => '📋', 'href' => '/visits'],
            'prescription' => ['label' => 'Prescriptions', 'icon' => '💊', 'href' => '/prescriptions'],
            'vitals' => ['label' => 'Vitals', 'icon' => '❤️', 'href' => '/vitals'],
            'lab' => ['label' => 'Lab', 'icon' => '🔬', 'href' => '/lab/catalog'],
            'radiology' => ['label' => 'Radiology', 'icon' => '🩻', 'href' => '/radiology'],
            'pharmacy' => ['label' => 'Pharmacy', 'icon' => '🏪', 'href' => '/pharmacy/pos'],
        ],
    ],
    'operations' => [
        'label' => 'Operations',
        'items' => [
            'appointments_basic' => ['label' => 'Appointments', 'icon' => '📅', 'href' => '/appointments'],
            'advanced_scheduling' => ['label' => 'Scheduling', 'icon' => '🗓️', 'href' => '/scheduling'],
            'invoicing_basic' => ['label' => 'Invoices', 'icon' => '🧾', 'href' => '/invoices'],
            'billing_pro' => ['label' => 'Billing', 'icon' => '💳', 'href' => '/billing'],
            'whatsapp' => ['label' => 'WhatsApp', 'icon' => '💬', 'href' => '/settings?tab=notifications'],
            'qr' => ['label' => 'QR Cards', 'icon' => '📱', 'href' => '/patients'],
        ],
    ],
    'reports' => [
        'label' => 'Reports',
        'items' => [
            'analytics' => ['label' => 'Analytics', 'icon' => '📊', 'href' => '/analytics'],
            'crm' => ['label' => 'CRM & Leads', 'icon' => '🎯', 'href' => '/crm'],
            'staff' => ['label' => 'Staff', 'icon' => '👥', 'href' => '/staff/attendance'],
        ],
    ],
];
