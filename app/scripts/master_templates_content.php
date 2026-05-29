<?php

declare(strict_types=1);

/**
 * Clinical content for master prescription templates.
 *
 * Structure:
 *   $CONTENT[specialtyKey] = [
 *     'label' => 'Human label (used to resolve the specialty_master slug)',
 *     'conditions' => [
 *        [ 'name' => 'Condition title', 'desc' => '...', 'mode' => 'allopathic',
 *          'items' => [
 *             ['generic' => 'Paracetamol', 'dose_unit'=>'tablet','dose_amount'=>1,
 *              'freq'=>'1-1-1','days'=>3,'food'=>'after','instructions'=>'...'],
 *          ],
 *        ],
 *     ],
 *   ];
 *
 * Medicines are listed by GENERIC NAME in Indian/British pharma spelling
 * (Paracetamol, Amoxycillin, Salbutamol, Pantoprazole…) to match the
 * imported A-Z India catalog. freq uses the morning-noon-night preset
 * format ('1-0-1' etc.) or 'SOS'. food: before|after|with|empty|bedtime|any.
 *
 * IMPORTANT: This is STARTER content — clinically common, conservative
 * choices meant as editable suggestions, NOT fixed protocols. Doctors edit
 * before prescribing. Admin can delete/adjust any template later.
 *
 * These are intentionally broad-coverage. Run with --dry to see which
 * generics don't match the catalog, then fix spellings here.
 */

$CONTENT = [

    // ===================== GENERAL PRACTICE / FAMILY =====================
    'gp' => [
        'label' => 'General Practice',
        'conditions' => [
            ['name' => 'Acute URI / Common Cold', 'desc' => 'Viral upper respiratory infection',
             'items' => [
                ['generic' => 'Paracetamol', 'dose_unit' => 'tablet', 'dose_amount' => 1, 'freq' => '1-1-1', 'days' => 3, 'food' => 'after'],
                ['generic' => 'Cetirizine', 'dose_unit' => 'tablet', 'dose_amount' => 1, 'freq' => '0-0-1', 'days' => 5, 'food' => 'after'],
                ['generic' => 'Levocetirizine', 'dose_unit' => 'tablet', 'dose_amount' => 1, 'freq' => '0-0-1', 'days' => 5, 'food' => 'after'],
             ]],
            ['name' => 'Fever (viral)', 'desc' => 'Symptomatic fever management',
             'items' => [
                ['generic' => 'Paracetamol', 'dose_unit' => 'tablet', 'dose_amount' => 1, 'freq' => '1-1-1', 'days' => 3, 'food' => 'after'],
                ['generic' => 'ORS', 'dose_unit' => 'sachet', 'dose_amount' => 1, 'freq' => 'SOS', 'days' => 3, 'food' => 'any', 'instructions' => 'Plenty of fluids'],
             ]],
            ['name' => 'Acute Bacterial Pharyngitis', 'desc' => 'Sore throat with bacterial features',
             'items' => [
                ['generic' => 'Amoxycillin', 'dose_unit' => 'capsule', 'dose_amount' => 1, 'freq' => '1-0-1', 'days' => 5, 'food' => 'after'],
                ['generic' => 'Paracetamol', 'dose_unit' => 'tablet', 'dose_amount' => 1, 'freq' => '1-1-1', 'days' => 3, 'food' => 'after'],
             ]],
            ['name' => 'Acute Gastroenteritis', 'desc' => 'Loose stools, mild dehydration',
             'items' => [
                ['generic' => 'ORS', 'dose_unit' => 'sachet', 'dose_amount' => 1, 'freq' => 'SOS', 'days' => 3, 'food' => 'any', 'instructions' => 'After each loose stool'],
                ['generic' => 'Ofloxacin', 'dose_unit' => 'tablet', 'dose_amount' => 1, 'freq' => '1-0-1', 'days' => 3, 'food' => 'after'],
                ['generic' => 'Racecadotril', 'dose_unit' => 'capsule', 'dose_amount' => 1, 'freq' => '1-0-1', 'days' => 3, 'food' => 'before'],
             ]],
            ['name' => 'Acidity / GERD', 'desc' => 'Dyspepsia, heartburn',
             'items' => [
                ['generic' => 'Pantoprazole', 'dose_unit' => 'tablet', 'dose_amount' => 1, 'freq' => '1-0-0', 'days' => 14, 'food' => 'before', 'instructions' => 'Before breakfast'],
                ['generic' => 'Domperidone', 'dose_unit' => 'tablet', 'dose_amount' => 1, 'freq' => '1-0-1', 'days' => 7, 'food' => 'before'],
             ]],
            ['name' => 'Allergic Rhinitis', 'desc' => 'Sneezing, nasal allergy',
             'items' => [
                ['generic' => 'Montelukast', 'dose_unit' => 'tablet', 'dose_amount' => 1, 'freq' => '0-0-1', 'days' => 14, 'food' => 'after'],
                ['generic' => 'Levocetirizine', 'dose_unit' => 'tablet', 'dose_amount' => 1, 'freq' => '0-0-1', 'days' => 14, 'food' => 'after'],
             ]],
            ['name' => 'Productive Cough', 'desc' => 'Cough with expectoration',
             'items' => [
                ['generic' => 'Ambroxol', 'dose_unit' => 'ml', 'dose_amount' => 10, 'freq' => '1-1-1', 'days' => 5, 'food' => 'after'],
             ]],
            ['name' => 'Body Ache / Myalgia', 'desc' => 'Musculoskeletal pain',
             'items' => [
                ['generic' => 'Diclofenac', 'dose_unit' => 'tablet', 'dose_amount' => 1, 'freq' => '1-0-1', 'days' => 5, 'food' => 'after'],
                ['generic' => 'Pantoprazole', 'dose_unit' => 'tablet', 'dose_amount' => 1, 'freq' => '1-0-0', 'days' => 5, 'food' => 'before'],
             ]],
            ['name' => 'Worm Infestation', 'desc' => 'Deworming',
             'items' => [
                ['generic' => 'Albendazole', 'dose_unit' => 'tablet', 'dose_amount' => 1, 'freq' => '0-0-1', 'days' => 1, 'food' => 'after', 'instructions' => 'Single dose, repeat after 2 weeks'],
             ]],
            ['name' => 'Vitamin Deficiency / Tonic', 'desc' => 'General supplementation',
             'items' => [
                ['generic' => 'Multivitamin', 'dose_unit' => 'tablet', 'dose_amount' => 1, 'freq' => '1-0-0', 'days' => 30, 'food' => 'after'],
                ['generic' => 'Vitamin D3', 'dose_unit' => 'sachet', 'dose_amount' => 1, 'freq' => '0-0-0', 'days' => 7, 'food' => 'after', 'instructions' => 'Once weekly'],
             ]],
        ],
    ],

    // ===================== PEDIATRICS =====================
    'pediatrics' => [
        'label' => 'Pediatrics',
        'conditions' => [
            ['name' => 'Pediatric Fever', 'desc' => 'Weight-based; confirm dose',
             'items' => [
                ['generic' => 'Paracetamol', 'dose_unit' => 'ml', 'dose_amount' => 5, 'freq' => '1-1-1', 'days' => 3, 'food' => 'after', 'instructions' => '15mg/kg/dose — adjust to weight'],
             ]],
            ['name' => 'Pediatric Cough & Cold', 'desc' => 'Symptomatic',
             'items' => [
                ['generic' => 'Cetirizine', 'dose_unit' => 'ml', 'dose_amount' => 5, 'freq' => '0-0-1', 'days' => 5, 'food' => 'after'],
                ['generic' => 'Paracetamol', 'dose_unit' => 'ml', 'dose_amount' => 5, 'freq' => 'SOS', 'days' => 3, 'food' => 'after'],
             ]],
            ['name' => 'Pediatric Acute Diarrhea', 'desc' => 'ORS + zinc protocol',
             'items' => [
                ['generic' => 'ORS', 'dose_unit' => 'sachet', 'dose_amount' => 1, 'freq' => 'SOS', 'days' => 5, 'food' => 'any', 'instructions' => 'After each loose stool'],
                ['generic' => 'Zinc', 'dose_unit' => 'ml', 'dose_amount' => 5, 'freq' => '0-0-1', 'days' => 14, 'food' => 'after'],
             ]],
            ['name' => 'Pediatric Throat Infection', 'desc' => 'Bacterial — weight-based',
             'items' => [
                ['generic' => 'Amoxycillin', 'dose_unit' => 'ml', 'dose_amount' => 5, 'freq' => '1-0-1', 'days' => 5, 'food' => 'after', 'instructions' => 'Dose by weight'],
             ]],
            ['name' => 'Pediatric Worm Infestation', 'desc' => 'Deworming >2y',
             'items' => [
                ['generic' => 'Albendazole', 'dose_unit' => 'ml', 'dose_amount' => 10, 'freq' => '0-0-1', 'days' => 1, 'food' => 'after', 'instructions' => 'Single dose'],
             ]],
        ],
    ],

    // ===================== DERMATOLOGY =====================
    'dermatology' => [
        'label' => 'Dermatology',
        'conditions' => [
            ['name' => 'Acne (mild-moderate)', 'desc' => 'Topical + supportive',
             'items' => [
                ['generic' => 'Doxycycline', 'dose_unit' => 'capsule', 'dose_amount' => 1, 'freq' => '1-0-0', 'days' => 14, 'food' => 'after'],
                ['generic' => 'Azithromycin', 'dose_unit' => 'tablet', 'dose_amount' => 1, 'freq' => '1-0-0', 'days' => 3, 'food' => 'after'],
             ]],
            ['name' => 'Fungal Skin Infection', 'desc' => 'Tinea / dermatophytosis',
             'items' => [
                ['generic' => 'Itraconazole', 'dose_unit' => 'capsule', 'dose_amount' => 1, 'freq' => '1-0-1', 'days' => 14, 'food' => 'after'],
                ['generic' => 'Levocetirizine', 'dose_unit' => 'tablet', 'dose_amount' => 1, 'freq' => '0-0-1', 'days' => 14, 'food' => 'after', 'instructions' => 'For itch'],
             ]],
            ['name' => 'Urticaria / Allergic Rash', 'desc' => 'Acute urticaria',
             'items' => [
                ['generic' => 'Levocetirizine', 'dose_unit' => 'tablet', 'dose_amount' => 1, 'freq' => '0-0-1', 'days' => 7, 'food' => 'after'],
                ['generic' => 'Fexofenadine', 'dose_unit' => 'tablet', 'dose_amount' => 1, 'freq' => '1-0-0', 'days' => 7, 'food' => 'after'],
             ]],
            ['name' => 'Bacterial Skin Infection', 'desc' => 'Impetigo / pyoderma',
             'items' => [
                ['generic' => 'Cephalexin', 'dose_unit' => 'capsule', 'dose_amount' => 1, 'freq' => '1-0-1', 'days' => 7, 'food' => 'after'],
             ]],
            ['name' => 'Eczema / Dermatitis', 'desc' => 'Itch control',
             'items' => [
                ['generic' => 'Hydroxyzine', 'dose_unit' => 'tablet', 'dose_amount' => 1, 'freq' => '0-0-1', 'days' => 10, 'food' => 'after'],
             ]],
        ],
    ],

    // ===================== ENT =====================
    'ent' => [
        'label' => 'ENT',
        'conditions' => [
            ['name' => 'Acute Otitis Media', 'desc' => 'Ear infection',
             'items' => [
                ['generic' => 'Amoxycillin', 'dose_unit' => 'capsule', 'dose_amount' => 1, 'freq' => '1-0-1', 'days' => 5, 'food' => 'after'],
                ['generic' => 'Paracetamol', 'dose_unit' => 'tablet', 'dose_amount' => 1, 'freq' => '1-1-1', 'days' => 3, 'food' => 'after'],
             ]],
            ['name' => 'Acute Sinusitis', 'desc' => 'Bacterial sinusitis',
             'items' => [
                ['generic' => 'Amoxycillin', 'dose_unit' => 'capsule', 'dose_amount' => 1, 'freq' => '1-0-1', 'days' => 7, 'food' => 'after'],
                ['generic' => 'Montelukast', 'dose_unit' => 'tablet', 'dose_amount' => 1, 'freq' => '0-0-1', 'days' => 10, 'food' => 'after'],
             ]],
            ['name' => 'Allergic Rhinitis', 'desc' => 'Nasal allergy',
             'items' => [
                ['generic' => 'Levocetirizine', 'dose_unit' => 'tablet', 'dose_amount' => 1, 'freq' => '0-0-1', 'days' => 14, 'food' => 'after'],
                ['generic' => 'Montelukast', 'dose_unit' => 'tablet', 'dose_amount' => 1, 'freq' => '0-0-1', 'days' => 14, 'food' => 'after'],
             ]],
            ['name' => 'Vertigo', 'desc' => 'Vestibular symptoms',
             'items' => [
                ['generic' => 'Betahistine', 'dose_unit' => 'tablet', 'dose_amount' => 1, 'freq' => '1-0-1', 'days' => 7, 'food' => 'after'],
             ]],
        ],
    ],

    // ===================== ORTHOPEDICS =====================
    'orthopedics' => [
        'label' => 'Orthopedics',
        'conditions' => [
            ['name' => 'Acute Low Back Pain', 'desc' => 'Mechanical back pain',
             'items' => [
                ['generic' => 'Diclofenac', 'dose_unit' => 'tablet', 'dose_amount' => 1, 'freq' => '1-0-1', 'days' => 5, 'food' => 'after'],
                ['generic' => 'Thiocolchicoside', 'dose_unit' => 'capsule', 'dose_amount' => 1, 'freq' => '1-0-1', 'days' => 5, 'food' => 'after'],
                ['generic' => 'Pantoprazole', 'dose_unit' => 'tablet', 'dose_amount' => 1, 'freq' => '1-0-0', 'days' => 5, 'food' => 'before'],
             ]],
            ['name' => 'Osteoarthritis Flare', 'desc' => 'Joint pain',
             'items' => [
                ['generic' => 'Aceclofenac', 'dose_unit' => 'tablet', 'dose_amount' => 1, 'freq' => '1-0-1', 'days' => 7, 'food' => 'after'],
                ['generic' => 'Pantoprazole', 'dose_unit' => 'tablet', 'dose_amount' => 1, 'freq' => '1-0-0', 'days' => 7, 'food' => 'before'],
             ]],
            ['name' => 'Calcium / Bone Support', 'desc' => 'Supplementation',
             'items' => [
                ['generic' => 'Calcium', 'dose_unit' => 'tablet', 'dose_amount' => 1, 'freq' => '1-0-1', 'days' => 30, 'food' => 'after'],
                ['generic' => 'Vitamin D3', 'dose_unit' => 'sachet', 'dose_amount' => 1, 'freq' => '0-0-0', 'days' => 7, 'food' => 'after', 'instructions' => 'Once weekly'],
             ]],
        ],
    ],

    // ===================== GYNECOLOGY =====================
    'gynecology' => [
        'label' => 'Gynecology',
        'conditions' => [
            ['name' => 'Bacterial Vaginosis', 'desc' => 'Vaginal infection',
             'items' => [
                ['generic' => 'Metronidazole', 'dose_unit' => 'tablet', 'dose_amount' => 1, 'freq' => '1-0-1', 'days' => 7, 'food' => 'after'],
             ]],
            ['name' => 'Dysmenorrhea', 'desc' => 'Painful menses',
             'items' => [
                ['generic' => 'Mefenamic Acid', 'dose_unit' => 'tablet', 'dose_amount' => 1, 'freq' => '1-0-1', 'days' => 3, 'food' => 'after'],
             ]],
            ['name' => 'Iron Deficiency Anemia', 'desc' => 'Supplementation',
             'items' => [
                ['generic' => 'Ferrous Ascorbate', 'dose_unit' => 'tablet', 'dose_amount' => 1, 'freq' => '1-0-0', 'days' => 30, 'food' => 'after'],
                ['generic' => 'Folic Acid', 'dose_unit' => 'tablet', 'dose_amount' => 1, 'freq' => '1-0-0', 'days' => 30, 'food' => 'after'],
             ]],
            ['name' => 'Antenatal Supplements', 'desc' => 'Routine pregnancy support',
             'items' => [
                ['generic' => 'Folic Acid', 'dose_unit' => 'tablet', 'dose_amount' => 1, 'freq' => '1-0-0', 'days' => 30, 'food' => 'after'],
                ['generic' => 'Calcium', 'dose_unit' => 'tablet', 'dose_amount' => 1, 'freq' => '0-0-1', 'days' => 30, 'food' => 'after'],
             ]],
        ],
    ],

    // ===================== CARDIOLOGY =====================
    'cardiology' => [
        'label' => 'Cardiology',
        'conditions' => [
            ['name' => 'Hypertension (starter)', 'desc' => 'First-line; titrate',
             'items' => [
                ['generic' => 'Amlodipine', 'dose_unit' => 'tablet', 'dose_amount' => 1, 'freq' => '1-0-0', 'days' => 30, 'food' => 'after'],
                ['generic' => 'Telmisartan', 'dose_unit' => 'tablet', 'dose_amount' => 1, 'freq' => '1-0-0', 'days' => 30, 'food' => 'after'],
             ]],
            ['name' => 'Dyslipidemia', 'desc' => 'Lipid lowering',
             'items' => [
                ['generic' => 'Atorvastatin', 'dose_unit' => 'tablet', 'dose_amount' => 1, 'freq' => '0-0-1', 'days' => 30, 'food' => 'after'],
             ]],
            ['name' => 'Antiplatelet (secondary prevention)', 'desc' => 'Post-event',
             'items' => [
                ['generic' => 'Aspirin', 'dose_unit' => 'tablet', 'dose_amount' => 1, 'freq' => '0-1-0', 'days' => 30, 'food' => 'after'],
                ['generic' => 'Clopidogrel', 'dose_unit' => 'tablet', 'dose_amount' => 1, 'freq' => '0-1-0', 'days' => 30, 'food' => 'after'],
             ]],
        ],
    ],

    // ===================== DIABETOLOGY / ENDOCRINOLOGY =====================
    'diabetology' => [
        'label' => 'Diabetology',
        'conditions' => [
            ['name' => 'Type 2 Diabetes (starter)', 'desc' => 'First-line',
             'items' => [
                ['generic' => 'Metformin', 'dose_unit' => 'tablet', 'dose_amount' => 1, 'freq' => '1-0-1', 'days' => 30, 'food' => 'after'],
             ]],
            ['name' => 'T2DM Add-on', 'desc' => 'Second agent',
             'items' => [
                ['generic' => 'Metformin', 'dose_unit' => 'tablet', 'dose_amount' => 1, 'freq' => '1-0-1', 'days' => 30, 'food' => 'after'],
                ['generic' => 'Glimepiride', 'dose_unit' => 'tablet', 'dose_amount' => 1, 'freq' => '1-0-0', 'days' => 30, 'food' => 'before'],
             ]],
            ['name' => 'Hypothyroidism', 'desc' => 'Thyroid replacement',
             'items' => [
                ['generic' => 'Thyroxine', 'dose_unit' => 'tablet', 'dose_amount' => 1, 'freq' => '1-0-0', 'days' => 30, 'food' => 'empty', 'instructions' => 'Empty stomach, before breakfast'],
             ]],
        ],
    ],

    // ===================== GASTROENTEROLOGY =====================
    'gastroenterology' => [
        'label' => 'Gastroenterology',
        'conditions' => [
            ['name' => 'GERD', 'desc' => 'Reflux',
             'items' => [
                ['generic' => 'Pantoprazole', 'dose_unit' => 'tablet', 'dose_amount' => 1, 'freq' => '1-0-0', 'days' => 28, 'food' => 'before'],
                ['generic' => 'Domperidone', 'dose_unit' => 'tablet', 'dose_amount' => 1, 'freq' => '1-0-1', 'days' => 14, 'food' => 'before'],
             ]],
            ['name' => 'Irritable Bowel (diarrhea)', 'desc' => 'IBS-D',
             'items' => [
                ['generic' => 'Rifaximin', 'dose_unit' => 'tablet', 'dose_amount' => 1, 'freq' => '1-0-1', 'days' => 14, 'food' => 'after'],
             ]],
            ['name' => 'Constipation', 'desc' => 'Functional constipation',
             'items' => [
                ['generic' => 'Lactulose', 'dose_unit' => 'ml', 'dose_amount' => 15, 'freq' => '0-0-1', 'days' => 14, 'food' => 'after'],
             ]],
        ],
    ],

    // ===================== PSYCHIATRY =====================
    'psychiatry' => [
        'label' => 'Psychiatry',
        'conditions' => [
            ['name' => 'Depression (starter SSRI)', 'desc' => 'First-line; review in 2-4w',
             'items' => [
                ['generic' => 'Escitalopram', 'dose_unit' => 'tablet', 'dose_amount' => 1, 'freq' => '1-0-0', 'days' => 30, 'food' => 'after'],
             ]],
            ['name' => 'Generalized Anxiety', 'desc' => 'SSRI-based',
             'items' => [
                ['generic' => 'Sertraline', 'dose_unit' => 'tablet', 'dose_amount' => 1, 'freq' => '1-0-0', 'days' => 30, 'food' => 'after'],
             ]],
            ['name' => 'Insomnia (short-term)', 'desc' => 'Brief use only',
             'items' => [
                ['generic' => 'Melatonin', 'dose_unit' => 'tablet', 'dose_amount' => 1, 'freq' => '0-0-1', 'days' => 10, 'food' => 'bedtime'],
             ]],
        ],
    ],

    // ===================== PULMONOLOGY / CHEST =====================
    'pulmonology' => [
        'label' => 'Pulmonology',
        'conditions' => [
            ['name' => 'Acute Bronchitis', 'desc' => 'Lower respiratory',
             'items' => [
                ['generic' => 'Azithromycin', 'dose_unit' => 'tablet', 'dose_amount' => 1, 'freq' => '1-0-0', 'days' => 5, 'food' => 'after'],
                ['generic' => 'Ambroxol', 'dose_unit' => 'ml', 'dose_amount' => 10, 'freq' => '1-1-1', 'days' => 5, 'food' => 'after'],
             ]],
            ['name' => 'Asthma / Bronchospasm', 'desc' => 'Reliever + controller',
             'items' => [
                ['generic' => 'Salbutamol', 'dose_unit' => 'puff', 'dose_amount' => 2, 'freq' => 'SOS', 'days' => 30, 'food' => 'any', 'instructions' => 'As reliever'],
                ['generic' => 'Montelukast', 'dose_unit' => 'tablet', 'dose_amount' => 1, 'freq' => '0-0-1', 'days' => 30, 'food' => 'after'],
             ]],
        ],
    ],

    // ===================== OPHTHALMOLOGY =====================
    'ophthalmology' => [
        'label' => 'Ophthalmology',
        'conditions' => [
            ['name' => 'Bacterial Conjunctivitis', 'desc' => 'Red eye, discharge',
             'items' => [
                ['generic' => 'Moxifloxacin', 'dose_unit' => 'drops', 'dose_amount' => 1, 'freq' => '1-1-1', 'days' => 5, 'food' => 'any', 'instructions' => '1 drop, both eyes'],
             ]],
            ['name' => 'Allergic Conjunctivitis', 'desc' => 'Itchy eyes',
             'items' => [
                ['generic' => 'Olopatadine', 'dose_unit' => 'drops', 'dose_amount' => 1, 'freq' => '1-0-1', 'days' => 14, 'food' => 'any'],
             ]],
            ['name' => 'Dry Eye', 'desc' => 'Lubrication',
             'items' => [
                ['generic' => 'Carboxymethylcellulose', 'dose_unit' => 'drops', 'dose_amount' => 1, 'freq' => '1-1-1', 'days' => 30, 'food' => 'any'],
             ]],
        ],
    ],

    // ===================== UROLOGY =====================
    'urology' => [
        'label' => 'Urology',
        'conditions' => [
            ['name' => 'Uncomplicated UTI', 'desc' => 'Lower urinary tract infection',
             'items' => [
                ['generic' => 'Nitrofurantoin', 'dose_unit' => 'tablet', 'dose_amount' => 1, 'freq' => '1-0-1', 'days' => 5, 'food' => 'after'],
             ]],
            ['name' => 'BPH (symptomatic)', 'desc' => 'Prostatic symptoms',
             'items' => [
                ['generic' => 'Tamsulosin', 'dose_unit' => 'capsule', 'dose_amount' => 1, 'freq' => '0-0-1', 'days' => 30, 'food' => 'after'],
             ]],
        ],
    ],

];
