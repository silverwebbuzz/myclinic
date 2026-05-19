<?php

declare(strict_types=1);

return [
    'free' => [
        'name' => 'Free',
        'tagline' => 'Solo practice starter',
        'monthly_usd' => 0,
        'yearly_usd' => 0,
        'seat_limit' => 2,
        'patient_limit' => 100,
        'featured' => false,
        'modules' => ['patients', 'appointments_basic', 'invoicing_basic'],
        'highlights' => [
            'Patient management (100 patients)',
            'Basic appointments & queue',
            'Basic invoicing',
            '2 team seats',
        ],
        'limits' => [
            'No WhatsApp reminders',
            'No EMR / prescriptions',
        ],
    ],
    'clinic' => [
        'name' => 'Clinic',
        'tagline' => 'Full clinical suite for 1 doctor',
        'monthly_usd' => 29,
        'yearly_usd' => 23,
        'seat_limit' => 3,
        'patient_limit' => null,
        'featured' => false,
        'trial_days' => 14,
        'modules' => [
            'patients', 'appointments_basic', 'invoicing_basic',
            'vitals', 'prescription', 'emr', 'billing_pro', 'whatsapp',
            'qr', 'consent', 'discharge', 'incentives',
        ],
        'highlights' => [
            'All 9 core clinical modules',
            'Unlimited patients',
            'WhatsApp notifications',
            '3 team seats',
        ],
        'limits' => [],
    ],
    'practice' => [
        'name' => 'Practice',
        'tagline' => 'Multi-doctor clinic',
        'monthly_usd' => 79,
        'yearly_usd' => 63,
        'seat_limit' => 8,
        'patient_limit' => null,
        'featured' => true,
        'trial_days' => 14,
        'modules' => [
            'patients', 'appointments_basic', 'invoicing_basic',
            'vitals', 'prescription', 'emr', 'billing_pro', 'whatsapp',
            'qr', 'consent', 'discharge', 'incentives',
            'advanced_scheduling', 'lab', 'pharmacy', 'analytics', 'staff', 'crm',
        ],
        'highlights' => [
            'Everything in Clinic',
            'Lab, Pharmacy, Analytics, CRM',
            'Advanced scheduling',
            '8 team seats',
        ],
        'limits' => [],
    ],
    'enterprise' => [
        'name' => 'Enterprise',
        'tagline' => 'Unlimited scale + white-label',
        'monthly_usd' => 199,
        'yearly_usd' => 159,
        'seat_limit' => 999,
        'patient_limit' => null,
        'featured' => false,
        'trial_days' => 14,
        'modules' => 'all_paid',
        'highlights' => [
            'All modules included',
            'Patient portal & telemedicine',
            'White-label domain',
            'Unlimited seats',
        ],
        'limits' => [],
    ],
];
