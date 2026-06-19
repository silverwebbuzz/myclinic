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
 *   diet
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
    // -- Pure-consultation specialties (no vitals by default) --
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

    // -- Visual / procedure specialties --
    'derma'             => ['case_specialty'],
    'trichology'        => ['case_specialty'],
    'cosmetology'       => ['case_specialty'],
    'plastic_surgery'   => ['case_specialty'],

    // -- Dental family --
    'dental'            => ['case_specialty'],
    'orthodontist'      => ['case_specialty'],
    'endodontist'       => ['case_specialty'],
    'implantologist'    => ['case_specialty'],
    'prosthodontist'    => ['case_specialty'],
    'pediatric_dentist' => ['case_specialty'],

    // -- Vitals-heavy (cardio family) --
    'cardio'            => ['vitals', 'case_specialty'],
    'endocrinology'     => ['vitals', 'case_specialty'],
    'nephrology'        => ['vitals', 'case_specialty'],
    'hepatology'        => ['vitals', 'case_specialty'],
    'pulmonology'       => ['vitals', 'case_specialty'],
    'hematology'        => ['vitals', 'case_specialty'],
    'oncology'          => ['vitals', 'case_specialty'],

    // -- Diabetes — vitals + diet --
    'diabetology'       => ['vitals', 'diet', 'case_specialty'],

    // -- Surgery family --
    'general_surgery'   => ['vitals', 'case_specialty'],
    'neurosurgery'      => ['vitals', 'case_specialty'],
    'gi_surgery'        => ['vitals', 'case_specialty'],
    'bariatric'         => ['vitals', 'case_specialty'],
    'vascular'          => ['vitals', 'case_specialty'],
    'spine'             => ['vitals', 'case_specialty'],
    'urologist'         => ['vitals', 'case_specialty'],
    'fertility'         => ['vitals', 'case_specialty'],
    'andrology'         => ['vitals', 'case_specialty'],

    // -- Ortho / pain --
    'ortho'             => ['vitals', 'case_specialty'],
    'sports_medicine'   => ['vitals', 'case_specialty'],
    'pain_management'   => ['vitals', 'case_specialty'],
    'rheumatology'      => ['vitals', 'case_specialty'],

    // -- Diet / nutrition --
    'dietitian'         => ['vitals', 'diet'],

    // -- Critical care --
    'critical_care'     => ['vitals', 'case_specialty'],
    'radiology'         => ['vitals', 'case_specialty'],

    // -- Fallback for unmapped specialties (gp, peds, gyno, family_medicine,
    //    gastro, allergy, neuro, etc.) --
    '__default'         => ['vitals', 'case_specialty'],
];
