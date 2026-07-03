<?php

declare(strict_types=1);

/**
 * Area-wise fetching support.
 *
 * WHY: a broad Google Text Search ("gynecologist") over a wide 15km city radius
 * ranks by prominence and returns very few results (7-11) even in cities with
 * hundreds of doctors — and it caps at 60 per query regardless. The reliable,
 * quality-first way to get proper coverage is to query TIGHT, named localities
 * one at a time: each ~3.5km area returns dense, well-ranked results and gets
 * its own 60-cap. Union of many areas ≈ true coverage.
 *
 * This file provides:
 *   - $AREAS: curated locality lists per city (seed for major metros).
 *   - fd_area_coords(): geocode a "Area, City" to lat/lng, cached to disk so we
 *     pay Google's geocoding cost once per area, ever.
 */

const FD_AREA_RADIUS = 2500;   // metres — tight so each area returns its own ~20
                               // WITHOUT needing Google's flaky next_page_token
                               // pagination. Coverage comes from MANY small areas.
const FD_AREA_GEOCACHE = __DIR__ . '/json/_area_geocache.json';

/**
 * Curated localities per city. Keyed by exact city name (must match $STATES).
 * Start with Ahmedabad's real areas; extend other metros incrementally. Cities
 * absent here simply have no Area dropdown and fall back to the city-wide fetch
 * (fine for small towns that fit in one 60-cap query anyway).
 *
 * @var array<string, list<string>>
 */
$AREAS = [
    'Ahmedabad' => [
        'Satellite', 'Navrangpura', 'Vastrapur', 'Bodakdev', 'Bopal',
        'Prahlad Nagar', 'S.G. Highway', 'Thaltej', 'Naranpura', 'Paldi',
        'Maninagar', 'Chandkheda', 'Gota', 'Nikol', 'Naroda',
        'Vastral', 'Vejalpur', 'Ghatlodia', 'Sabarmati', 'Ashram Road',
        'C.G. Road', 'Ellis Bridge', 'Ambawadi', 'Isanpur', 'Ranip',
        'Nava Vadaj', 'Odhav', 'Bapunagar', 'Shahibaug', 'Memnagar',
    ],
    'Surat' => [
        'Adajan', 'Vesu', 'Athwa', 'Piplod', 'Katargam',
        'Varachha', 'Udhna', 'Pal', 'Rander', 'City Light',
        'Ghod Dod Road', 'Nanpura', 'Majura Gate', 'Dumas Road',
    ],
    'Vadodara' => [
        'Alkapuri', 'Gotri', 'Manjalpur', 'Fatehgunj', 'Sayajigunj',
        'Akota', 'Karelibaug', 'Waghodia Road', 'Nizampura', 'Subhanpura',
        'Old Padra Road', 'Vasna', 'Race Course',
    ],
    'Rajkot' => [
        'Kalawad Road', 'University Road', 'Race Course Ring Road', 'Gondal Road',
        '150 Feet Ring Road', 'Yagnik Road', 'Mavdi', 'Nana Mava Road',
    ],

    // ---- Maharashtra ----
    'Mumbai' => [
        'Andheri', 'Bandra', 'Borivali', 'Dadar', 'Ghatkopar',
        'Goregaon', 'Juhu', 'Kandivali', 'Malad', 'Mulund',
        'Powai', 'Vile Parle', 'Chembur', 'Kurla', 'Sion',
        'Worli', 'Colaba', 'Fort', 'Byculla', 'Santacruz',
        'Vikhroli', 'Bhandup', 'Kalyan', 'Dombivli', 'Vashi',
    ],
    'Pune' => [
        'Kothrud', 'Hinjewadi', 'Baner', 'Aundh', 'Wakad',
        'Hadapsar', 'Viman Nagar', 'Kharadi', 'Shivajinagar', 'Camp',
        'Deccan Gymkhana', 'Kondhwa', 'Katraj', 'Pimpri', 'Chinchwad',
        'Nigdi', 'Wagholi', 'Magarpatta', 'Bibwewadi', 'Karve Nagar',
    ],
    'Nagpur' => [
        'Dharampeth', 'Sadar', 'Civil Lines', 'Sitabuldi', 'Ramdaspeth',
        'Manish Nagar', 'Pratap Nagar', 'Wardha Road', 'Trimurti Nagar',
    ],
    'Nashik' => [
        'College Road', 'Gangapur Road', 'Indira Nagar', 'Panchavati',
        'Cidco', 'Deolali', 'Nashik Road', 'Mahatma Nagar',
    ],
    'Thane' => [
        'Ghodbunder Road', 'Naupada', 'Vartak Nagar', 'Kolshet',
        'Majiwada', 'Wagle Estate', 'Kalwa', 'Manpada',
    ],
    'Navi Mumbai' => [
        'Vashi', 'Nerul', 'Belapur', 'Kharghar', 'Airoli',
        'Ghansoli', 'Kopar Khairane', 'Panvel', 'Sanpada', 'Seawoods',
    ],

    // ---- Delhi NCR ----
    'Delhi' => [
        'Rohini', 'Dwarka', 'Saket', 'Pitampura', 'Janakpuri',
        'Lajpat Nagar', 'Karol Bagh', 'Vasant Kunj', 'Preet Vihar', 'Rajouri Garden',
        'Mayur Vihar', 'Paschim Vihar', 'Greater Kailash', 'Defence Colony', 'Model Town',
        'Chanakyapuri', 'Connaught Place', 'Shalimar Bagh', 'Uttam Nagar', 'Vikaspuri',
    ],
    'Noida' => [
        'Sector 18', 'Sector 62', 'Sector 50', 'Sector 76', 'Sector 137',
        'Sector 15', 'Sector 78', 'Sector 110', 'Sector 44',
    ],
    'Gurgaon' => [
        'DLF Phase 1', 'DLF Phase 2', 'DLF Phase 3', 'Sohna Road', 'Golf Course Road',
        'Sector 14', 'Sector 56', 'MG Road', 'Palam Vihar', 'Sushant Lok',
    ],
    'Faridabad' => [
        'NIT', 'Sector 15', 'Sector 21', 'Ballabgarh', 'Old Faridabad', 'Neelam Chowk',
    ],
    'Ghaziabad' => [
        'Indirapuram', 'Vaishali', 'Vasundhara', 'Kaushambi', 'Raj Nagar', 'Crossings Republik',
    ],

    // ---- Karnataka ----
    'Bangalore' => [
        'Koramangala', 'Indiranagar', 'Whitefield', 'Jayanagar', 'JP Nagar',
        'HSR Layout', 'Malleshwaram', 'Rajajinagar', 'BTM Layout', 'Marathahalli',
        'Electronic City', 'Bannerghatta Road', 'Hebbal', 'Yelahanka', 'Banashankari',
        'Basavanagudi', 'RT Nagar', 'Sarjapur Road', 'Bellandur', 'Vijayanagar',
        'KR Puram', 'Yeshwanthpur', 'Ulsoor', 'Frazer Town', 'Kengeri',
    ],
    'Mysore' => [
        'Kuvempunagar', 'Vijayanagar', 'Gokulam', 'Saraswathipuram', 'Hebbal',
        'Jayalakshmipuram', 'Vontikoppal', 'Nazarbad',
    ],
    'Mangalore' => [
        'Kadri', 'Bejai', 'Hampankatta', 'Kankanady', 'Balmatta', 'Surathkal',
    ],

    // ---- Tamil Nadu ----
    'Chennai' => [
        'T. Nagar', 'Anna Nagar', 'Adyar', 'Velachery', 'Nungambakkam',
        'Mylapore', 'Tambaram', 'Porur', 'Chromepet', 'Ambattur',
        'Kilpauk', 'Guindy', 'Perambur', 'Vadapalani', 'Egmore',
        'Royapettah', 'Besant Nagar', 'Kodambakkam', 'OMR', 'Perungudi',
    ],
    'Coimbatore' => [
        'RS Puram', 'Saibaba Colony', 'Peelamedu', 'Ganapathy', 'Gandhipuram',
        'Race Course', 'Singanallur', 'Saravanampatti', 'Ramanathapuram',
    ],
    'Madurai' => [
        'Anna Nagar', 'KK Nagar', 'Simmakkal', 'Goripalayam', 'Bypass Road',
    ],

    // ---- Telangana ----
    'Hyderabad' => [
        'Banjara Hills', 'Jubilee Hills', 'Gachibowli', 'Madhapur', 'Kukatpally',
        'Ameerpet', 'Secunderabad', 'Begumpet', 'Himayatnagar', 'Dilsukhnagar',
        'Miyapur', 'Hitech City', 'Kondapur', 'Manikonda', 'LB Nagar',
        'Uppal', 'Mehdipatnam', 'Somajiguda', 'Nizampet', 'Tolichowki',
    ],

    // ---- West Bengal ----
    'Kolkata' => [
        'Salt Lake', 'New Town', 'Ballygunge', 'Park Street', 'Behala',
        'Gariahat', 'Jadavpur', 'Dumdum', 'Tollygunge', 'Rajarhat',
        'Barasat', 'Howrah', 'Alipore', 'Shyambazar', 'Kasba',
    ],

    // ---- Rajasthan ----
    'Jaipur' => [
        'Malviya Nagar', 'Vaishali Nagar', 'C-Scheme', 'Mansarovar', 'Raja Park',
        'Jagatpura', 'Tonk Road', 'Bani Park', 'Sodala', 'Vidhyadhar Nagar',
        'Jhotwara', 'Sanganer', 'Ajmer Road', 'Civil Lines',
    ],
    'Jodhpur' => [
        'Ratanada', 'Sardarpura', 'Shastri Nagar', 'Paota', 'Mandore Road',
    ],
    'Udaipur' => [
        'Hiran Magri', 'Sector 4', 'Fatehpura', 'Bhuwana', 'Ashok Nagar',
    ],

    // ---- Uttar Pradesh ----
    'Lucknow' => [
        'Gomti Nagar', 'Hazratganj', 'Aliganj', 'Indira Nagar', 'Aminabad',
        'Alambagh', 'Rajajipuram', 'Mahanagar', 'Chowk', 'Jankipuram',
    ],
    'Kanpur' => [
        'Swaroop Nagar', 'Kakadeo', 'Kidwai Nagar', 'Civil Lines', 'Govind Nagar',
        'Arya Nagar', 'Shyam Nagar',
    ],
    'Varanasi' => [
        'Lanka', 'Sigra', 'Bhelupur', 'Cantonment', 'Mahmoorganj', 'Sarnath',
    ],
    'Agra' => [
        'Sadar Bazaar', 'Kamla Nagar', 'Dayal Bagh', 'Sikandra', 'Tajganj',
    ],

    // ---- Kerala ----
    'Kochi' => [
        'Kakkanad', 'Edappally', 'Palarivattom', 'Vyttila', 'Kaloor',
        'Fort Kochi', 'Aluva', 'Tripunithura', 'MG Road', 'Panampilly Nagar',
    ],
    'Thiruvananthapuram' => [
        'Pattom', 'Kowdiar', 'Vazhuthacaud', 'Sasthamangalam', 'Kesavadasapuram',
        'Peroorkada', 'Technopark', 'Thampanoor',
    ],
    'Kozhikode' => [
        'Nadakkavu', 'Mavoor Road', 'Kovoor', 'Chevayur', 'Medical College',
    ],

    // ---- Punjab / Chandigarh ----
    'Ludhiana' => [
        'Model Town', 'Sarabha Nagar', 'Civil Lines', 'BRS Nagar', 'Pakhowal Road',
        'Dugri', 'Ferozepur Road',
    ],
    'Amritsar' => [
        'Ranjit Avenue', 'Lawrence Road', 'Green Avenue', 'Mall Road', 'Majitha Road',
    ],
    'Chandigarh' => [
        'Sector 17', 'Sector 22', 'Sector 35', 'Sector 8', 'Sector 34',
        'Sector 43', 'Manimajra', 'Sector 20',
    ],

    // ---- Madhya Pradesh ----
    'Indore' => [
        'Vijay Nagar', 'Palasia', 'Sudama Nagar', 'Rajwada', 'AB Road',
        'Bhawarkuan', 'Sapna Sangeeta', 'Scheme 78', 'Nipania',
    ],
    'Bhopal' => [
        'MP Nagar', 'Arera Colony', 'Kolar Road', 'Shivaji Nagar', 'New Market',
        'Hoshangabad Road', 'Bairagarh', 'Ayodhya Nagar',
    ],

    // ---- Bihar / Jharkhand ----
    'Patna' => [
        'Kankarbagh', 'Boring Road', 'Rajendra Nagar', 'Patliputra', 'Bailey Road',
        'Kadam Kuan', 'Danapur', 'Ashok Rajpath',
    ],
    'Ranchi' => [
        'Lalpur', 'Kanke Road', 'Harmu', 'Doranda', 'Ashok Nagar', 'Bariatu',
    ],

    // ---- Andhra Pradesh ----
    'Visakhapatnam' => [
        'MVP Colony', 'Dwaraka Nagar', 'Gajuwaka', 'Seethammadhara', 'Madhurawada',
        'Siripuram', 'Rushikonda',
    ],
    'Vijayawada' => [
        'Benz Circle', 'Governorpet', 'Labbipet', 'Patamata', 'Auto Nagar',
    ],
];

/**
 * Resolve a locality to coordinates, caching the result to disk so Google's
 * Geocoding API is called at most once per (area, city). Returns null on failure
 * (caller falls back to the city centre).
 *
 * @return array{lat: float, lng: float, radius: int}|null
 */
function fd_area_coords(string $apiKey, string $area, array $city, int &$reqCount): ?array
{
    $cityName = (string) ($city['name'] ?? '');
    $key = strtolower(trim($area) . '|' . trim($cityName));

    // ---- cache read ----
    $cache = [];
    if (is_file(FD_AREA_GEOCACHE)) {
        $raw = json_decode((string) file_get_contents(FD_AREA_GEOCACHE), true);
        if (is_array($raw)) {
            $cache = $raw;
        }
    }
    if (isset($cache[$key]['lat'], $cache[$key]['lng'])) {
        return [
            'lat'    => (float) $cache[$key]['lat'],
            'lng'    => (float) $cache[$key]['lng'],
            'radius' => FD_AREA_RADIUS,
        ];
    }

    // ---- geocode (one-time) ----
    if ($apiKey === '') {
        return null;
    }
    $address = $area . ', ' . $cityName . ', India';
    $url = 'https://maps.googleapis.com/maps/api/geocode/json?' . http_build_query([
        'address' => $address,
        'region'  => 'in',
        'key'     => $apiKey,
    ]);
    $reqCount++;
    $body = @file_get_contents($url);
    $data = $body !== false ? json_decode((string) $body, true) : null;
    $loc = $data['results'][0]['geometry']['location'] ?? null;
    if (!is_array($loc) || !isset($loc['lat'], $loc['lng'])) {
        return null;
    }

    // ---- cache write (atomic) ----
    $cache[$key] = ['lat' => (float) $loc['lat'], 'lng' => (float) $loc['lng'], 'area' => $area, 'city' => $cityName];
    $tmp = FD_AREA_GEOCACHE . '.' . bin2hex(random_bytes(4)) . '.tmp';
    if (@file_put_contents($tmp, json_encode($cache, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) !== false) {
        @rename($tmp, FD_AREA_GEOCACHE);
    }

    return ['lat' => (float) $loc['lat'], 'lng' => (float) $loc['lng'], 'radius' => FD_AREA_RADIUS];
}

/**
 * Areas known for a city — curated seed unioned with distinct `area` values
 * already saved in that city's JSON (self-enriching as you fetch). Sorted.
 *
 * @return list<string>
 */
function fd_areas_for_city(string $cityName, string $jsonDir): array
{
    global $AREAS;
    $seed = $AREAS[$cityName] ?? [];

    $fromData = [];
    $path = $jsonDir . '/' . preg_replace('/[^a-z0-9]+/', '-', strtolower($cityName)) . '.json';
    if (is_file($path)) {
        $raw = json_decode((string) file_get_contents($path), true);
        foreach ((array) ($raw['doctors'] ?? []) as $d) {
            $a = trim((string) ($d['area'] ?? ''));
            if ($a !== '') {
                $fromData[$a] = true;
            }
        }
    }

    $all = array_values(array_unique(array_merge($seed, array_keys($fromData))));
    sort($all, SORT_NATURAL | SORT_FLAG_CASE);

    return $all;
}
