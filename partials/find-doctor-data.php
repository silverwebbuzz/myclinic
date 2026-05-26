<?php
// =====================================================================
// find-doctor-data.php — seed data for the public Find a Doctor page.
// Returns: countries, specialties (flat for filter logic),
//          specialty_groups (4-group layout for chip rendering),
//          locations, doctors.
// Replace this file later with a DB-backed source if needed.
// =====================================================================

$DATA = [
    'countries' => [
        ['code' => 'IN', 'name' => 'India',          'flag' => '🇮🇳', 'currency' => '₹',   'feeRange' => [200, 2000], 'langs' => ['English', 'Hindi', 'Marathi', 'Tamil', 'Telugu', 'Kannada', 'Bengali']],
        ['code' => 'US', 'name' => 'United States',  'flag' => '🇺🇸', 'currency' => '$',   'feeRange' => [80, 500],   'langs' => ['English', 'Spanish']],
        ['code' => 'GB', 'name' => 'United Kingdom', 'flag' => '🇬🇧', 'currency' => '£',   'feeRange' => [60, 350],   'langs' => ['English']],
        ['code' => 'AE', 'name' => 'UAE',            'flag' => '🇦🇪', 'currency' => 'AED', 'feeRange' => [150, 900],  'langs' => ['English', 'Arabic', 'Hindi']],
        ['code' => 'CA', 'name' => 'Canada',         'flag' => '🇨🇦', 'currency' => 'C$',  'feeRange' => [80, 400],   'langs' => ['English', 'French']],
        ['code' => 'AU', 'name' => 'Australia',      'flag' => '🇦🇺', 'currency' => 'A$',  'feeRange' => [100, 450],  'langs' => ['English']],
        ['code' => 'SG', 'name' => 'Singapore',      'flag' => '🇸🇬', 'currency' => 'S$',  'feeRange' => [80, 380],   'langs' => ['English', 'Mandarin', 'Malay', 'Tamil']],
    ],

    // Grouped specialty layout — keys are kept stable (used as `spec` in DB).
    // The flat 'specialties' array below is derived from this for the Alpine
    // filter logic; the 'specialty_groups' is what the UI renders.
    'specialty_groups' => [
        [
            'label' => 'General Physicians',
            'items' => [
                ['id' => 'gp',              'label' => 'General Physician',     'icon' => '🩺'],
                ['id' => 'family_medicine', 'label' => 'Family Medicine',       'icon' => '👨‍⚕️'],
                ['id' => 'peds',            'label' => 'Pediatrician',          'icon' => '👶'],
                ['id' => 'gyno',            'label' => 'Gynecologist',          'icon' => '👩‍⚕️'],
            ],
        ],
        [
            'label' => 'Skin, Hair & Eye',
            'items' => [
                ['id' => 'derma',          'label' => 'Dermatologist',         'icon' => '✨'],
                ['id' => 'cosmetology',    'label' => 'Cosmetologist',         'icon' => '💎'],
                ['id' => 'trichology',     'label' => 'Trichologist (Hair)',   'icon' => '💇'],
                ['id' => 'eye',            'label' => 'Ophthalmologist',       'icon' => '👁️'],
            ],
        ],
        [
            'label' => 'Heart, Diabetes & Internal Medicine',
            'items' => [
                ['id' => 'cardio',         'label' => 'Cardiologist',          'icon' => '❤️'],
                ['id' => 'diabetology',    'label' => 'Diabetologist',         'icon' => '💉'],
                ['id' => 'endocrinology',  'label' => 'Endocrinologist',       'icon' => '🧪'],
                ['id' => 'gastro',         'label' => 'Gastroenterologist',    'icon' => '🩻'],
                ['id' => 'hepatology',     'label' => 'Hepatologist (Liver)',  'icon' => '🩸'],
                ['id' => 'pulmonology',    'label' => 'Pulmonologist',         'icon' => '🫁'],
                ['id' => 'nephrology',     'label' => 'Nephrologist',          'icon' => '🩸'],
                ['id' => 'allergy',        'label' => 'Allergist',             'icon' => '🤧'],
                ['id' => 'rheumatology',   'label' => 'Rheumatologist',        'icon' => '🦴'],
            ],
        ],
        [
            'label' => 'Brain, Mind & Nerves',
            'items' => [
                ['id' => 'neuro',          'label' => 'Neurologist',           'icon' => '🧬'],
                ['id' => 'psychiatrist',   'label' => 'Psychiatrist',          'icon' => '🧠'],
            ],
        ],
        [
            'label' => 'Bones, Joints & Sports',
            'items' => [
                ['id' => 'ortho',          'label' => 'Orthopedic',            'icon' => '🦴'],
                ['id' => 'sports_medicine','label' => 'Sports Medicine',       'icon' => '🏃'],
                ['id' => 'pain_management','label' => 'Pain Management',       'icon' => '💊'],
            ],
        ],
        [
            'label' => 'Cancer & Blood',
            'items' => [
                ['id' => 'oncology',       'label' => 'Oncologist',            'icon' => '🎗️'],
                ['id' => 'hematology',     'label' => 'Hematologist',          'icon' => '🩸'],
            ],
        ],
        [
            'label' => 'ENT & Reproductive Health',
            'items' => [
                ['id' => 'ent',            'label' => 'ENT',                   'icon' => '👂'],
                ['id' => 'urologist',      'label' => 'Urologist',             'icon' => '🩺'],
                ['id' => 'andrology',      'label' => 'Andrologist',           'icon' => '👨'],
                ['id' => 'fertility',      'label' => 'Fertility / IVF',       'icon' => '🤰'],
                // 'sexology' is omitted here intentionally (safe=false) but
                // direct URL access still works for SEO.
            ],
        ],
        [
            'label' => 'Surgeons & Critical Care',
            'items' => [
                ['id' => 'general_surgery','label' => 'General Surgeon',       'icon' => '🔪'],
                ['id' => 'neurosurgery',   'label' => 'Neurosurgeon',          'icon' => '🧠'],
                ['id' => 'spine',          'label' => 'Spine Surgeon',         'icon' => '🦴'],
                ['id' => 'gi_surgery',     'label' => 'GI / Laparoscopic',     'icon' => '🔬'],
                ['id' => 'plastic_surgery','label' => 'Plastic Surgeon',       'icon' => '✨'],
                ['id' => 'bariatric',      'label' => 'Bariatric Surgeon',     'icon' => '⚖️'],
                ['id' => 'vascular',       'label' => 'Vascular Surgeon',      'icon' => '🩸'],
                ['id' => 'radiology',      'label' => 'Radiologist',           'icon' => '📷'],
                ['id' => 'critical_care',  'label' => 'Critical Care',         'icon' => '🚨'],
            ],
        ],
        [
            'label' => 'Dentists',
            'items' => [
                ['id' => 'dental',             'label' => 'Dentist',          'icon' => '🦷'],
                ['id' => 'prosthodontist',     'label' => 'Prosthodontist',   'icon' => '🦷'],
                ['id' => 'orthodontist',       'label' => 'Orthodontist',     'icon' => '🦷'],
                ['id' => 'pediatric_dentist',  'label' => 'Pediatric',        'icon' => '🦷'],
                ['id' => 'endodontist',        'label' => 'Endodontist',      'icon' => '🦷'],
                ['id' => 'implantologist',     'label' => 'Implantologist',   'icon' => '🦷'],
            ],
        ],
        [
            'label' => 'Alternative Medicine Practitioners',
            'items' => [
                ['id' => 'ayurveda',    'label' => 'Ayurveda',           'icon' => '🌿'],
                ['id' => 'homeo',       'label' => 'Homoeopath',         'icon' => '🌿'],
                ['id' => 'siddha',      'label' => 'Siddha',             'icon' => '🌿'],
                ['id' => 'unani',       'label' => 'Unani',              'icon' => '🌿'],
                ['id' => 'naturopathy', 'label' => 'Yoga & Naturopathy', 'icon' => '🧘'],
            ],
        ],
        [
            'label' => 'Therapists & Nutritionists',
            'items' => [
                ['id' => 'acupuncturist', 'label' => 'Acupuncturist',         'icon' => '📍'],
                ['id' => 'physio',        'label' => 'Physiotherapist',       'icon' => '🤸'],
                ['id' => 'psychologist',  'label' => 'Psychologist',          'icon' => '🧠'],
                ['id' => 'audiologist',   'label' => 'Audiologist',           'icon' => '👂'],
                ['id' => 'speech',        'label' => 'Speech',                'icon' => '🗣️'],
                ['id' => 'dietitian',     'label' => 'Dietitian/Nutritionist','icon' => '🥗'],
            ],
        ],
    ],

    // Flat list — auto-built below from specialty_groups for filter logic
    // (don't edit; derived).
    'specialties' => [],

    'locations' => [
        // India
        ['label' => 'Bandra, Mumbai', 'sub' => 'Maharashtra, India',   'type' => 'area', 'flag' => '📍', 'country' => 'IN', 'value' => ['country' => 'IN', 'state' => 'Maharashtra', 'city' => 'Mumbai', 'area' => 'Bandra']],
        ['label' => 'Andheri, Mumbai', 'sub' => 'Maharashtra, India',  'type' => 'area', 'flag' => '📍', 'country' => 'IN', 'value' => ['country' => 'IN', 'state' => 'Maharashtra', 'city' => 'Mumbai', 'area' => 'Andheri']],
        ['label' => 'Mumbai', 'sub' => 'Maharashtra, India',           'type' => 'city', 'flag' => '🏙️', 'country' => 'IN', 'value' => ['country' => 'IN', 'state' => 'Maharashtra', 'city' => 'Mumbai']],
        ['label' => 'Pune', 'sub' => 'Maharashtra, India',             'type' => 'city', 'flag' => '🏙️', 'country' => 'IN', 'value' => ['country' => 'IN', 'state' => 'Maharashtra', 'city' => 'Pune']],
        ['label' => 'Bangalore', 'sub' => 'Karnataka, India',          'type' => 'city', 'flag' => '🏙️', 'country' => 'IN', 'value' => ['country' => 'IN', 'state' => 'Karnataka',   'city' => 'Bangalore']],
        ['label' => 'Delhi', 'sub' => 'Delhi, India',                  'type' => 'city', 'flag' => '🏙️', 'country' => 'IN', 'value' => ['country' => 'IN', 'state' => 'Delhi',       'city' => 'Delhi']],
        ['label' => 'Chennai', 'sub' => 'Tamil Nadu, India',           'type' => 'city', 'flag' => '🏙️', 'country' => 'IN', 'value' => ['country' => 'IN', 'state' => 'Tamil Nadu',  'city' => 'Chennai']],
        ['label' => 'Maharashtra', 'sub' => 'India',                   'type' => 'state','flag' => '🗺️', 'country' => 'IN', 'value' => ['country' => 'IN', 'state' => 'Maharashtra']],
        // US
        ['label' => 'New York', 'sub' => 'NY, United States',          'type' => 'city', 'flag' => '🏙️', 'country' => 'US', 'value' => ['country' => 'US', 'state' => 'NY', 'city' => 'New York']],
        ['label' => 'San Francisco', 'sub' => 'CA, United States',     'type' => 'city', 'flag' => '🏙️', 'country' => 'US', 'value' => ['country' => 'US', 'state' => 'CA', 'city' => 'San Francisco']],
        ['label' => 'Chicago', 'sub' => 'IL, United States',           'type' => 'city', 'flag' => '🏙️', 'country' => 'US', 'value' => ['country' => 'US', 'state' => 'IL', 'city' => 'Chicago']],
        // UK
        ['label' => 'London', 'sub' => 'United Kingdom',               'type' => 'city', 'flag' => '🏙️', 'country' => 'GB', 'value' => ['country' => 'GB', 'state' => 'England', 'city' => 'London']],
        ['label' => 'Bristol', 'sub' => 'United Kingdom',              'type' => 'city', 'flag' => '🏙️', 'country' => 'GB', 'value' => ['country' => 'GB', 'state' => 'England', 'city' => 'Bristol']],
        // UAE
        ['label' => 'Dubai', 'sub' => 'UAE',                           'type' => 'city', 'flag' => '🏙️', 'country' => 'AE', 'value' => ['country' => 'AE', 'state' => 'Dubai',     'city' => 'Dubai']],
        // Canada
        ['label' => 'Toronto', 'sub' => 'ON, Canada',                  'type' => 'city', 'flag' => '🏙️', 'country' => 'CA', 'value' => ['country' => 'CA', 'state' => 'ON', 'city' => 'Toronto']],
        // Australia
        ['label' => 'Sydney', 'sub' => 'NSW, Australia',               'type' => 'city', 'flag' => '🏙️', 'country' => 'AU', 'value' => ['country' => 'AU', 'state' => 'NSW', 'city' => 'Sydney']],
        // Singapore
        ['label' => 'Singapore', 'sub' => 'Singapore',                 'type' => 'country', 'flag' => '🇸🇬', 'country' => 'SG', 'value' => ['country' => 'SG']],
    ],

    'doctors' => [
        // ----- India -----
        ['id' => 1,  'name' => 'Dr. Aarav Sharma',  'firstInitial' => 'A', 'lastInitial' => 'S', 'qual' => 'MBBS, MD (Medicine)', 'years' => 14, 'spec' => 'gp', 'specLabel' => 'General practice', 'verified' => true,  'video' => true,  'gender' => 'M', 'rating' => 4.9, 'reviews' => 287, 'langs' => ['English', 'Hindi', 'Marathi'], 'hospital' => 'Sunrise Family Clinic', 'area' => 'Bandra', 'city' => 'Mumbai', 'state' => 'Maharashtra', 'country' => 'IN', 'countryName' => 'India', 'currency' => '₹', 'fee' => 600, 'next' => ['when' => 'today',    'label' => 'Today 6:30 PM',   'sub' => '4 slots left']],
        ['id' => 2,  'name' => 'Dr. Priya Iyer',    'firstInitial' => 'P', 'lastInitial' => 'I', 'qual' => 'BHMS, MD (Hom)',     'years' => 11, 'spec' => 'homeo', 'specLabel' => 'Homeopathy', 'verified' => true,  'video' => true,  'gender' => 'F', 'rating' => 4.9, 'reviews' => 142, 'langs' => ['English', 'Hindi', 'Marathi'], 'hospital' => 'Iyer Homeopathy', 'area' => 'Aundh', 'city' => 'Pune', 'state' => 'Maharashtra', 'country' => 'IN', 'countryName' => 'India', 'currency' => '₹', 'fee' => 500, 'next' => ['when' => 'tomorrow', 'label' => 'Tomorrow 10:00 AM', 'sub' => '']],
        ['id' => 3,  'name' => 'Dr. Ananya Rao',    'firstInitial' => 'A', 'lastInitial' => 'R', 'qual' => 'BDS, MDS',           'years' => 9,  'spec' => 'dental', 'specLabel' => 'Dental',    'verified' => true,  'video' => false, 'gender' => 'F', 'rating' => 4.8, 'reviews' => 96,  'langs' => ['English', 'Kannada', 'Hindi'], 'hospital' => 'BrightSmiles Dental', 'area' => 'Indiranagar', 'city' => 'Bangalore', 'state' => 'Karnataka', 'country' => 'IN', 'countryName' => 'India', 'currency' => '₹', 'fee' => 800, 'next' => ['when' => 'today',   'label' => 'Today 4:00 PM',   'sub' => '2 slots left']],
        ['id' => 4,  'name' => 'Dr. Rohit Kapoor',  'firstInitial' => 'R', 'lastInitial' => 'K', 'qual' => 'BHMS',               'years' => 18, 'spec' => 'homeo', 'specLabel' => 'Homeopathy', 'verified' => true,  'video' => true,  'gender' => 'M', 'rating' => 4.7, 'reviews' => 211, 'langs' => ['English', 'Hindi', 'Punjabi'], 'hospital' => 'Kapoor Clinic', 'area' => 'Connaught Place', 'city' => 'Delhi', 'state' => 'Delhi', 'country' => 'IN', 'countryName' => 'India', 'currency' => '₹', 'fee' => 700, 'next' => ['when' => 'later',  'label' => 'In 3 days',       'sub' => '']],
        ['id' => 5,  'name' => 'Dr. Karthik Menon', 'firstInitial' => 'K', 'lastInitial' => 'M', 'qual' => 'MBBS, MD (Pediatrics)', 'years' => 12, 'spec' => 'peds', 'specLabel' => 'Pediatrics', 'verified' => true, 'video' => true, 'gender' => 'M', 'rating' => 4.9, 'reviews' => 174, 'langs' => ['English', 'Malayalam', 'Tamil'], 'hospital' => 'TinyCare Pediatrics', 'area' => 'T. Nagar', 'city' => 'Chennai', 'state' => 'Tamil Nadu', 'country' => 'IN', 'countryName' => 'India', 'currency' => '₹', 'fee' => 550, 'next' => ['when' => 'today',   'label' => 'Today 7:15 PM',   'sub' => '']],
        ['id' => 6,  'name' => 'Dr. Megha Desai',   'firstInitial' => 'M', 'lastInitial' => 'D', 'qual' => 'MBBS, MD (Derm)',    'years' => 10, 'spec' => 'derma', 'specLabel' => 'Dermatology', 'verified' => true,  'video' => true,  'gender' => 'F', 'rating' => 4.8, 'reviews' => 88,  'langs' => ['English', 'Hindi', 'Gujarati'], 'hospital' => 'GlowSkin Clinic', 'area' => 'Juhu', 'city' => 'Mumbai', 'state' => 'Maharashtra', 'country' => 'IN', 'countryName' => 'India', 'currency' => '₹', 'fee' => 900, 'next' => ['when' => 'tomorrow','label' => 'Tomorrow 11:30 AM','sub' => '']],
        ['id' => 7,  'name' => 'Dr. Maya Patel',    'firstInitial' => 'M', 'lastInitial' => 'P', 'qual' => 'BPT, MPT',           'years' => 8,  'spec' => 'physio', 'specLabel' => 'Physiotherapy', 'verified' => true, 'video' => true, 'gender' => 'F', 'rating' => 4.8, 'reviews' => 67,  'langs' => ['English', 'Gujarati', 'Hindi'], 'hospital' => 'MoveWell Physio', 'area' => 'Andheri', 'city' => 'Mumbai', 'state' => 'Maharashtra', 'country' => 'IN', 'countryName' => 'India', 'currency' => '₹', 'fee' => 450, 'next' => ['when' => 'today',  'label' => 'Today 5:00 PM',   'sub' => '']],
        ['id' => 8,  'name' => 'Dr. Suresh Patil',  'firstInitial' => 'S', 'lastInitial' => 'P', 'qual' => 'MBBS, MD (Cardio)',  'years' => 22, 'spec' => 'cardio', 'specLabel' => 'Cardiology', 'verified' => true,  'video' => false, 'gender' => 'M', 'rating' => 4.9, 'reviews' => 412, 'langs' => ['English', 'Hindi', 'Marathi'], 'hospital' => 'Heart Care Centre', 'area' => 'Kothrud', 'city' => 'Pune', 'state' => 'Maharashtra', 'country' => 'IN', 'countryName' => 'India', 'currency' => '₹', 'fee' => 1500, 'next' => ['when' => 'later', 'label' => 'In 5 days', 'sub' => '']],
        ['id' => 9,  'name' => 'Dr. Neha Joshi',    'firstInitial' => 'N', 'lastInitial' => 'J', 'qual' => 'MBBS, MS (Gyne)',    'years' => 15, 'spec' => 'gyno', 'specLabel' => 'Gynaecology', 'verified' => true, 'video' => true, 'gender' => 'F', 'rating' => 4.8, 'reviews' => 198, 'langs' => ['English', 'Hindi'], 'hospital' => 'Sarla Women\'s Clinic', 'area' => 'Bandra', 'city' => 'Mumbai', 'state' => 'Maharashtra', 'country' => 'IN', 'countryName' => 'India', 'currency' => '₹', 'fee' => 1200, 'next' => ['when' => 'tomorrow', 'label' => 'Tomorrow 3:30 PM', 'sub' => '']],
        ['id' => 10, 'name' => 'Dr. Ben Carter',    'firstInitial' => 'B', 'lastInitial' => 'C', 'qual' => 'MBBS, MD',           'years' => 13, 'spec' => 'gp', 'specLabel' => 'General practice', 'verified' => true, 'video' => true, 'gender' => 'M', 'rating' => 4.8, 'reviews' => 156, 'langs' => ['English', 'Hindi'], 'hospital' => 'Hill Family Practice', 'area' => 'Andheri', 'city' => 'Mumbai', 'state' => 'Maharashtra', 'country' => 'IN', 'countryName' => 'India', 'currency' => '₹', 'fee' => 700, 'next' => ['when' => 'today', 'label' => 'Today 8:00 PM', 'sub' => '']],
        ['id' => 11, 'name' => 'Dr. Anjali Verma',  'firstInitial' => 'A', 'lastInitial' => 'V', 'qual' => 'BDS',                'years' => 6,  'spec' => 'dental', 'specLabel' => 'Dental', 'verified' => false, 'video' => false, 'gender' => 'F', 'rating' => 4.6, 'reviews' => 41, 'langs' => ['English', 'Hindi'], 'hospital' => 'CityDental', 'area' => 'Karol Bagh', 'city' => 'Delhi', 'state' => 'Delhi', 'country' => 'IN', 'countryName' => 'India', 'currency' => '₹', 'fee' => 400, 'next' => ['when' => 'today', 'label' => 'Today 2:00 PM', 'sub' => '']],
        ['id' => 12, 'name' => 'Dr. Vikram Singh',  'firstInitial' => 'V', 'lastInitial' => 'S', 'qual' => 'MBBS, DCH',          'years' => 19, 'spec' => 'peds', 'specLabel' => 'Pediatrics', 'verified' => true,  'video' => true,  'gender' => 'M', 'rating' => 4.7, 'reviews' => 220, 'langs' => ['English', 'Hindi', 'Punjabi'], 'hospital' => 'Singh Pediatric Care', 'area' => 'Dwarka', 'city' => 'Delhi', 'state' => 'Delhi', 'country' => 'IN', 'countryName' => 'India', 'currency' => '₹', 'fee' => 700, 'next' => ['when' => 'tomorrow', 'label' => 'Tomorrow 9:00 AM', 'sub' => '']],

        // ----- US -----
        ['id' => 13, 'name' => 'Dr. Sofia Rodriguez', 'firstInitial' => 'S', 'lastInitial' => 'R', 'qual' => 'MD',                'years' => 16, 'spec' => 'gp', 'specLabel' => 'General practice', 'verified' => true, 'video' => true, 'gender' => 'F', 'rating' => 4.9, 'reviews' => 312, 'langs' => ['English', 'Spanish'], 'hospital' => 'Manhattan Family Health', 'area' => 'Upper East Side', 'city' => 'New York', 'state' => 'NY', 'country' => 'US', 'countryName' => 'United States', 'currency' => '$', 'fee' => 250, 'next' => ['when' => 'tomorrow', 'label' => 'Tomorrow 11:00 AM', 'sub' => '']],
        ['id' => 14, 'name' => 'Dr. Daniel Cohen',  'firstInitial' => 'D', 'lastInitial' => 'C', 'qual' => 'DMD',                'years' => 11, 'spec' => 'dental', 'specLabel' => 'Dental', 'verified' => true,  'video' => false, 'gender' => 'M', 'rating' => 4.8, 'reviews' => 189, 'langs' => ['English'], 'hospital' => 'Cohen Dental Group', 'area' => 'Midtown', 'city' => 'New York', 'state' => 'NY', 'country' => 'US', 'countryName' => 'United States', 'currency' => '$', 'fee' => 180, 'next' => ['when' => 'today', 'label' => 'Today 5:30 PM', 'sub' => '1 slot left']],
        ['id' => 15, 'name' => 'Dr. James Whitfield','firstInitial' => 'J', 'lastInitial' => 'W', 'qual' => 'DDS',                'years' => 21, 'spec' => 'dental', 'specLabel' => 'Dental', 'verified' => true,  'video' => false, 'gender' => 'M', 'rating' => 4.9, 'reviews' => 401, 'langs' => ['English'], 'hospital' => 'Whitfield Dental', 'area' => 'East End', 'city' => 'Toronto', 'state' => 'ON', 'country' => 'CA', 'countryName' => 'Canada', 'currency' => 'C$', 'fee' => 220, 'next' => ['when' => 'tomorrow', 'label' => 'Tomorrow 1:00 PM', 'sub' => '']],
        ['id' => 16, 'name' => 'Dr. Hannah Lin',    'firstInitial' => 'H', 'lastInitial' => 'L', 'qual' => 'MD',                'years' => 10, 'spec' => 'derma', 'specLabel' => 'Dermatology', 'verified' => true, 'video' => true, 'gender' => 'F', 'rating' => 4.9, 'reviews' => 245, 'langs' => ['English'], 'hospital' => 'Lin Dermatology', 'area' => 'Bondi', 'city' => 'Sydney', 'state' => 'NSW', 'country' => 'AU', 'countryName' => 'Australia', 'currency' => 'A$', 'fee' => 280, 'next' => ['when' => 'tomorrow', 'label' => 'Tomorrow 2:30 PM', 'sub' => '']],
        ['id' => 17, 'name' => 'Dr. Olivia Park',   'firstInitial' => 'O', 'lastInitial' => 'P', 'qual' => 'MD, FACC',           'years' => 18, 'spec' => 'cardio', 'specLabel' => 'Cardiology', 'verified' => true, 'video' => true, 'gender' => 'F', 'rating' => 4.9, 'reviews' => 198, 'langs' => ['English'], 'hospital' => 'Bay Cardiology', 'area' => 'SOMA', 'city' => 'San Francisco', 'state' => 'CA', 'country' => 'US', 'countryName' => 'United States', 'currency' => '$', 'fee' => 420, 'next' => ['when' => 'later', 'label' => 'In 4 days', 'sub' => '']],
        ['id' => 18, 'name' => 'Dr. Marcus Lee',    'firstInitial' => 'M', 'lastInitial' => 'L', 'qual' => 'MD',                'years' => 9,  'spec' => 'peds', 'specLabel' => 'Pediatrics', 'verified' => true, 'video' => true, 'gender' => 'M', 'rating' => 4.8, 'reviews' => 132, 'langs' => ['English', 'Mandarin'], 'hospital' => 'Bayside Kids', 'area' => 'Marina', 'city' => 'San Francisco', 'state' => 'CA', 'country' => 'US', 'countryName' => 'United States', 'currency' => '$', 'fee' => 200, 'next' => ['when' => 'today', 'label' => 'Today 6:00 PM', 'sub' => '']],
        ['id' => 19, 'name' => 'Dr. Emily Brooks',  'firstInitial' => 'E', 'lastInitial' => 'B', 'qual' => 'MD',                'years' => 14, 'spec' => 'gyno', 'specLabel' => 'Gynaecology', 'verified' => true, 'video' => true, 'gender' => 'F', 'rating' => 4.8, 'reviews' => 167, 'langs' => ['English'], 'hospital' => 'Brooks Women\'s Health', 'area' => 'Lincoln Park', 'city' => 'Chicago', 'state' => 'IL', 'country' => 'US', 'countryName' => 'United States', 'currency' => '$', 'fee' => 300, 'next' => ['when' => 'tomorrow', 'label' => 'Tomorrow 10:30 AM', 'sub' => '']],

        // ----- UK -----
        ['id' => 20, 'name' => 'Dr. Olivia Bennett','firstInitial' => 'O', 'lastInitial' => 'B', 'qual' => 'MBBS, MRCGP',        'years' => 12, 'spec' => 'gp', 'specLabel' => 'General practice', 'verified' => true, 'video' => true, 'gender' => 'F', 'rating' => 4.8, 'reviews' => 145, 'langs' => ['English'], 'hospital' => 'Chelsea Family Surgery', 'area' => 'Chelsea', 'city' => 'London', 'state' => 'England', 'country' => 'GB', 'countryName' => 'United Kingdom', 'currency' => '£', 'fee' => 120, 'next' => ['when' => 'tomorrow', 'label' => 'Tomorrow 4:00 PM', 'sub' => '']],
        ['id' => 21, 'name' => 'Dr. Adam Whitaker', 'firstInitial' => 'A', 'lastInitial' => 'W', 'qual' => 'BDS, MFDS RCS',      'years' => 14, 'spec' => 'dental', 'specLabel' => 'Dental', 'verified' => true, 'video' => false, 'gender' => 'M', 'rating' => 4.7, 'reviews' => 89, 'langs' => ['English'], 'hospital' => 'Bristol Dental Co.', 'area' => 'Clifton', 'city' => 'Bristol', 'state' => 'England', 'country' => 'GB', 'countryName' => 'United Kingdom', 'currency' => '£', 'fee' => 95, 'next' => ['when' => 'today', 'label' => 'Today 4:45 PM', 'sub' => '']],
        ['id' => 22, 'name' => 'Dr. Maya Patel',    'firstInitial' => 'M', 'lastInitial' => 'P', 'qual' => 'BSc, MSc Physio',    'years' => 9,  'spec' => 'physio', 'specLabel' => 'Physiotherapy', 'verified' => true, 'video' => true, 'gender' => 'F', 'rating' => 4.9, 'reviews' => 134, 'langs' => ['English', 'Hindi'], 'hospital' => 'Riverside Physio', 'area' => 'Redland', 'city' => 'Bristol', 'state' => 'England', 'country' => 'GB', 'countryName' => 'United Kingdom', 'currency' => '£', 'fee' => 75, 'next' => ['when' => 'today', 'label' => 'Today 6:15 PM', 'sub' => '']],
        ['id' => 23, 'name' => 'Dr. Sarah Hughes',  'firstInitial' => 'S', 'lastInitial' => 'H', 'qual' => 'MBBS, FRCDerm',      'years' => 17, 'spec' => 'derma', 'specLabel' => 'Dermatology', 'verified' => true, 'video' => true, 'gender' => 'F', 'rating' => 4.9, 'reviews' => 198, 'langs' => ['English'], 'hospital' => 'Northside Derma', 'area' => 'Marylebone', 'city' => 'London', 'state' => 'England', 'country' => 'GB', 'countryName' => 'United Kingdom', 'currency' => '£', 'fee' => 180, 'next' => ['when' => 'later', 'label' => 'In 6 days', 'sub' => '']],

        // ----- UAE -----
        ['id' => 24, 'name' => 'Dr. Yusuf El-Sayed','firstInitial' => 'Y', 'lastInitial' => 'E', 'qual' => 'BDS, MSc',           'years' => 13, 'spec' => 'dental', 'specLabel' => 'Dental', 'verified' => true, 'video' => false, 'gender' => 'M', 'rating' => 4.9, 'reviews' => 256, 'langs' => ['English', 'Arabic'], 'hospital' => 'Bright Smiles Dubai', 'area' => 'Jumeirah', 'city' => 'Dubai', 'state' => 'Dubai', 'country' => 'AE', 'countryName' => 'UAE', 'currency' => 'AED', 'fee' => 380, 'next' => ['when' => 'tomorrow', 'label' => 'Tomorrow 12:00 PM', 'sub' => '']],
        ['id' => 25, 'name' => 'Dr. Layla Al-Mansoori','firstInitial' => 'L','lastInitial' => 'A', 'qual' => 'MBBS, MD',         'years' => 11, 'spec' => 'gyno', 'specLabel' => 'Gynaecology', 'verified' => true, 'video' => true, 'gender' => 'F', 'rating' => 4.8, 'reviews' => 145, 'langs' => ['English', 'Arabic'], 'hospital' => 'Dubai Women\'s Centre', 'area' => 'Bur Dubai', 'city' => 'Dubai', 'state' => 'Dubai', 'country' => 'AE', 'countryName' => 'UAE', 'currency' => 'AED', 'fee' => 500, 'next' => ['when' => 'today', 'label' => 'Today 7:30 PM', 'sub' => '']],

        // ----- Australia -----
        ['id' => 26, 'name' => 'Dr. Hyun-woo Park', 'firstInitial' => 'H', 'lastInitial' => 'P', 'qual' => 'BSc, MSc Sports Physio', 'years' => 10, 'spec' => 'physio', 'specLabel' => 'Physiotherapy', 'verified' => true, 'video' => true, 'gender' => 'M', 'rating' => 4.9, 'reviews' => 178, 'langs' => ['English'], 'hospital' => 'Sydney Sports Physio', 'area' => 'Manly', 'city' => 'Sydney', 'state' => 'NSW', 'country' => 'AU', 'countryName' => 'Australia', 'currency' => 'A$', 'fee' => 120, 'next' => ['when' => 'tomorrow', 'label' => 'Tomorrow 8:30 AM', 'sub' => '']],

        // ----- Canada -----
        ['id' => 27, 'name' => 'Dr. Emily Chen',    'firstInitial' => 'E', 'lastInitial' => 'C', 'qual' => 'MD',                'years' => 8,  'spec' => 'peds', 'specLabel' => 'Pediatrics', 'verified' => true, 'video' => true, 'gender' => 'F', 'rating' => 4.8, 'reviews' => 112, 'langs' => ['English', 'Mandarin'], 'hospital' => 'Toronto Kids Clinic', 'area' => 'Yorkville', 'city' => 'Toronto', 'state' => 'ON', 'country' => 'CA', 'countryName' => 'Canada', 'currency' => 'C$', 'fee' => 160, 'next' => ['when' => 'today', 'label' => 'Today 3:00 PM', 'sub' => '']],

        // ----- Singapore -----
        ['id' => 28, 'name' => 'Dr. Liang Wei',     'firstInitial' => 'L', 'lastInitial' => 'W', 'qual' => 'MBBS, MMed (Family)','years' => 15, 'spec' => 'gp', 'specLabel' => 'General practice', 'verified' => true, 'video' => true, 'gender' => 'M', 'rating' => 4.8, 'reviews' => 220, 'langs' => ['English', 'Mandarin'], 'hospital' => 'Orchard Family Clinic', 'area' => 'Orchard', 'city' => 'Singapore', 'state' => 'Singapore', 'country' => 'SG', 'countryName' => 'Singapore', 'currency' => 'S$', 'fee' => 90, 'next' => ['when' => 'today', 'label' => 'Today 6:45 PM', 'sub' => '']],
        ['id' => 29, 'name' => 'Dr. Mei-Lin Chen',  'firstInitial' => 'M', 'lastInitial' => 'C', 'qual' => 'MBBS, FAMS',         'years' => 13, 'spec' => 'peds', 'specLabel' => 'Pediatrics', 'verified' => true, 'video' => true, 'gender' => 'F', 'rating' => 4.9, 'reviews' => 198, 'langs' => ['English', 'Mandarin', 'Malay'], 'hospital' => 'Tampines Kids Care', 'area' => 'Tampines', 'city' => 'Singapore', 'state' => 'Singapore', 'country' => 'SG', 'countryName' => 'Singapore', 'currency' => 'S$', 'fee' => 110, 'next' => ['when' => 'tomorrow', 'label' => 'Tomorrow 11:15 AM', 'sub' => '']],
        ['id' => 30, 'name' => 'Dr. Rahul Krishnan','firstInitial' => 'R', 'lastInitial' => 'K', 'qual' => 'MBBS, MD (Cardio)',  'years' => 20, 'spec' => 'cardio', 'specLabel' => 'Cardiology', 'verified' => true, 'video' => false, 'gender' => 'M', 'rating' => 4.9, 'reviews' => 340, 'langs' => ['English', 'Tamil', 'Hindi'], 'hospital' => 'Heart Health Singapore', 'area' => 'Novena', 'city' => 'Singapore', 'state' => 'Singapore', 'country' => 'SG', 'countryName' => 'Singapore', 'currency' => 'S$', 'fee' => 220, 'next' => ['when' => 'later', 'label' => 'In 3 days', 'sub' => '']],
    ],
];

// Derive the flat 'specialties' list from the grouped layout. Keep this last
// so any group edits above auto-propagate without touching the filter logic.
foreach ($DATA['specialty_groups'] as $g) {
    foreach ($g['items'] as $item) {
        $DATA['specialties'][] = $item;
    }
}

return $DATA;
