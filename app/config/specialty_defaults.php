<?php

declare(strict_types=1);

/**
 * Specialty Smart Defaults — which UI modules a clinic sees by default,
 * based on its primary specialty.
 *
 * Reads:
 *   VisitView::visibleModules() reads clinic_settings.visible_modules first;
 *   if that's NULL, this config is used as the fallback (lazily populated
 *   on first save).
 *
 * Available module keys:
 *   vitals
 *   labs
 *   photos
 *   diet
 *   consent
 *   case_specialty    (the per-specialty case form partial)
 *
 * symptoms, diagnosis, prescription, notes are ALWAYS visible regardless
 * of this config — they are the 4 fundamentals.
 *
 * Doctors can toggle individual modules anytime in settings. They can also
 * reveal a hidden section for a single visit via the ghost-link pattern;
 * after 3 reveals in a row, the section auto-promotes to visible_modules.
 */

return [
    // -- Pure-consultation specialties (no vitals, no labs by default) --
    'homeopathy'        => ['case_specialty'],
    'ayurveda'          => ['case_specialty'],
    'siddha'            => ['case_specialty'],
    'unani'             => ['case_specialty'],
    'naturopathy'       => ['case_specialty'],
    'acupuncturist'     => ['case_specialty'],
    'physio'            => ['case_specialty'],
    'psychologist'      => ['case_specialty'],
    'psychiatrist'      => ['case_specialty'],
    'speech'            => ['case_specialty'],
    'audiologist'       => ['case_specialty'],
    'eye'               => ['case_specialty'],
    'ent'               => ['case_specialty'],
    'sexology'          => ['case_specialty'],

    // -- Visual / photo-first specialties --
    'derma'             => ['photos', 'case_specialty'],
    'trichology'        => ['photos', 'case_specialty'],

    // -- Procedure specialties (photos + consent) --
    'cosmetology'       => ['photos', 'consent', 'case_specialty'],
    'plastic_surgery'   => ['photos', 'consent', 'case_specialty'],

    // -- Dental family --
    'dental'            => ['photos', 'consent', 'case_specialty'],
    'orthodontist'      => ['photos', 'consent', 'case_specialty'],
    'endodontist'       => ['photos', 'consent', 'case_specialty'],
    'implantologist'    => ['photos', 'consent', 'case_specialty'],
    'prosthodontist'    => ['photos', 'consent', 'case_specialty'],
    'pediatric_dentist' => ['photos', 'consent', 'case_specialty'],

    // -- Vitals + labs heavy (cardio family) --
    'cardio'            => ['vitals', 'labs', 'case_specialty'],
    'endocrinology'     => ['vitals', 'labs', 'case_specialty'],
    'nephrology'        => ['vitals', 'labs', 'case_specialty'],
    'hepatology'        => ['vitals', 'labs', 'case_specialty'],
    'pulmonology'       => ['vitals', 'labs', 'case_specialty'],
    'hematology'        => ['vitals', 'labs', 'case_specialty'],
    'oncology'          => ['vitals', 'labs', 'case_specialty'],

    // -- Diabetes — everything including diet --
    'diabetology'       => ['vitals', 'labs', 'diet', 'case_specialty'],

    // -- Surgery family (vitals + consent) --
    'general_surgery'   => ['vitals', 'consent', 'case_specialty'],
    'neurosurgery'      => ['vitals', 'consent', 'case_specialty'],
    'gi_surgery'        => ['vitals', 'consent', 'case_specialty'],
    'bariatric'         => ['vitals', 'consent', 'case_specialty'],
    'vascular'          => ['vitals', 'consent', 'case_specialty'],
    'spine'             => ['vitals', 'consent', 'case_specialty'],
    'urologist'         => ['vitals', 'consent', 'case_specialty'],
    'fertility'         => ['vitals', 'consent', 'case_specialty'],
    'andrology'         => ['vitals', 'consent', 'case_specialty'],

    // -- Ortho / pain (photos + vitals) --
    'ortho'             => ['vitals', 'photos', 'case_specialty'],
    'sports_medicine'   => ['vitals', 'photos', 'case_specialty'],
    'pain_management'   => ['vitals', 'photos', 'case_specialty'],
    'rheumatology'      => ['vitals', 'photos', 'case_specialty'],

    // -- Diet / nutrition --
    'dietitian'         => ['vitals', 'diet'],

    // -- Critical care / radiology --
    'critical_care'     => ['vitals', 'labs', 'case_specialty'],
    'radiology'         => ['vitals', 'labs', 'case_specialty'],

    // -- Fallback for unmapped specialties (gp, peds, gyno, family_medicine,
    //    gastro, allergy, neuro, etc.) --
    '__default'         => ['vitals', 'case_specialty'],
];
