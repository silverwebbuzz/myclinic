<?php

declare(strict_types=1);

// After Phase 1 there is only one plan: 'standard'. Legacy entries
// (free/clinic/practice/enterprise) are kept so anything that hasn't
// been refactored yet doesn't blow up. They are not surfaced anywhere
// in the UI — see /pricing for the new model.

return [
    'standard' => [
        'name' => 'Clinic',
        'tagline' => 'Everything to run your clinic',
        // NOTE: fields are named *_usd for legacy reasons but hold INR amounts.
        // Single annual plan: ₹16,000/year (10% off ₹17,988; GST added at checkout).
        'monthly_usd' => 1499,   // legacy; kept for MRR math, not surfaced in UI
        'yearly_usd' => 16000,
        'seat_limit' => 999,        // unlimited in practice
        'patient_limit' => null,
        'featured' => true,
        'trial_days' => 30,
        'modules' => 'all_paid',    // resolved to every core module
        'highlights' => [
            'Patient records, visits, prescriptions',
            'Appointments + walk-in queue',
            'Billing & GST invoicing',
            'Teleconsultation built in',
            'Unlimited patients & users',
        ],
        'limits' => [],
    ],
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
            'qr', 'discharge', 'incentives',
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
            'qr', 'discharge', 'incentives',
            'lab', 'pharmacy', 'analytics', 'staff', 'crm',
        ],
        'highlights' => [
            'Everything in Clinic',
            'Lab, Pharmacy, Analytics, CRM',
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
