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
                ['generic' => 'Vitamin B', 'dose_unit' => 'tablet', 'dose_amount' => 1, 'freq' => '1-0-0', 'days' => 30, 'food' => 'after'],
                ['generic' => 'Vitamin D3', 'dose_unit' => 'sachet', 'dose_amount' => 1, 'freq' => '0-0-0', 'days' => 7, 'food' => 'after', 'instructions' => 'Once weekly'],
             ]],
        ],
    ],

    // ===================== PEDIATRICS =====================
    'peds' => [
        'label' => 'Pediatrician',
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
    'derma' => [
        'label' => 'Dermatologist',
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
                ['generic' => 'Cefalexin', 'dose_unit' => 'capsule', 'dose_amount' => 1, 'freq' => '1-0-1', 'days' => 7, 'food' => 'after'],
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
    'ortho' => [
        'label' => 'Orthopedic doctor',
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
    'gyno' => [
        'label' => 'Gynecologist',
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
    'cardio' => [
        'label' => 'Cardiologist',
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
    'gastro' => [
        'label' => 'Gastroenterologist',
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
    'psychiatrist' => [
        'label' => 'Psychiatrist',
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
    'eye' => [
        'label' => 'Ophthalmologist',
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
    'urologist' => [
        'label' => 'Urologist',
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

    // ===================== NEUROLOGY =====================
    'neuro' => [
        'label' => 'Neurologist',
        'conditions' => [
            ['name' => 'Migraine (acute)', 'desc' => 'Acute attack',
             'items' => [
                ['generic' => 'Sumatriptan', 'dose_unit' => 'tablet', 'dose_amount' => 1, 'freq' => 'SOS', 'days' => 5, 'food' => 'after'],
                ['generic' => 'Naproxen', 'dose_unit' => 'tablet', 'dose_amount' => 1, 'freq' => 'SOS', 'days' => 5, 'food' => 'after'],
             ]],
            ['name' => 'Migraine Prophylaxis', 'desc' => 'Preventive',
             'items' => [
                ['generic' => 'Propranolol', 'dose_unit' => 'tablet', 'dose_amount' => 1, 'freq' => '1-0-1', 'days' => 30, 'food' => 'after'],
             ]],
            ['name' => 'Neuropathic Pain', 'desc' => 'Peripheral neuropathy',
             'items' => [
                ['generic' => 'Pregabalin', 'dose_unit' => 'capsule', 'dose_amount' => 1, 'freq' => '0-0-1', 'days' => 30, 'food' => 'after'],
                ['generic' => 'Methylcobalamin', 'dose_unit' => 'tablet', 'dose_amount' => 1, 'freq' => '1-0-0', 'days' => 30, 'food' => 'after'],
             ]],
        ],
    ],

    // ===================== NEPHROLOGY =====================
    'nephrology' => [
        'label' => 'Nephrologist',
        'conditions' => [
            ['name' => 'CKD — BP control', 'desc' => 'Renoprotective',
             'items' => [
                ['generic' => 'Telmisartan', 'dose_unit' => 'tablet', 'dose_amount' => 1, 'freq' => '1-0-0', 'days' => 30, 'food' => 'after'],
             ]],
            ['name' => 'Edema / Fluid Overload', 'desc' => 'Diuresis',
             'items' => [
                ['generic' => 'Furosemide', 'dose_unit' => 'tablet', 'dose_amount' => 1, 'freq' => '1-0-0', 'days' => 14, 'food' => 'after'],
             ]],
        ],
    ],

    // ===================== ENDOCRINOLOGY =====================
    'endocrinology' => [
        'label' => 'Endocrinologist',
        'conditions' => [
            ['name' => 'Hypothyroidism', 'desc' => 'Thyroid replacement',
             'items' => [
                ['generic' => 'Thyroxine', 'dose_unit' => 'tablet', 'dose_amount' => 1, 'freq' => '1-0-0', 'days' => 30, 'food' => 'empty', 'instructions' => 'Empty stomach, before breakfast'],
             ]],
            ['name' => 'Type 2 Diabetes', 'desc' => 'First-line',
             'items' => [
                ['generic' => 'Metformin', 'dose_unit' => 'tablet', 'dose_amount' => 1, 'freq' => '1-0-1', 'days' => 30, 'food' => 'after'],
                ['generic' => 'Sitagliptin', 'dose_unit' => 'tablet', 'dose_amount' => 1, 'freq' => '1-0-0', 'days' => 30, 'food' => 'after'],
             ]],
        ],
    ],

    // ===================== RHEUMATOLOGY =====================
    'rheumatology' => [
        'label' => 'Rheumatologist',
        'conditions' => [
            ['name' => 'Gout (acute)', 'desc' => 'Acute flare',
             'items' => [
                ['generic' => 'Etoricoxib', 'dose_unit' => 'tablet', 'dose_amount' => 1, 'freq' => '1-0-0', 'days' => 5, 'food' => 'after'],
                ['generic' => 'Pantoprazole', 'dose_unit' => 'tablet', 'dose_amount' => 1, 'freq' => '1-0-0', 'days' => 5, 'food' => 'before'],
             ]],
            ['name' => 'Gout (maintenance)', 'desc' => 'Urate lowering',
             'items' => [
                ['generic' => 'Febuxostat', 'dose_unit' => 'tablet', 'dose_amount' => 1, 'freq' => '1-0-0', 'days' => 30, 'food' => 'after'],
             ]],
            ['name' => 'Rheumatoid Arthritis (symptomatic)', 'desc' => 'Pain control; DMARDs by specialist',
             'items' => [
                ['generic' => 'Naproxen', 'dose_unit' => 'tablet', 'dose_amount' => 1, 'freq' => '1-0-1', 'days' => 7, 'food' => 'after'],
             ]],
        ],
    ],

    // ===================== ALLERGY / IMMUNOLOGY =====================
    'allergy' => [
        'label' => 'Allergist',
        'conditions' => [
            ['name' => 'Allergic Rhinitis', 'desc' => 'Nasal allergy',
             'items' => [
                ['generic' => 'Fexofenadine', 'dose_unit' => 'tablet', 'dose_amount' => 1, 'freq' => '1-0-0', 'days' => 14, 'food' => 'after'],
                ['generic' => 'Montelukast', 'dose_unit' => 'tablet', 'dose_amount' => 1, 'freq' => '0-0-1', 'days' => 14, 'food' => 'after'],
             ]],
            ['name' => 'Chronic Urticaria', 'desc' => 'Antihistamine-based',
             'items' => [
                ['generic' => 'Levocetirizine', 'dose_unit' => 'tablet', 'dose_amount' => 1, 'freq' => '0-0-1', 'days' => 28, 'food' => 'after'],
             ]],
        ],
    ],

    // ===================== PAIN MANAGEMENT =====================
    'pain_management' => [
        'label' => 'Pain management specialist',
        'conditions' => [
            ['name' => 'Chronic Musculoskeletal Pain', 'desc' => 'Multimodal',
             'items' => [
                ['generic' => 'Etoricoxib', 'dose_unit' => 'tablet', 'dose_amount' => 1, 'freq' => '1-0-0', 'days' => 7, 'food' => 'after'],
                ['generic' => 'Pantoprazole', 'dose_unit' => 'tablet', 'dose_amount' => 1, 'freq' => '1-0-0', 'days' => 7, 'food' => 'before'],
             ]],
            ['name' => 'Neuropathic Pain', 'desc' => 'Nerve pain',
             'items' => [
                ['generic' => 'Pregabalin', 'dose_unit' => 'capsule', 'dose_amount' => 1, 'freq' => '0-0-1', 'days' => 30, 'food' => 'after'],
                ['generic' => 'Duloxetine', 'dose_unit' => 'capsule', 'dose_amount' => 1, 'freq' => '1-0-0', 'days' => 30, 'food' => 'after'],
             ]],
        ],
    ],

    // ===================== DENTAL =====================
    'dental' => [
        'label' => 'Dentist',
        'conditions' => [
            ['name' => 'Dental Infection / Abscess', 'desc' => 'Odontogenic infection',
             'items' => [
                ['generic' => 'Amoxycillin', 'dose_unit' => 'capsule', 'dose_amount' => 1, 'freq' => '1-0-1', 'days' => 5, 'food' => 'after'],
                ['generic' => 'Metronidazole', 'dose_unit' => 'tablet', 'dose_amount' => 1, 'freq' => '1-1-1', 'days' => 5, 'food' => 'after'],
                ['generic' => 'Diclofenac', 'dose_unit' => 'tablet', 'dose_amount' => 1, 'freq' => '1-0-1', 'days' => 3, 'food' => 'after'],
             ]],
            ['name' => 'Post-extraction', 'desc' => 'Analgesia + cover',
             'items' => [
                ['generic' => 'Aceclofenac', 'dose_unit' => 'tablet', 'dose_amount' => 1, 'freq' => '1-0-1', 'days' => 3, 'food' => 'after'],
                ['generic' => 'Chlorhexidine', 'dose_unit' => 'ml', 'dose_amount' => 10, 'freq' => '1-0-1', 'days' => 5, 'food' => 'after', 'instructions' => 'Mouth rinse, do not swallow'],
             ]],
        ],
    ],

    // ===================== PHYSIOTHERAPY (supportive meds) =====================
    'physio' => [
        'label' => 'Physiotherapist',
        'conditions' => [
            ['name' => 'Muscle Spasm (adjunct)', 'desc' => 'Supportive to therapy',
             'items' => [
                ['generic' => 'Thiocolchicoside', 'dose_unit' => 'capsule', 'dose_amount' => 1, 'freq' => '1-0-1', 'days' => 5, 'food' => 'after'],
             ]],
        ],
    ],

    // ===================== FAMILY MEDICINE =====================
    'family_medicine' => [
        'label' => 'Family medicine doctor',
        'conditions' => [
            ['name' => 'Common Cold', 'desc' => 'Viral URI',
             'items' => [
                ['generic' => 'Paracetamol', 'dose_unit' => 'tablet', 'dose_amount' => 1, 'freq' => '1-1-1', 'days' => 3, 'food' => 'after'],
                ['generic' => 'Levocetirizine', 'dose_unit' => 'tablet', 'dose_amount' => 1, 'freq' => '0-0-1', 'days' => 5, 'food' => 'after'],
             ]],
            ['name' => 'Acidity / GERD', 'desc' => 'Dyspepsia',
             'items' => [
                ['generic' => 'Pantoprazole', 'dose_unit' => 'tablet', 'dose_amount' => 1, 'freq' => '1-0-0', 'days' => 14, 'food' => 'before'],
             ]],
            ['name' => 'Hypertension (starter)', 'desc' => 'First-line',
             'items' => [
                ['generic' => 'Amlodipine', 'dose_unit' => 'tablet', 'dose_amount' => 1, 'freq' => '1-0-0', 'days' => 30, 'food' => 'after'],
             ]],
        ],
    ],

    // ===================== GASTRO / HEPATOLOGY =====================
    'hepatology' => [
        'label' => 'Hepatologist',
        'conditions' => [
            ['name' => 'Fatty Liver (supportive)', 'desc' => 'Lifestyle + support',
             'items' => [
                ['generic' => 'Ursodeoxycholic', 'dose_unit' => 'tablet', 'dose_amount' => 1, 'freq' => '1-0-1', 'days' => 30, 'food' => 'after'],
             ]],
        ],
    ],

    // ===================== TRICHOLOGY =====================
    'trichology' => [
        'label' => 'Trichologist',
        'conditions' => [
            ['name' => 'Androgenetic Hair Loss (male)', 'desc' => 'Topical + oral',
             'items' => [
                ['generic' => 'Finasteride', 'dose_unit' => 'tablet', 'dose_amount' => 1, 'freq' => '1-0-0', 'days' => 30, 'food' => 'after'],
                ['generic' => 'Minoxidil', 'dose_unit' => 'ml', 'dose_amount' => 1, 'freq' => '1-0-1', 'days' => 30, 'food' => 'any', 'instructions' => 'Apply to scalp'],
             ]],
        ],
    ],

    // ===================== SEXOLOGY / ANDROLOGY =====================
    'andrology' => [
        'label' => 'Andrologist',
        'conditions' => [
            ['name' => 'Erectile Dysfunction', 'desc' => 'On-demand',
             'items' => [
                ['generic' => 'Tadalafil', 'dose_unit' => 'tablet', 'dose_amount' => 1, 'freq' => 'SOS', 'days' => 10, 'food' => 'any'],
             ]],
        ],
    ],
    'sexology' => [
        'label' => 'Sexologist',
        'conditions' => [
            ['name' => 'Erectile Dysfunction', 'desc' => 'On-demand',
             'items' => [
                ['generic' => 'Sildenafil', 'dose_unit' => 'tablet', 'dose_amount' => 1, 'freq' => 'SOS', 'days' => 10, 'food' => 'empty', 'instructions' => 'On empty stomach, 1h before activity'],
             ]],
        ],
    ],

    // ===================== PSYCHOLOGY (limited pharma) =====================
    'psychologist' => [
        'label' => 'Psychologist',
        'conditions' => [
            ['name' => 'Sleep Hygiene Support', 'desc' => 'Short-term aid (refer for Rx)',
             'items' => [
                ['generic' => 'Melatonin', 'dose_unit' => 'tablet', 'dose_amount' => 1, 'freq' => '0-0-1', 'days' => 10, 'food' => 'bedtime'],
             ]],
        ],
    ],

    // ===================== GENERAL SURGERY =====================
    'general_surgery' => [
        'label' => 'General surgeon',
        'conditions' => [
            ['name' => 'Post-op Analgesia + Cover', 'desc' => 'Routine post-operative',
             'items' => [
                ['generic' => 'Aceclofenac', 'dose_unit' => 'tablet', 'dose_amount' => 1, 'freq' => '1-0-1', 'days' => 5, 'food' => 'after'],
                ['generic' => 'Pantoprazole', 'dose_unit' => 'tablet', 'dose_amount' => 1, 'freq' => '1-0-0', 'days' => 5, 'food' => 'before'],
                ['generic' => 'Cefixime', 'dose_unit' => 'tablet', 'dose_amount' => 1, 'freq' => '1-0-1', 'days' => 5, 'food' => 'after'],
             ]],
            ['name' => 'Hemorrhoids / Piles', 'desc' => 'Conservative',
             'items' => [
                ['generic' => 'Diosmin', 'dose_unit' => 'tablet', 'dose_amount' => 1, 'freq' => '1-0-1', 'days' => 7, 'food' => 'after'],
             ]],
        ],
    ],

    // ===================== DIETITIAN (supplements only) =====================
    'dietitian' => [
        'label' => 'Dietitian',
        'conditions' => [
            ['name' => 'Iron Deficiency Support', 'desc' => 'Nutritional support',
             'items' => [
                ['generic' => 'Ferrous Ascorbate', 'dose_unit' => 'tablet', 'dose_amount' => 1, 'freq' => '1-0-0', 'days' => 30, 'food' => 'after'],
             ]],
            ['name' => 'General Supplementation', 'desc' => 'Vitamins',
             'items' => [
                ['generic' => 'Vitamin D3', 'dose_unit' => 'sachet', 'dose_amount' => 1, 'freq' => '0-0-0', 'days' => 7, 'food' => 'after', 'instructions' => 'Once weekly'],
                ['generic' => 'Vitamin B', 'dose_unit' => 'tablet', 'dose_amount' => 1, 'freq' => '1-0-0', 'days' => 30, 'food' => 'after'],
             ]],
        ],
    ],

    // =====================================================================
    // BELOW: previously-skipped specialties. ALL seeded INACTIVE ('active'=>0)
    // for a doctor to review/activate. Content is conservative SUPPORTIVE
    // care only — NOT disease-specific protocols. AYUSH systems use their
    // own formulary (herbs not in this allopathic catalog), so their items
    // are co-prescribed allopathic supportives the practitioner can replace.
    // =====================================================================

    // ---- AYUSH (review: replace with system-specific formulary) ----
    'ayurveda' => [
        'label' => 'Ayurveda doctor', 'active' => 0,
        'conditions' => [
            ['name' => 'Supportive — Fever (review)', 'desc' => 'Allopathic supportive; replace with Ayurvedic formulary',
             'items' => [
                ['generic' => 'Paracetamol', 'dose_unit' => 'tablet', 'dose_amount' => 1, 'freq' => '1-1-1', 'days' => 3, 'food' => 'after'],
             ]],
        ],
    ],
    'homeopathy' => [
        'label' => 'Homeopathy doctor', 'active' => 0, 'mode' => 'homeopathic',
        'conditions' => [
            ['name' => 'Supportive — Fever (review)', 'desc' => 'Placeholder; homeopathy uses remedies, not drugs',
             'items' => [
                ['generic' => 'Paracetamol', 'dose_unit' => 'tablet', 'dose_amount' => 1, 'freq' => 'SOS', 'days' => 3, 'food' => 'after'],
             ]],
        ],
    ],
    'siddha' => [
        'label' => 'Siddha doctor', 'active' => 0,
        'conditions' => [
            ['name' => 'Supportive — Pain (review)', 'desc' => 'Replace with Siddha formulary',
             'items' => [
                ['generic' => 'Paracetamol', 'dose_unit' => 'tablet', 'dose_amount' => 1, 'freq' => 'SOS', 'days' => 3, 'food' => 'after'],
             ]],
        ],
    ],
    'unani' => [
        'label' => 'Unani doctor', 'active' => 0,
        'conditions' => [
            ['name' => 'Supportive — Pain (review)', 'desc' => 'Replace with Unani formulary',
             'items' => [
                ['generic' => 'Paracetamol', 'dose_unit' => 'tablet', 'dose_amount' => 1, 'freq' => 'SOS', 'days' => 3, 'food' => 'after'],
             ]],
        ],
    ],
    'naturopathy' => [
        'label' => 'Naturopathy doctor', 'active' => 0,
        'conditions' => [
            ['name' => 'Supportive — Supplements (review)', 'desc' => 'Replace with naturopathy plan',
             'items' => [
                ['generic' => 'Vitamin D3', 'dose_unit' => 'sachet', 'dose_amount' => 1, 'freq' => '0-0-0', 'days' => 7, 'food' => 'after', 'instructions' => 'Once weekly'],
             ]],
        ],
    ],
    'acupuncturist' => [
        'label' => 'Acupuncturist', 'active' => 0,
        'conditions' => [
            ['name' => 'Supportive — Pain (review)', 'desc' => 'Adjunct only',
             'items' => [
                ['generic' => 'Paracetamol', 'dose_unit' => 'tablet', 'dose_amount' => 1, 'freq' => 'SOS', 'days' => 3, 'food' => 'after'],
             ]],
        ],
    ],

    // ---- Oncology / high-risk (review: specialist oversight required) ----
    'oncology' => [
        'label' => 'Oncologist', 'active' => 0,
        'conditions' => [
            ['name' => 'Chemo-induced Nausea (review)', 'desc' => 'Supportive antiemetic',
             'items' => [
                ['generic' => 'Ondansetron', 'dose_unit' => 'tablet', 'dose_amount' => 1, 'freq' => '1-0-1', 'days' => 5, 'food' => 'before'],
                ['generic' => 'Dexamethasone', 'dose_unit' => 'tablet', 'dose_amount' => 1, 'freq' => '1-0-0', 'days' => 3, 'food' => 'after'],
             ]],
            ['name' => 'Cancer Pain (review)', 'desc' => 'Step analgesia — titrate',
             'items' => [
                ['generic' => 'Tramadol', 'dose_unit' => 'capsule', 'dose_amount' => 1, 'freq' => '1-0-1', 'days' => 7, 'food' => 'after'],
             ]],
        ],
    ],
    'hematology' => [
        'label' => 'Hematologist', 'active' => 0,
        'conditions' => [
            ['name' => 'Iron Deficiency Anemia (review)', 'desc' => 'Oral iron',
             'items' => [
                ['generic' => 'Ferrous Ascorbate', 'dose_unit' => 'tablet', 'dose_amount' => 1, 'freq' => '1-0-0', 'days' => 30, 'food' => 'after'],
                ['generic' => 'Folic Acid', 'dose_unit' => 'tablet', 'dose_amount' => 1, 'freq' => '1-0-0', 'days' => 30, 'food' => 'after'],
             ]],
        ],
    ],

    // ---- Surgical specialties (review: post-op supportive) ----
    'neurosurgery' => [
        'label' => 'Neurosurgeon', 'active' => 0,
        'conditions' => [
            ['name' => 'Post-op Supportive (review)', 'desc' => 'Analgesia + gastroprotection',
             'items' => [
                ['generic' => 'Paracetamol', 'dose_unit' => 'tablet', 'dose_amount' => 1, 'freq' => '1-1-1', 'days' => 5, 'food' => 'after'],
                ['generic' => 'Pantoprazole', 'dose_unit' => 'tablet', 'dose_amount' => 1, 'freq' => '1-0-0', 'days' => 5, 'food' => 'before'],
             ]],
        ],
    ],
    'plastic_surgery' => [
        'label' => 'Plastic surgeon', 'active' => 0,
        'conditions' => [
            ['name' => 'Post-op Supportive (review)', 'desc' => 'Analgesia + cover',
             'items' => [
                ['generic' => 'Aceclofenac', 'dose_unit' => 'tablet', 'dose_amount' => 1, 'freq' => '1-0-1', 'days' => 5, 'food' => 'after'],
                ['generic' => 'Cefixime', 'dose_unit' => 'tablet', 'dose_amount' => 1, 'freq' => '1-0-1', 'days' => 5, 'food' => 'after'],
             ]],
        ],
    ],
    'bariatric' => [
        'label' => 'Bariatric surgeon', 'active' => 0,
        'conditions' => [
            ['name' => 'Post-op Supportive (review)', 'desc' => 'PPI + supplements',
             'items' => [
                ['generic' => 'Pantoprazole', 'dose_unit' => 'tablet', 'dose_amount' => 1, 'freq' => '1-0-0', 'days' => 30, 'food' => 'before'],
                ['generic' => 'Vitamin B', 'dose_unit' => 'tablet', 'dose_amount' => 1, 'freq' => '1-0-0', 'days' => 30, 'food' => 'after'],
             ]],
        ],
    ],
    'vascular' => [
        'label' => 'Vascular surgeon', 'active' => 0,
        'conditions' => [
            ['name' => 'Antiplatelet (review)', 'desc' => 'Secondary prevention',
             'items' => [
                ['generic' => 'Aspirin', 'dose_unit' => 'tablet', 'dose_amount' => 1, 'freq' => '0-1-0', 'days' => 30, 'food' => 'after'],
             ]],
        ],
    ],
    'gi_surgery' => [
        'label' => 'GI surgeon', 'active' => 0,
        'conditions' => [
            ['name' => 'Post-op Supportive (review)', 'desc' => 'Analgesia + cover',
             'items' => [
                ['generic' => 'Aceclofenac', 'dose_unit' => 'tablet', 'dose_amount' => 1, 'freq' => '1-0-1', 'days' => 5, 'food' => 'after'],
                ['generic' => 'Pantoprazole', 'dose_unit' => 'tablet', 'dose_amount' => 1, 'freq' => '1-0-0', 'days' => 5, 'food' => 'before'],
             ]],
        ],
    ],
    'spine' => [
        'label' => 'Spine surgeon', 'active' => 0,
        'conditions' => [
            ['name' => 'Back Pain Supportive (review)', 'desc' => 'Analgesia + muscle relaxant',
             'items' => [
                ['generic' => 'Aceclofenac', 'dose_unit' => 'tablet', 'dose_amount' => 1, 'freq' => '1-0-1', 'days' => 5, 'food' => 'after'],
                ['generic' => 'Thiocolchicoside', 'dose_unit' => 'capsule', 'dose_amount' => 1, 'freq' => '1-0-1', 'days' => 5, 'food' => 'after'],
             ]],
        ],
    ],
    'sports_medicine' => [
        'label' => 'Sports medicine doctor', 'active' => 0,
        'conditions' => [
            ['name' => 'Soft-tissue Injury (review)', 'desc' => 'Anti-inflammatory',
             'items' => [
                ['generic' => 'Naproxen', 'dose_unit' => 'tablet', 'dose_amount' => 1, 'freq' => '1-0-1', 'days' => 5, 'food' => 'after'],
             ]],
        ],
    ],
    'critical_care' => [
        'label' => 'Critical care specialist', 'active' => 0,
        'conditions' => [
            ['name' => 'Stress Ulcer Prophylaxis (review)', 'desc' => 'ICU supportive',
             'items' => [
                ['generic' => 'Pantoprazole', 'dose_unit' => 'tablet', 'dose_amount' => 1, 'freq' => '1-0-0', 'days' => 7, 'food' => 'before'],
             ]],
        ],
    ],

    // ---- Diagnostic / procedure-based (review: minimal Rx) ----
    'radiology' => [
        'label' => 'Radiologist', 'active' => 0,
        'conditions' => [
            ['name' => 'Contrast Premedication (review)', 'desc' => 'Allergy prophylaxis',
             'items' => [
                ['generic' => 'Prednisolone', 'dose_unit' => 'tablet', 'dose_amount' => 1, 'freq' => '1-0-0', 'days' => 1, 'food' => 'after'],
                ['generic' => 'Levocetirizine', 'dose_unit' => 'tablet', 'dose_amount' => 1, 'freq' => '0-0-1', 'days' => 1, 'food' => 'after'],
             ]],
        ],
    ],
    'audiologist' => [
        'label' => 'Audiologist', 'active' => 0,
        'conditions' => [
            ['name' => 'Ear Wax / Supportive (review)', 'desc' => 'Adjunct',
             'items' => [
                ['generic' => 'Paracetamol', 'dose_unit' => 'tablet', 'dose_amount' => 1, 'freq' => 'SOS', 'days' => 3, 'food' => 'after'],
             ]],
        ],
    ],
    'speech' => [
        'label' => 'Speech therapist', 'active' => 0,
        'conditions' => [
            ['name' => 'Supportive (review)', 'desc' => 'Rarely prescribes',
             'items' => [
                ['generic' => 'Vitamin B', 'dose_unit' => 'tablet', 'dose_amount' => 1, 'freq' => '1-0-0', 'days' => 30, 'food' => 'after'],
             ]],
        ],
    ],

    // ---- Dental sub-specialties (review) ----
    'prosthodontist' => [
        'label' => 'Prosthodontist', 'active' => 0,
        'conditions' => [
            ['name' => 'Post-procedure (review)', 'desc' => 'Analgesia',
             'items' => [
                ['generic' => 'Aceclofenac', 'dose_unit' => 'tablet', 'dose_amount' => 1, 'freq' => '1-0-1', 'days' => 3, 'food' => 'after'],
             ]],
        ],
    ],
    'orthodontist' => [
        'label' => 'Orthodontist', 'active' => 0,
        'conditions' => [
            ['name' => 'Orthodontic Pain (review)', 'desc' => 'Analgesia',
             'items' => [
                ['generic' => 'Paracetamol', 'dose_unit' => 'tablet', 'dose_amount' => 1, 'freq' => 'SOS', 'days' => 3, 'food' => 'after'],
             ]],
        ],
    ],
    'pediatric_dentist' => [
        'label' => 'Pediatric dentist', 'active' => 0,
        'conditions' => [
            ['name' => 'Pediatric Dental Pain (review)', 'desc' => 'Weight-based',
             'items' => [
                ['generic' => 'Paracetamol', 'dose_unit' => 'ml', 'dose_amount' => 5, 'freq' => 'SOS', 'days' => 3, 'food' => 'after', 'instructions' => 'Dose by weight'],
             ]],
        ],
    ],
    'endodontist' => [
        'label' => 'Endodontist', 'active' => 0,
        'conditions' => [
            ['name' => 'Endodontic Infection (review)', 'desc' => 'Antibiotic + analgesia',
             'items' => [
                ['generic' => 'Amoxycillin', 'dose_unit' => 'capsule', 'dose_amount' => 1, 'freq' => '1-0-1', 'days' => 5, 'food' => 'after'],
                ['generic' => 'Aceclofenac', 'dose_unit' => 'tablet', 'dose_amount' => 1, 'freq' => '1-0-1', 'days' => 3, 'food' => 'after'],
             ]],
        ],
    ],
    'implantologist' => [
        'label' => 'Dental implant specialist', 'active' => 0,
        'conditions' => [
            ['name' => 'Post-implant (review)', 'desc' => 'Cover + analgesia',
             'items' => [
                ['generic' => 'Amoxycillin', 'dose_unit' => 'capsule', 'dose_amount' => 1, 'freq' => '1-0-1', 'days' => 5, 'food' => 'after'],
                ['generic' => 'Chlorhexidine', 'dose_unit' => 'ml', 'dose_amount' => 10, 'freq' => '1-0-1', 'days' => 7, 'food' => 'after', 'instructions' => 'Mouth rinse'],
             ]],
        ],
    ],

    // ---- Niche (review) ----
    'cosmetology' => [
        'label' => 'Cosmetologist', 'active' => 0,
        'conditions' => [
            ['name' => 'Post-procedure Skin (review)', 'desc' => 'Supportive',
             'items' => [
                ['generic' => 'Levocetirizine', 'dose_unit' => 'tablet', 'dose_amount' => 1, 'freq' => '0-0-1', 'days' => 5, 'food' => 'after'],
             ]],
        ],
    ],
    'fertility' => [
        'label' => 'Fertility specialist', 'active' => 0,
        'conditions' => [
            ['name' => 'Preconception Support (review)', 'desc' => 'Folate',
             'items' => [
                ['generic' => 'Folic Acid', 'dose_unit' => 'tablet', 'dose_amount' => 1, 'freq' => '1-0-0', 'days' => 30, 'food' => 'after'],
             ]],
        ],
    ],

];
