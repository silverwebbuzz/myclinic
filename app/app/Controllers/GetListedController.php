<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\RequestContext;
use App\Http\Request;
use App\Http\Response;
use App\Services\CsrfService;
use App\Services\DoctorClaimService;
use App\Support\Layout;

/**
 * /onboarding/get-listed — in-portal "list my clinic on eClinicPro"
 * application page. Reuses DoctorClaimService and the existing admin
 * review queue. The tenant is already authenticated so no phone OTP.
 */
final class GetListedController
{
    public function show(Request $request): Response
    {
        $clinic = RequestContext::clinic();
        if (!$clinic) return Response::redirect('/login');

        // Already listed? Send them back to the dashboard with a flash hint.
        if (!empty($clinic['is_directory_listed'])) {
            return Response::redirect('/dashboard?message=already_listed');
        }

        $latest = DoctorClaimService::latestForTenantPhone((string) ($clinic['phone'] ?? ''));

        return Response::html(Layout::page('onboarding/get-listed', [
            'clinic'       => $clinic,
            'latest'       => $latest,
            'specialties'  => self::specialtyCatalog(),
            'csrf'         => CsrfService::token(),
            'message'      => $request->query['message'] ?? null,
        ], 'Get listed'));
    }

    /**
     * Full directory specialty catalog as flat [db_value => display label].
     * Used by the dropdown on the get-listed form. Includes everything
     * patients search for — cardiologist, diabetologist, dermatologist, etc.
     *
     * Kept in this controller to avoid pulling the whole marketing-site
     * seo_slugs.php into the portal.
     */
    public static function specialtyCatalog(): array
    {
        return [
            'gp'              => 'General Physician',
            'family_medicine' => 'Family Medicine',
            'peds'            => 'Pediatrician',
            'gyno'            => 'Gynecologist',
            'eye'             => 'Ophthalmologist',
            'derma'           => 'Dermatologist',
            'cosmetology'     => 'Cosmetologist',
            'trichology'      => 'Trichologist',
            'cardio'          => 'Cardiologist',
            'diabetology'     => 'Diabetologist',
            'endocrinology'   => 'Endocrinologist',
            'gastro'          => 'Gastroenterologist',
            'hepatology'      => 'Hepatologist',
            'pulmonology'     => 'Pulmonologist',
            'nephrology'      => 'Nephrologist',
            'allergy'         => 'Allergist',
            'rheumatology'    => 'Rheumatologist',
            'neuro'           => 'Neurologist',
            'psychiatrist'    => 'Psychiatrist',
            'ortho'           => 'Orthopedic',
            'sports_medicine' => 'Sports Medicine',
            'pain_management' => 'Pain Management',
            'oncology'        => 'Oncologist',
            'hematology'      => 'Hematologist',
            'ent'             => 'ENT Specialist',
            'urologist'       => 'Urologist',
            'andrology'       => 'Andrologist',
            'fertility'       => 'Fertility / IVF',
            'sexology'        => 'Sexologist',
            'general_surgery' => 'General Surgeon',
            'neurosurgery'    => 'Neurosurgeon',
            'spine'           => 'Spine Surgeon',
            'gi_surgery'      => 'GI / Laparoscopic Surgeon',
            'plastic_surgery' => 'Plastic Surgeon',
            'bariatric'       => 'Bariatric Surgeon',
            'vascular'        => 'Vascular Surgeon',
            'radiology'       => 'Radiologist',
            'critical_care'   => 'Critical Care',
            'dental'          => 'Dentist',
            'prosthodontist'  => 'Prosthodontist',
            'orthodontist'    => 'Orthodontist',
            'pediatric_dentist'=> 'Pediatric Dentist',
            'endodontist'     => 'Endodontist',
            'implantologist'  => 'Dental Implant Specialist',
            'ayurveda'        => 'Ayurveda',
            'homeopathy'      => 'Homeopathy',
            'siddha'          => 'Siddha',
            'unani'           => 'Unani',
            'naturopathy'     => 'Naturopathy',
            'acupuncturist'   => 'Acupuncturist',
            'physio'          => 'Physiotherapist',
            'psychologist'    => 'Psychologist',
            'audiologist'     => 'Audiologist',
            'speech'          => 'Speech Therapist',
            'dietitian'       => 'Dietitian',
        ];
    }

    public function submit(Request $request): Response
    {
        $clinic = RequestContext::clinic();
        if (!$clinic) return Response::redirect('/login');
        if (!empty($clinic['is_directory_listed'])) {
            return Response::redirect('/dashboard?message=already_listed');
        }

        $tenantId = (int) ($clinic['id'] ?? 0);
        $id = DoctorClaimService::submitFromPortal($tenantId, $clinic, $request->post);
        if ($id === null) {
            return Response::redirect('/onboarding/get-listed?message=failed');
        }
        return Response::redirect('/onboarding/get-listed?message=submitted');
    }
}
