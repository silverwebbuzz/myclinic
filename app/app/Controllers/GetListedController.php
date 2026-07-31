<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\RequestContext;
use App\Http\Request;
use App\Http\Response;
use App\Services\ClinicSettingsService;
use App\Services\CsrfService;
use App\Services\DoctorClaimService;
use App\Services\LocationCatalogService;
use App\Support\Layout;

/**
 * /listing — the single "Listed on eClinicPro" page. One destination for the
 * whole public-directory lifecycle:
 *   - not listed      → application form (apply)
 *   - pending         → "under review" status
 *   - rejected        → reason + re-apply
 *   - approved        → edit the live public profile (directory_doctors)
 *
 * Reuses DoctorClaimService (admin review queue) and ClinicSettingsService
 * (public-profile fields). The tenant is already authenticated — no phone OTP.
 * (/onboarding/get-listed redirects here for backwards compatibility.)
 */
final class GetListedController
{
    public function show(Request $request): Response
    {
        $clinic = RequestContext::clinic();
        if (!$clinic) return Response::redirect('/login');

        $clinicId = (int) ($clinic['id'] ?? 0);

        return Response::html(Layout::page('onboarding/get-listed', [
            'clinic'        => $clinic,
            'ownerName'     => trim((string) (RequestContext::user()['name'] ?? '')),
            'latest'        => DoctorClaimService::latestForTenantPhone((string) ($clinic['phone'] ?? '')),
            'listingStatus' => DoctorClaimService::listingStatus($clinic),
            'listing'       => ClinicSettingsService::publicListing($clinicId),
            'services'      => ClinicSettingsService::servicesForClinic($clinicId),
            'specialties'   => self::specialtyCatalog(),
            'locationPicker'=> LocationCatalogService::pickerPayload(),
            'csrf'          => CsrfService::token(),
            'message'       => $request->query['message'] ?? null,
        ], 'Listed on eClinicPro'));
    }

    /** Save edits to the live public profile (approved clinics only). */
    public function save(Request $request): Response
    {
        $clinic = RequestContext::clinic();
        if (!$clinic) return Response::redirect('/login');

        ClinicSettingsService::saveListing((int) ($clinic['id'] ?? 0), $request->post);

        return Response::redirect('/listing?message=saved');
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
            return Response::redirect('/listing?message=already_listed');
        }

        $tenantId = (int) ($clinic['id'] ?? 0);
        $id = DoctorClaimService::submitFromPortal($tenantId, $clinic, $request->post);
        if ($id === null) {
            return Response::redirect('/listing?message=failed');
        }
        return Response::redirect('/listing?message=submitted');
    }
}
