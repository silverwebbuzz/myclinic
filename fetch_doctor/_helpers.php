<?php
// =====================================================================
// _helpers.php — shared parsing helpers for fetch_doctor
//
// Used by:
//   - index.php (when building fresh JSON from Google Places)
//   - insert_db.php (lazy fallback for legacy JSON that lacks these fields)
// =====================================================================

if (!function_exists('extract_doctor_name')) {
    /**
     * Tries to pull a doctor's personal name out of the clinic/listing name.
     * Returns null if no doctor name is detectable (just a clinic name).
     *
     * Examples:
     *   "Dr. Mitesh Prajapati (Dr. Feelgood's) - Homeopathic Doctors" → "Dr. Mitesh Prajapati"
     *   "Anubhuti Homeo Clinic"                                      → null
     *   "Dr Ketan Shah's Happy Homoeopathic Clinic"                  → "Dr Ketan Shah"
     *   "Dr. Reeta Dave || Dr. Nidhi Dave"                           → "Dr. Reeta Dave"
     */
    function extract_doctor_name(string $listingName): ?string {
        $clean = preg_replace('/[\(\[][^\)\]]*[\)\]]/u', ' ', $listingName) ?? $listingName;
        $clean = trim(preg_replace('/\s+/', ' ', $clean));

        if (!preg_match('/\bDr\.?\s+([A-Z][^,|\-\/\\\\]+?)(?:\s+(?:[\'\x{2019}]s|clinic|hospital|homoeopathic|homeopathic|homoeopath|homeopath|dental|skin|eye|child|children|pediatric|paediatric|cardio|ortho|gyno|gynae|ent|cancer|kidney|nephro|uro|neuro|surgery|surgical|maternity|nursing|polyclinic|multi[\s\-]?speciality|specialist|consultant)\b|$|\s*(?:[|]|,))/iu', $clean, $m)) {
            return null;
        }

        $name = trim($m[1]);
        $name = preg_replace('/\s*(?:[-,|]|\bMD\b|\bMBBS\b|\bBHMS\b|\bBDS\b|\bMS\b|\bDNB\b|\bMRCP\b).*$/iu', '', $name);
        $name = trim($name, " \t\n-,|");

        if (strlen($name) < 2 || !preg_match('/[A-Za-z]/', $name)) return null;
        if (strlen($name) > 80) return null;

        return 'Dr. ' . $name;
    }
}

if (!function_exists('extract_area')) {
    /**
     * Pulls the neighborhood/area from a Google formatted_address.
     *
     * Example input:
     *   "6&7, 2nd FLOOR, A-WING, New SG Rd, opposite SHUKAN, Vandematram Arcade,
     *    Gota, Ahmedabad, Gujarat 382481, India"
     * Returns: "Gota"
     */
    function extract_area(string $address, string $cityName): ?string {
        $parts = array_map('trim', explode(',', $address));
        if (empty($parts)) return null;

        $cityLc = strtolower($cityName);

        for ($i = 0; $i < count($parts); $i++) {
            $partLc = strtolower($parts[$i]);
            if ($partLc === $cityLc || str_starts_with($partLc, $cityLc . ' ')) {
                if ($i === 0) return null;
                $cand = $parts[$i - 1];

                if (strlen($cand) > 60) return null;
                if (preg_match('/^\d{4,}/', $cand)) return null;
                if (preg_match('/^(near|opp|opposite|behind|next to|above|below)\b/i', $cand)) return null;

                return $cand;
            }
        }

        return null;
    }
}
