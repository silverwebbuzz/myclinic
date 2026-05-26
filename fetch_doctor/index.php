<?php
// =====================================================================
// fetch_doctor/index.php
// Pick Indian cities, then fetch doctors from Google Places in small
// AJAX-driven chunks (one sub-area per request) so no single PHP call
// times out. Live progress bar.
//
// Three modes (driven by query string):
//   (no params)            — picker UI
//   ?job={id}              — progress view for a running job
//   ?action=step&job={id}  — does ONE chunk, returns JSON status (AJAX only)
//   ?action=status&job={id}— returns current state JSON without running
//
// Setup once:
//   1. Copy .env.example to .env, set GOOGLE_MAPS_API_KEY
//   2. chmod 0755 fetch_doctor/json fetch_doctor/jobs
// =====================================================================

declare(strict_types=1);

// =====================================================================
// CONFIG
// =====================================================================

$envFile = __DIR__ . '/.env';
$apiKey = '';
if (is_file($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) continue;
        [$k, $v] = explode('=', $line, 2);
        if (trim($k) === 'GOOGLE_MAPS_API_KEY') $apiKey = trim($v, " \t\"'");
    }
}

$STATES = [
    'Gujarat' => [
        ['name' => 'Ahmedabad',      'lat' => 23.0225, 'lng' => 72.5714, 'radius' => 15000],
        ['name' => 'Surat',          'lat' => 21.1702, 'lng' => 72.8311, 'radius' => 15000],
        ['name' => 'Vadodara',       'lat' => 22.3072, 'lng' => 73.1812, 'radius' => 12000],
        ['name' => 'Rajkot',         'lat' => 22.3039, 'lng' => 70.8022, 'radius' => 12000],
        ['name' => 'Bhavnagar',      'lat' => 21.7645, 'lng' => 72.1519, 'radius' => 10000],
        ['name' => 'Gandhinagar',    'lat' => 23.2156, 'lng' => 72.6369, 'radius' => 10000],
        ['name' => 'Jamnagar',       'lat' => 22.4707, 'lng' => 70.0577, 'radius' => 10000],
        ['name' => 'Junagadh',       'lat' => 21.5222, 'lng' => 70.4579, 'radius' =>  9000],
        ['name' => 'Surendranagar',  'lat' => 22.7196, 'lng' => 71.6369, 'radius' =>  8000],
        ['name' => 'Anand',          'lat' => 22.5645, 'lng' => 72.9289, 'radius' =>  8000],
        ['name' => 'Bharuch',        'lat' => 21.7051, 'lng' => 72.9959, 'radius' =>  8000],
        ['name' => 'Mehsana',        'lat' => 23.5879, 'lng' => 72.3693, 'radius' =>  8000],
        ['name' => 'Nadiad',         'lat' => 22.6917, 'lng' => 72.8634, 'radius' =>  8000],
        ['name' => 'Navsari',        'lat' => 20.9467, 'lng' => 72.9520, 'radius' =>  8000],
        ['name' => 'Morbi',          'lat' => 22.8252, 'lng' => 70.8423, 'radius' =>  8000],
        ['name' => 'Vapi',           'lat' => 20.3893, 'lng' => 72.9106, 'radius' =>  8000],
        ['name' => 'Valsad',         'lat' => 20.5992, 'lng' => 72.9342, 'radius' =>  7000],
        ['name' => 'Porbandar',      'lat' => 21.6417, 'lng' => 69.6293, 'radius' =>  7000],
        ['name' => 'Gandhidham',     'lat' => 23.0753, 'lng' => 70.1337, 'radius' =>  7000],
        ['name' => 'Bhuj',           'lat' => 23.2420, 'lng' => 69.6669, 'radius' =>  7000],
        ['name' => 'Patan',          'lat' => 23.8493, 'lng' => 72.1266, 'radius' =>  7000],
        ['name' => 'Palanpur',       'lat' => 24.1722, 'lng' => 72.4317, 'radius' =>  7000],
        ['name' => 'Veraval',        'lat' => 20.9077, 'lng' => 70.3665, 'radius' =>  7000],
        ['name' => 'Godhra',         'lat' => 22.7788, 'lng' => 73.6143, 'radius' =>  7000],
        ['name' => 'Himatnagar',     'lat' => 23.5980, 'lng' => 72.9572, 'radius' =>  7000],
    ],
    'Maharashtra' => [
        ['name' => 'Mumbai',         'lat' => 19.0760, 'lng' => 72.8777, 'radius' => 15000],
        ['name' => 'Pune',           'lat' => 18.5204, 'lng' => 73.8567, 'radius' => 15000],
        ['name' => 'Nagpur',         'lat' => 21.1458, 'lng' => 79.0882, 'radius' => 12000],
        ['name' => 'Nashik',         'lat' => 19.9975, 'lng' => 73.7898, 'radius' => 12000],
        ['name' => 'Aurangabad',     'lat' => 19.8762, 'lng' => 75.3433, 'radius' => 10000],
        ['name' => 'Thane',          'lat' => 19.2183, 'lng' => 72.9781, 'radius' => 10000],
        ['name' => 'Navi Mumbai',    'lat' => 19.0330, 'lng' => 73.0297, 'radius' => 12000],
        ['name' => 'Kolhapur',       'lat' => 16.7050, 'lng' => 74.2433, 'radius' =>  9000],
        ['name' => 'Solapur',        'lat' => 17.6599, 'lng' => 75.9064, 'radius' =>  9000],
        ['name' => 'Amravati',       'lat' => 20.9374, 'lng' => 77.7796, 'radius' =>  8000],
        ['name' => 'Akola',          'lat' => 20.7002, 'lng' => 77.0082, 'radius' =>  8000],
        ['name' => 'Jalgaon',        'lat' => 21.0077, 'lng' => 75.5626, 'radius' =>  8000],
        ['name' => 'Sangli',         'lat' => 16.8524, 'lng' => 74.5815, 'radius' =>  8000],
        ['name' => 'Latur',          'lat' => 18.4088, 'lng' => 76.5604, 'radius' =>  7000],
        ['name' => 'Nanded',         'lat' => 19.1383, 'lng' => 77.3210, 'radius' =>  8000],
        ['name' => 'Chandrapur',     'lat' => 19.9615, 'lng' => 79.2961, 'radius' =>  7000],
        ['name' => 'Ahmednagar',     'lat' => 19.0948, 'lng' => 74.7480, 'radius' =>  7000],
    ],
    'Delhi NCR' => [
        ['name' => 'Delhi',          'lat' => 28.7041, 'lng' => 77.1025, 'radius' => 15000],
        ['name' => 'Noida',          'lat' => 28.5355, 'lng' => 77.3910, 'radius' => 12000],
        ['name' => 'Gurgaon',        'lat' => 28.4595, 'lng' => 77.0266, 'radius' => 12000],
        ['name' => 'Faridabad',      'lat' => 28.4089, 'lng' => 77.3178, 'radius' => 12000],
        ['name' => 'Ghaziabad',      'lat' => 28.6692, 'lng' => 77.4538, 'radius' => 12000],
        ['name' => 'Greater Noida',  'lat' => 28.4744, 'lng' => 77.5040, 'radius' => 10000],
        ['name' => 'Meerut',         'lat' => 28.9845, 'lng' => 77.7064, 'radius' => 10000],
        ['name' => 'Sonipat',        'lat' => 28.9931, 'lng' => 77.0151, 'radius' =>  8000],
    ],
    'Karnataka' => [
        ['name' => 'Bangalore',      'lat' => 12.9716, 'lng' => 77.5946, 'radius' => 15000],
        ['name' => 'Mysore',         'lat' => 12.2958, 'lng' => 76.6394, 'radius' => 10000],
        ['name' => 'Mangalore',      'lat' => 12.9141, 'lng' => 74.8560, 'radius' => 10000],
        ['name' => 'Hubli',          'lat' => 15.3647, 'lng' => 75.1240, 'radius' => 10000],
        ['name' => 'Belgaum',        'lat' => 15.8497, 'lng' => 74.4977, 'radius' =>  9000],
        ['name' => 'Davanagere',     'lat' => 14.4644, 'lng' => 75.9218, 'radius' =>  8000],
        ['name' => 'Tumkur',         'lat' => 13.3409, 'lng' => 77.1010, 'radius' =>  8000],
        ['name' => 'Shimoga',        'lat' => 13.9299, 'lng' => 75.5681, 'radius' =>  7000],
        ['name' => 'Bellary',        'lat' => 15.1394, 'lng' => 76.9214, 'radius' =>  8000],
        ['name' => 'Gulbarga',       'lat' => 17.3297, 'lng' => 76.8343, 'radius' =>  8000],
        ['name' => 'Udupi',          'lat' => 13.3409, 'lng' => 74.7421, 'radius' =>  7000],
    ],
    'Tamil Nadu' => [
        ['name' => 'Chennai',        'lat' => 13.0827, 'lng' => 80.2707, 'radius' => 15000],
        ['name' => 'Coimbatore',     'lat' => 11.0168, 'lng' => 76.9558, 'radius' => 12000],
        ['name' => 'Madurai',        'lat' =>  9.9252, 'lng' => 78.1198, 'radius' => 10000],
        ['name' => 'Tiruchirappalli','lat' => 10.7905, 'lng' => 78.7047, 'radius' => 10000],
        ['name' => 'Salem',          'lat' => 11.6643, 'lng' => 78.1460, 'radius' =>  9000],
        ['name' => 'Tirunelveli',    'lat' =>  8.7139, 'lng' => 77.7567, 'radius' =>  8000],
        ['name' => 'Erode',          'lat' => 11.3410, 'lng' => 77.7172, 'radius' =>  8000],
        ['name' => 'Vellore',        'lat' => 12.9165, 'lng' => 79.1325, 'radius' =>  8000],
        ['name' => 'Thoothukudi',    'lat' =>  8.7642, 'lng' => 78.1348, 'radius' =>  8000],
        ['name' => 'Thanjavur',      'lat' => 10.7870, 'lng' => 79.1378, 'radius' =>  7000],
        ['name' => 'Dindigul',       'lat' => 10.3624, 'lng' => 77.9695, 'radius' =>  7000],
        ['name' => 'Hosur',          'lat' => 12.7409, 'lng' => 77.8253, 'radius' =>  7000],
    ],
    'Telangana' => [
        ['name' => 'Hyderabad',      'lat' => 17.3850, 'lng' => 78.4867, 'radius' => 15000],
        ['name' => 'Warangal',       'lat' => 17.9689, 'lng' => 79.5941, 'radius' => 10000],
        ['name' => 'Karimnagar',     'lat' => 18.4386, 'lng' => 79.1288, 'radius' =>  8000],
        ['name' => 'Nizamabad',      'lat' => 18.6725, 'lng' => 78.0941, 'radius' =>  7000],
        ['name' => 'Khammam',        'lat' => 17.2473, 'lng' => 80.1514, 'radius' =>  7000],
    ],
    'West Bengal' => [
        ['name' => 'Kolkata',        'lat' => 22.5726, 'lng' => 88.3639, 'radius' => 15000],
        ['name' => 'Howrah',         'lat' => 22.5958, 'lng' => 88.2636, 'radius' => 10000],
        ['name' => 'Siliguri',       'lat' => 26.7271, 'lng' => 88.3953, 'radius' => 10000],
        ['name' => 'Durgapur',       'lat' => 23.5204, 'lng' => 87.3119, 'radius' =>  9000],
        ['name' => 'Asansol',        'lat' => 23.6889, 'lng' => 86.9661, 'radius' =>  9000],
        ['name' => 'Kharagpur',      'lat' => 22.3460, 'lng' => 87.2320, 'radius' =>  8000],
        ['name' => 'Malda',          'lat' => 25.0119, 'lng' => 88.1433, 'radius' =>  7000],
    ],
    'Rajasthan' => [
        ['name' => 'Jaipur',         'lat' => 26.9124, 'lng' => 75.7873, 'radius' => 12000],
        ['name' => 'Jodhpur',        'lat' => 26.2389, 'lng' => 73.0243, 'radius' => 10000],
        ['name' => 'Udaipur',        'lat' => 24.5854, 'lng' => 73.7125, 'radius' => 10000],
        ['name' => 'Kota',           'lat' => 25.2138, 'lng' => 75.8648, 'radius' => 10000],
        ['name' => 'Ajmer',          'lat' => 26.4499, 'lng' => 74.6399, 'radius' =>  9000],
        ['name' => 'Bikaner',        'lat' => 28.0229, 'lng' => 73.3119, 'radius' =>  9000],
        ['name' => 'Sikar',          'lat' => 27.6094, 'lng' => 75.1399, 'radius' =>  7000],
        ['name' => 'Alwar',          'lat' => 27.5530, 'lng' => 76.6346, 'radius' =>  8000],
        ['name' => 'Bhilwara',       'lat' => 25.3463, 'lng' => 74.6364, 'radius' =>  7000],
    ],
    'Uttar Pradesh' => [
        ['name' => 'Lucknow',        'lat' => 26.8467, 'lng' => 80.9462, 'radius' => 12000],
        ['name' => 'Kanpur',         'lat' => 26.4499, 'lng' => 80.3319, 'radius' => 12000],
        ['name' => 'Varanasi',       'lat' => 25.3176, 'lng' => 82.9739, 'radius' => 10000],
        ['name' => 'Agra',           'lat' => 27.1767, 'lng' => 78.0081, 'radius' => 10000],
        ['name' => 'Prayagraj',      'lat' => 25.4358, 'lng' => 81.8463, 'radius' => 10000],
        ['name' => 'Bareilly',       'lat' => 28.3670, 'lng' => 79.4304, 'radius' =>  9000],
        ['name' => 'Aligarh',        'lat' => 27.8974, 'lng' => 78.0880, 'radius' =>  8000],
        ['name' => 'Moradabad',      'lat' => 28.8389, 'lng' => 78.7768, 'radius' =>  8000],
        ['name' => 'Gorakhpur',      'lat' => 26.7606, 'lng' => 83.3732, 'radius' =>  9000],
        ['name' => 'Saharanpur',     'lat' => 29.9680, 'lng' => 77.5552, 'radius' =>  8000],
        ['name' => 'Jhansi',         'lat' => 25.4484, 'lng' => 78.5685, 'radius' =>  8000],
        ['name' => 'Mathura',        'lat' => 27.4924, 'lng' => 77.6737, 'radius' =>  7000],
        ['name' => 'Ayodhya',        'lat' => 26.7922, 'lng' => 82.1998, 'radius' =>  7000],
    ],
    'Kerala' => [
        ['name' => 'Kochi',              'lat' =>  9.9312, 'lng' => 76.2673, 'radius' => 12000],
        ['name' => 'Thiruvananthapuram', 'lat' =>  8.5241, 'lng' => 76.9366, 'radius' => 12000],
        ['name' => 'Kozhikode',          'lat' => 11.2588, 'lng' => 75.7804, 'radius' => 10000],
        ['name' => 'Thrissur',           'lat' => 10.5276, 'lng' => 76.2144, 'radius' =>  9000],
        ['name' => 'Kollam',             'lat' =>  8.8932, 'lng' => 76.6141, 'radius' =>  8000],
        ['name' => 'Kannur',             'lat' => 11.8745, 'lng' => 75.3704, 'radius' =>  8000],
        ['name' => 'Alappuzha',          'lat' =>  9.4981, 'lng' => 76.3388, 'radius' =>  7000],
        ['name' => 'Palakkad',           'lat' => 10.7867, 'lng' => 76.6548, 'radius' =>  7000],
        ['name' => 'Kottayam',           'lat' =>  9.5916, 'lng' => 76.5222, 'radius' =>  7000],
    ],
    'Punjab' => [
        ['name' => 'Ludhiana',       'lat' => 30.9000, 'lng' => 75.8573, 'radius' => 10000],
        ['name' => 'Amritsar',       'lat' => 31.6340, 'lng' => 74.8723, 'radius' => 10000],
        ['name' => 'Chandigarh',     'lat' => 30.7333, 'lng' => 76.7794, 'radius' => 10000],
        ['name' => 'Jalandhar',      'lat' => 31.3260, 'lng' => 75.5762, 'radius' =>  9000],
        ['name' => 'Patiala',        'lat' => 30.3398, 'lng' => 76.3869, 'radius' =>  8000],
        ['name' => 'Bathinda',       'lat' => 30.2110, 'lng' => 74.9455, 'radius' =>  8000],
        ['name' => 'Mohali',         'lat' => 30.7046, 'lng' => 76.7179, 'radius' =>  8000],
    ],
    'Haryana' => [
        ['name' => 'Karnal',         'lat' => 29.6857, 'lng' => 76.9905, 'radius' =>  8000],
        ['name' => 'Panipat',        'lat' => 29.3909, 'lng' => 76.9635, 'radius' =>  8000],
        ['name' => 'Hisar',          'lat' => 29.1492, 'lng' => 75.7217, 'radius' =>  8000],
        ['name' => 'Ambala',         'lat' => 30.3782, 'lng' => 76.7767, 'radius' =>  8000],
        ['name' => 'Rohtak',         'lat' => 28.8955, 'lng' => 76.6066, 'radius' =>  8000],
        ['name' => 'Yamunanagar',    'lat' => 30.1290, 'lng' => 77.2674, 'radius' =>  7000],
    ],
    // ----- State capitals & secondary metros for nationwide coverage -----
    'Madhya Pradesh' => [
        ['name' => 'Indore',         'lat' => 22.7196, 'lng' => 75.8577, 'radius' => 12000],
        ['name' => 'Bhopal',         'lat' => 23.2599, 'lng' => 77.4126, 'radius' => 12000],
        ['name' => 'Jabalpur',       'lat' => 23.1815, 'lng' => 79.9864, 'radius' => 10000],
        ['name' => 'Gwalior',        'lat' => 26.2183, 'lng' => 78.1828, 'radius' => 10000],
        ['name' => 'Ujjain',         'lat' => 23.1765, 'lng' => 75.7885, 'radius' =>  9000],
        ['name' => 'Sagar',          'lat' => 23.8388, 'lng' => 78.7378, 'radius' =>  8000],
        ['name' => 'Ratlam',         'lat' => 23.3315, 'lng' => 75.0367, 'radius' =>  7000],
        ['name' => 'Rewa',           'lat' => 24.5362, 'lng' => 81.3037, 'radius' =>  7000],
    ],
    'Chhattisgarh' => [
        ['name' => 'Raipur',         'lat' => 21.2514, 'lng' => 81.6296, 'radius' => 10000],
        ['name' => 'Bhilai',         'lat' => 21.1938, 'lng' => 81.3509, 'radius' =>  9000],
        ['name' => 'Bilaspur',       'lat' => 22.0797, 'lng' => 82.1409, 'radius' =>  8000],
        ['name' => 'Korba',          'lat' => 22.3595, 'lng' => 82.7501, 'radius' =>  7000],
    ],
    'Odisha' => [
        ['name' => 'Bhubaneswar',    'lat' => 20.2961, 'lng' => 85.8245, 'radius' => 10000],
        ['name' => 'Cuttack',        'lat' => 20.4625, 'lng' => 85.8828, 'radius' => 10000],
        ['name' => 'Rourkela',       'lat' => 22.2604, 'lng' => 84.8536, 'radius' =>  8000],
        ['name' => 'Berhampur',      'lat' => 19.3149, 'lng' => 84.7941, 'radius' =>  8000],
        ['name' => 'Sambalpur',      'lat' => 21.4669, 'lng' => 83.9756, 'radius' =>  7000],
        ['name' => 'Puri',           'lat' => 19.8135, 'lng' => 85.8312, 'radius' =>  6000],
    ],
    'Bihar' => [
        ['name' => 'Patna',          'lat' => 25.5941, 'lng' => 85.1376, 'radius' => 12000],
        ['name' => 'Gaya',           'lat' => 24.7914, 'lng' => 85.0002, 'radius' =>  9000],
        ['name' => 'Bhagalpur',      'lat' => 25.2425, 'lng' => 86.9842, 'radius' =>  8000],
        ['name' => 'Muzaffarpur',    'lat' => 26.1209, 'lng' => 85.3647, 'radius' =>  8000],
        ['name' => 'Darbhanga',      'lat' => 26.1542, 'lng' => 85.8918, 'radius' =>  7000],
        ['name' => 'Purnia',         'lat' => 25.7771, 'lng' => 87.4753, 'radius' =>  7000],
    ],
    'Jharkhand' => [
        ['name' => 'Ranchi',         'lat' => 23.3441, 'lng' => 85.3096, 'radius' => 10000],
        ['name' => 'Jamshedpur',     'lat' => 22.8046, 'lng' => 86.2029, 'radius' => 10000],
        ['name' => 'Dhanbad',        'lat' => 23.7957, 'lng' => 86.4304, 'radius' =>  9000],
        ['name' => 'Bokaro',         'lat' => 23.6693, 'lng' => 86.1511, 'radius' =>  8000],
        ['name' => 'Hazaribagh',     'lat' => 23.9929, 'lng' => 85.3650, 'radius' =>  7000],
    ],
    'Assam' => [
        ['name' => 'Guwahati',       'lat' => 26.1445, 'lng' => 91.7362, 'radius' => 10000],
        ['name' => 'Silchar',        'lat' => 24.8333, 'lng' => 92.7789, 'radius' =>  8000],
        ['name' => 'Dibrugarh',      'lat' => 27.4728, 'lng' => 94.9120, 'radius' =>  7000],
        ['name' => 'Jorhat',         'lat' => 26.7509, 'lng' => 94.2037, 'radius' =>  7000],
        ['name' => 'Tezpur',         'lat' => 26.6529, 'lng' => 92.7926, 'radius' =>  6000],
    ],
    'Andhra Pradesh' => [
        ['name' => 'Visakhapatnam',  'lat' => 17.6868, 'lng' => 83.2185, 'radius' => 12000],
        ['name' => 'Vijayawada',     'lat' => 16.5062, 'lng' => 80.6480, 'radius' => 10000],
        ['name' => 'Guntur',         'lat' => 16.3067, 'lng' => 80.4365, 'radius' =>  9000],
        ['name' => 'Tirupati',       'lat' => 13.6288, 'lng' => 79.4192, 'radius' =>  8000],
        ['name' => 'Nellore',        'lat' => 14.4426, 'lng' => 79.9865, 'radius' =>  8000],
        ['name' => 'Kurnool',        'lat' => 15.8281, 'lng' => 78.0373, 'radius' =>  8000],
        ['name' => 'Rajahmundry',    'lat' => 17.0005, 'lng' => 81.8040, 'radius' =>  7000],
        ['name' => 'Kakinada',       'lat' => 16.9891, 'lng' => 82.2475, 'radius' =>  7000],
        ['name' => 'Anantapur',      'lat' => 14.6819, 'lng' => 77.6006, 'radius' =>  7000],
    ],
    'Goa' => [
        ['name' => 'Panaji',         'lat' => 15.4909, 'lng' => 73.8278, 'radius' =>  8000],
        ['name' => 'Margao',         'lat' => 15.2832, 'lng' => 73.9862, 'radius' =>  7000],
        ['name' => 'Vasco da Gama',  'lat' => 15.3958, 'lng' => 73.8157, 'radius' =>  6000],
    ],
    'Uttarakhand' => [
        ['name' => 'Dehradun',       'lat' => 30.3165, 'lng' => 78.0322, 'radius' => 10000],
        ['name' => 'Haridwar',       'lat' => 29.9457, 'lng' => 78.1642, 'radius' =>  8000],
        ['name' => 'Rishikesh',      'lat' => 30.0869, 'lng' => 78.2676, 'radius' =>  6000],
        ['name' => 'Roorkee',        'lat' => 29.8543, 'lng' => 77.8880, 'radius' =>  7000],
        ['name' => 'Haldwani',       'lat' => 29.2183, 'lng' => 79.5130, 'radius' =>  7000],
    ],
    'Himachal Pradesh' => [
        ['name' => 'Shimla',         'lat' => 31.1048, 'lng' => 77.1734, 'radius' =>  7000],
        ['name' => 'Dharamshala',    'lat' => 32.2190, 'lng' => 76.3234, 'radius' =>  6000],
        ['name' => 'Mandi',          'lat' => 31.7081, 'lng' => 76.9319, 'radius' =>  6000],
        ['name' => 'Solan',          'lat' => 30.9045, 'lng' => 77.0967, 'radius' =>  6000],
    ],
    'Jammu & Kashmir' => [
        ['name' => 'Jammu',          'lat' => 32.7266, 'lng' => 74.8570, 'radius' => 10000],
        ['name' => 'Srinagar',       'lat' => 34.0837, 'lng' => 74.7973, 'radius' => 10000],
        ['name' => 'Udhampur',       'lat' => 32.9239, 'lng' => 75.1416, 'radius' =>  6000],
    ],
];

// Specialty queries — grouped here as comments for clarity. Each line gets
// its own pass through the 9-area sub-grid. Sub-specialties (prosthodontist,
// siddha, etc.) get focused queries so the resulting JSON is properly tagged.
$QUERIES = [
    // ----- General Physicians & Specialists -----
    ['q' => 'general physician',     'spec' => 'gp'],
    ['q' => 'family medicine doctor','spec' => 'family_medicine'],
    ['q' => 'ophthalmologist',       'spec' => 'eye'],
    ['q' => 'dermatologist',         'spec' => 'derma'],
    ['q' => 'cosmetologist skin',    'spec' => 'cosmetology'],
    ['q' => 'trichologist hair',     'spec' => 'trichology'],
    ['q' => 'cardiologist',          'spec' => 'cardio'],
    ['q' => 'psychiatrist',          'spec' => 'psychiatrist'],
    ['q' => 'gastroenterologist',    'spec' => 'gastro'],
    ['q' => 'hepatologist liver',    'spec' => 'hepatology'],
    ['q' => 'ENT specialist',        'spec' => 'ent'],
    ['q' => 'gynecologist',          'spec' => 'gyno'],
    ['q' => 'IVF fertility specialist','spec' => 'fertility'],
    ['q' => 'neurologist',           'spec' => 'neuro'],
    ['q' => 'urologist',             'spec' => 'urologist'],
    ['q' => 'andrologist sexologist','spec' => 'andrology'],
    ['q' => 'sexologist',            'spec' => 'sexology'],
    ['q' => 'pediatrician',          'spec' => 'peds'],
    ['q' => 'orthopedic',            'spec' => 'ortho'],
    ['q' => 'sports medicine doctor','spec' => 'sports_medicine'],
    ['q' => 'rheumatologist',        'spec' => 'rheumatology'],
    ['q' => 'pain management doctor','spec' => 'pain_management'],
    ['q' => 'oncologist cancer',     'spec' => 'oncology'],
    ['q' => 'hematologist',          'spec' => 'hematology'],
    ['q' => 'pulmonologist',         'spec' => 'pulmonology'],
    ['q' => 'allergist immunologist','spec' => 'allergy'],
    ['q' => 'nephrologist',          'spec' => 'nephrology'],
    ['q' => 'diabetologist',         'spec' => 'diabetology'],
    ['q' => 'endocrinologist',       'spec' => 'endocrinology'],
    ['q' => 'neurosurgeon',          'spec' => 'neurosurgery'],
    ['q' => 'spine surgeon',         'spec' => 'spine'],
    ['q' => 'gastrointestinal surgeon laparoscopic', 'spec' => 'gi_surgery'],
    ['q' => 'general surgeon',       'spec' => 'general_surgery'],
    ['q' => 'plastic surgeon cosmetic','spec' => 'plastic_surgery'],
    ['q' => 'bariatric surgeon obesity','spec' => 'bariatric'],
    ['q' => 'vascular surgeon',      'spec' => 'vascular'],
    ['q' => 'radiologist diagnostic center', 'spec' => 'radiology'],
    ['q' => 'critical care icu',     'spec' => 'critical_care'],

    // ----- Dentists (general + sub-specialties) -----
    ['q' => 'dentist',               'spec' => 'dental'],
    ['q' => 'prosthodontist',        'spec' => 'prosthodontist'],
    ['q' => 'orthodontist',          'spec' => 'orthodontist'],
    ['q' => 'pediatric dentist',     'spec' => 'pediatric_dentist'],
    ['q' => 'endodontist',           'spec' => 'endodontist'],
    ['q' => 'dental implant clinic', 'spec' => 'implantologist'],

    // ----- Alternative Medicine -----
    ['q' => 'ayurveda clinic',       'spec' => 'ayurveda'],
    ['q' => 'homeopathy clinic',     'spec' => 'homeo'],
    ['q' => 'siddha clinic',         'spec' => 'siddha'],
    ['q' => 'unani clinic',          'spec' => 'unani'],
    ['q' => 'naturopathy clinic',    'spec' => 'naturopathy'],

    // ----- Therapists & Nutritionists -----
    ['q' => 'acupuncturist',         'spec' => 'acupuncturist'],
    ['q' => 'physiotherapist',       'spec' => 'physio'],
    ['q' => 'psychologist',          'spec' => 'psychologist'],
    ['q' => 'audiologist',           'spec' => 'audiologist'],
    ['q' => 'speech therapist',      'spec' => 'speech'],
    ['q' => 'dietitian nutritionist','spec' => 'dietitian'],
];

$JSON_DIR = __DIR__ . '/json';
$JOBS_DIR = __DIR__ . '/jobs';
foreach ([$JSON_DIR, $JOBS_DIR] as $d) {
    if (!is_dir($d)) @mkdir($d, 0755, true);
}

// =====================================================================
// HELPERS — shared
// =====================================================================

function find_city(array $states, string $name): ?array {
    foreach ($states as $stateName => $cities) {
        foreach ($cities as $c) {
            if (strcasecmp($c['name'], $name) === 0) return $c + ['state' => $stateName];
        }
    }
    return null;
}

function slugify(string $s): string {
    $s = strtolower(trim($s));
    $s = preg_replace('/[^a-z0-9]+/', '-', $s) ?? '';
    return trim($s, '-');
}

/**
 * Adaptive grid: bigger cities get a denser scan.
 *  - radius ≤ 10 km  → 3x3   (smaller towns: Rajkot, Surat, Vadodara)
 *  - radius ≤ 12 km  → 5x5   (mid-size: Pune, Bangalore, Chennai)
 *  - radius >  12 km → 7x7   (mega-metros: Mumbai, Delhi, Ahmedabad)
 *
 * Each sub-area covers ~30% of the city radius so neighbors overlap.
 * Each sub-area gets its OWN 60-result Google cap, so dense neighborhoods
 * like Gota in Ahmedabad get a dedicated scan rather than being absorbed
 * into a single overcapped city-wide query.
 */
function generate_sub_areas(float $lat, float $lng, int $cityRadius): array {
    if     ($cityRadius >  12000) $n = 7;   // 49 sub-areas
    elseif ($cityRadius >  10000) $n = 5;   // 25 sub-areas
    else                          $n = 3;   //  9 sub-areas

    $half = (int) (($n - 1) / 2);             // 1, 2, or 3
    $offsetKm = ($cityRadius / 1000.0);       // total span in km
    $latStep = $offsetKm / 111.0 / $n;        // single step in degrees
    $lngStep = $offsetKm / (111.0 * cos(deg2rad($lat))) / $n;
    $subRadius = (int) round($cityRadius * 0.30);

    $areas = [];
    for ($dy = -$half; $dy <= $half; $dy++) {
        for ($dx = -$half; $dx <= $half; $dx++) {
            $areas[] = [
                'lat'    => $lat + ($latStep * $dy),
                'lng'    => $lng + ($lngStep * $dx),
                'radius' => $subRadius,
            ];
        }
    }
    return $areas;
}

function places_type_acceptable(array $types): bool {
    $accept = ['doctor', 'dentist', 'hospital', 'physiotherapist', 'health'];
    $reject = ['pharmacy', 'drugstore', 'health_food', 'spa'];
    if (array_intersect($types, $accept)) return true;
    if (array_intersect($types, $reject)) return false;
    return true;
}

require_once __DIR__ . '/_helpers.php';

function format_doctor(array $place, array $details, array $city, string $spec): array {
    $loc = $details['geometry']['location'] ?? [];
    $lastReviewAt = null;
    if (isset($details['reviews']) && is_array($details['reviews'])) {
        $latest = 0;
        foreach ($details['reviews'] as $r) {
            $t = (int) ($r['time'] ?? 0);
            if ($t > $latest) $latest = $t;
        }
        if ($latest > 0) $lastReviewAt = date('Y-m-d H:i:s', $latest);
    }
    $photoRef = null;
    if (isset($details['photos'][0]['photo_reference'])) {
        $photoRef = (string) $details['photos'][0]['photo_reference'];
    }
    $rawName = (string) ($details['name'] ?? $place['name'] ?? '');
    $address = $details['formatted_address'] ?? null;

    return [
        'place_id'        => $place['place_id'] ?? null,
        'name'            => $rawName,                                  // full Google listing (e.g. "Dr. Mitesh Prajapati (Dr. Feelgood's) - Homeopathic Doctors")
        'doctor_name'     => extract_doctor_name($rawName),             // parsed personal name or null
        'specialty'       => $spec,
        'city'            => $city['name'],
        'state'           => $city['state'],
        'country'         => 'IN',
        'address'         => $address,
        'area'            => $address ? extract_area($address, $city['name']) : null,
        'lat'             => isset($loc['lat']) ? (float) $loc['lat'] : null,
        'lng'             => isset($loc['lng']) ? (float) $loc['lng'] : null,
        'phone'           => $details['formatted_phone_number'] ?? null,
        'intl_phone'      => $details['international_phone_number'] ?? null,
        'website'         => $details['website'] ?? null,
        'gmaps_url'       => $details['url'] ?? null,
        'plus_code'       => $details['plus_code']['compound_code'] ?? null,
        'status'          => $details['business_status'] ?? 'OPERATIONAL',
        'rating'          => isset($details['rating']) ? (float) $details['rating'] : null,
        'reviews'         => isset($details['user_ratings_total']) ? (int) $details['user_ratings_total'] : 0,
        'price_level'     => isset($details['price_level']) ? (int) $details['price_level'] : null,
        'last_review_at'  => $lastReviewAt,
        'types'           => array_values((array) ($details['types'] ?? [])),
        'opening_hours'   => $details['opening_hours']['weekday_text'] ?? null,
        'photo_reference' => $photoRef,
        'fetched_at'      => date('Y-m-d H:i:s'),
    ];
}

function quality_check(array $row, array $city): string {
    $status = $row['status'] ?? 'OPERATIONAL';
    if ($status === 'CLOSED_PERMANENTLY' || $status === 'CLOSED_TEMPORARILY') return 'SKIP:closed';
    if (empty($row['address'])) return 'SKIP:no_address';
    $addr = strtolower((string) $row['address']);
    if (!str_contains($addr, strtolower($city['name']))) return 'SKIP:city_mismatch';
    if ((int) ($row['reviews'] ?? 0) < 1) return 'low_reviews';
    if (empty($row['opening_hours'])) return 'no_hours';
    if (!empty($row['last_review_at'])) {
        $age = time() - strtotime((string) $row['last_review_at']);
        if ($age > 18 * 30 * 86400) return 'stale_reviews';
    }
    return '';
}

function dedupe_by_place_id(array $rows): array {
    $seen = [];
    $out = [];
    foreach ($rows as $r) {
        $k = $r['place_id'] ?? null;
        if (!$k || isset($seen[$k])) continue;
        $seen[$k] = true;
        $out[] = $r;
    }
    return $out;
}

// =====================================================================
// HTTP — Google Places calls (per-chunk; brief enough to fit in one PHP call)
// =====================================================================

function http_get_json(string $url, int &$reqCount): ?array {
    for ($attempt = 1; $attempt <= 2; $attempt++) {
        $reqCount++;
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 25,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_USERAGENT => 'eClinicPro-Fetcher/1.0',
        ]);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($body === false || $code === 0 || ($code >= 500 && $code < 600)) {
            if ($attempt === 1) { sleep(2); continue; }
            return null;
        }
        if ($code !== 200) return null;
        $data = json_decode((string) $body, true);
        if (!is_array($data)) return null;

        $status = $data['status'] ?? '';
        if ($status === 'OK' || $status === 'ZERO_RESULTS') return $data;
        if ($status === 'INVALID_REQUEST' && $attempt === 1) { sleep(3); continue; }
        if ($status === 'OVER_QUERY_LIMIT' && $attempt === 1) { sleep(5); continue; }
        if ($status === 'UNKNOWN_ERROR' && $attempt === 1) { sleep(2); continue; }
        return null;
    }
    return null;
}

function fetch_text_search(string $apiKey, string $query, float $lat, float $lng, int $radius, int &$reqCount): array {
    $baseParams = [
        'query'    => $query,
        'location' => sprintf('%f,%f', $lat, $lng),
        'radius'   => (string) $radius,
        'key'      => $apiKey,
    ];
    $all = [];
    $pageToken = null;
    for ($page = 0; $page < 3; $page++) {
        if ($pageToken !== null) {
            sleep(3);
            $params = ['pagetoken' => $pageToken, 'key' => $apiKey];
        } else {
            $params = $baseParams;
        }
        $url = 'https://maps.googleapis.com/maps/api/place/textsearch/json?' . http_build_query($params);
        $data = http_get_json($url, $reqCount);
        if (!$data) break;
        foreach ((array) ($data['results'] ?? []) as $row) $all[] = $row;
        $pageToken = $data['next_page_token'] ?? null;
        if (!$pageToken) break;
    }
    return $all;
}

function fetch_place_details(string $apiKey, string $placeId, int &$reqCount): ?array {
    $url = 'https://maps.googleapis.com/maps/api/place/details/json?' . http_build_query([
        'place_id' => $placeId,
        'fields'   => 'name,formatted_address,geometry/location,formatted_phone_number,'
                    . 'international_phone_number,website,url,opening_hours/weekday_text,'
                    . 'rating,user_ratings_total,price_level,reviews/time,'
                    . 'types,business_status,plus_code,photo,permanently_closed',
        'key'      => $apiKey,
    ]);
    $data = http_get_json($url, $reqCount);
    return is_array($data['result'] ?? null) ? $data['result'] : null;
}

// =====================================================================
// JOB STATE — one JSON file per job, atomic writes
// =====================================================================

function job_path(string $id): string {
    global $JOBS_DIR;
    $id = preg_replace('/[^a-z0-9]/', '', $id) ?: 'invalid';
    return $JOBS_DIR . '/' . $id . '.json';
}

function job_load(string $id): ?array {
    $p = job_path($id);
    if (!is_file($p)) return null;
    $raw = json_decode((string) file_get_contents($p), true);
    return is_array($raw) ? $raw : null;
}

function job_save(array $job): void {
    $p = job_path($job['id']);
    $tmp = $p . '.tmp';
    file_put_contents($tmp, json_encode($job, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    rename($tmp, $p);
}

function job_create(array $cities, array $queries): string {
    $id = bin2hex(random_bytes(6));
    $tasks = [];
    // Start with ONE city-wide probe per (city, query) pair. If a probe
    // saturates Google's 60-result cap, the worker enqueues the sub-area
    // grid tasks dynamically. Saves ~40% of API calls for small specialties.
    foreach ($cities as $city) {
        foreach ($queries as $qIdx => $qrow) {
            $tasks[] = [
                'kind'    => 'probe',
                'city'    => $city['name'],
                'state'   => $city['state'],
                'q'       => $qrow['q'],
                'spec'    => $qrow['spec'],
                'area'    => [
                    'lat'    => $city['lat'],
                    'lng'    => $city['lng'],
                    'radius' => $city['radius'],
                ],
                'qIdx'    => $qIdx,
                'aIdx'    => 0,
                'qCount'  => count($queries),
                'aCount'  => 1,           // probe is 1-of-1 until grid expands
                'cityRef' => $city,
            ];
        }
    }
    $job = [
        'id'         => $id,
        'created_at' => date('Y-m-d H:i:s'),
        'cities'     => array_column($cities, 'name'),
        'cursor'     => 0,
        'total'      => count($tasks),
        'tasks'      => $tasks,
        'totals'     => [
            'doctors_new'    => 0,
            'requests'       => 0,
            'skipped_closed' => 0,
            'skipped_type'   => 0,
            'skipped_addr'   => 0,
            'skipped_city'   => 0,
            'flagged'        => 0,
            'detail_fail'    => 0,
        ],
        'log'        => [],   // recent log lines (capped)
        'status'     => 'running',  // running | paused | done | error
        'updated_at' => date('Y-m-d H:i:s'),
    ];
    job_save($job);
    return $id;
}

function job_append_log(array &$job, string $msg): void {
    $job['log'][] = '[' . date('H:i:s') . '] ' . $msg;
    // Cap log so the JSON doesn't grow unbounded
    if (count($job['log']) > 200) $job['log'] = array_slice($job['log'], -200);
}

// =====================================================================
// CHUNK WORKER — does exactly ONE task (one city/query/sub-area)
// =====================================================================

function run_one_chunk(array &$job, string $apiKey, string $jsonDir): void {
    if ($job['cursor'] >= $job['total']) {
        $job['status'] = 'done';
        return;
    }

    $task = $job['tasks'][$job['cursor']];
    $city = $task['cityRef'];
    $area = $task['area'];
    $q    = $task['q'];
    $spec = $task['spec'];

    $reqs = 0;
    $newRows = [];

    // Step 1 — Text Search this sub-area
    $places = fetch_text_search($apiKey, $q, $area['lat'], $area['lng'], $area['radius'], $reqs);

    // Load existing city JSON for dedup against rows already saved
    $cityPath = $jsonDir . '/' . slugify($city['name']) . '.json';
    $existing = [];
    $existingIds = [];
    if (is_file($cityPath)) {
        $raw = json_decode((string) file_get_contents($cityPath), true);
        if (isset($raw['doctors']) && is_array($raw['doctors'])) {
            $existing = $raw['doctors'];
            foreach ($existing as $d) {
                if (!empty($d['place_id'])) $existingIds[$d['place_id']] = true;
            }
        }
    }

    foreach ($places as $place) {
        $pid = $place['place_id'] ?? null;
        if (!$pid || isset($existingIds[$pid])) continue;

        $types = $place['types'] ?? [];
        $biz = $place['business_status'] ?? 'OPERATIONAL';
        if (str_starts_with((string) $biz, 'CLOSED_')) { $job['totals']['skipped_closed']++; continue; }
        if (!places_type_acceptable($types))           { $job['totals']['skipped_type']++; continue; }

        $details = fetch_place_details($apiKey, $pid, $reqs);
        if (!$details) { $job['totals']['detail_fail']++; continue; }

        if (!empty($details['permanently_closed'])) { $job['totals']['skipped_closed']++; continue; }
        $detailStatus = $details['business_status'] ?? '';
        if (str_starts_with((string) $detailStatus, 'CLOSED_')) { $job['totals']['skipped_closed']++; continue; }

        $row = format_doctor($place, $details, $city, $spec);
        $verdict = quality_check($row, $city);
        if (str_starts_with($verdict, 'SKIP:')) {
            $reason = substr($verdict, 5);
            if ($reason === 'no_address')    $job['totals']['skipped_addr']++;
            elseif ($reason === 'city_mismatch') $job['totals']['skipped_city']++;
            elseif ($reason === 'closed')    $job['totals']['skipped_closed']++;
            continue;
        }
        if ($verdict !== '') {
            $row['dropped_reason'] = $verdict;
            $job['totals']['flagged']++;
        }
        $newRows[] = $row;
        $existingIds[$pid] = true; // also dedup within this chunk
    }

    // Save merged JSON for the city
    if (!empty($newRows)) {
        $merged = dedupe_by_place_id(array_merge($existing, $newRows));
        file_put_contents($cityPath, json_encode([
            'city'       => $city['name'],
            'state'      => $city['state'],
            'country'    => 'IN',
            'count'      => count($merged),
            'updated_at' => date('Y-m-d H:i:s'),
            'doctors'    => $merged,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    $found = count($newRows);
    $job['totals']['doctors_new'] += $found;
    $job['totals']['requests']    += $reqs;
    $job['cursor']++;
    $job['updated_at'] = date('Y-m-d H:i:s');

    // ---- Adaptive grid expansion ----
    // If this was the city-wide probe AND we hit Google's 60-result ceiling,
    // the area definitely has more doctors hidden behind the cap. Expand
    // the search into the sub-area grid for THIS (city, query) pair only.
    $kind = $task['kind'] ?? 'grid';
    $hitCap = count($places) >= 60;   // 3 pages × 20 = Google's hard cap
    if ($kind === 'probe' && $hitCap) {
        $subAreas = generate_sub_areas($city['lat'], $city['lng'], $city['radius']);
        $newTasks = [];
        foreach ($subAreas as $aIdx => $area) {
            $newTasks[] = [
                'kind'    => 'grid',
                'city'    => $city['name'],
                'state'   => $city['state'],
                'q'       => $q,
                'spec'    => $spec,
                'area'    => $area,
                'qIdx'    => $task['qIdx'],
                'aIdx'    => $aIdx,
                'qCount'  => $task['qCount'],
                'aCount'  => count($subAreas),
                'cityRef' => $city,
            ];
        }
        // Splice the new tasks right after the current cursor so they run next
        // (keeps logs grouped by (city, specialty) instead of jumping around).
        array_splice($job['tasks'], $job['cursor'], 0, $newTasks);
        $job['total'] += count($newTasks);
        job_append_log($job, sprintf(
            '%s · %s · probe hit cap (60) → expanding into %d sub-areas',
            $city['name'], $q, count($subAreas)
        ));
    }

    $areaLabel = $kind === 'probe'
        ? sprintf('probe (city-wide, r=%.1fkm)', $area['radius'] / 1000)
        : sprintf('area %d/%d', $task['aIdx'] + 1, $task['aCount']);
    job_append_log($job, sprintf(
        '%s · %s · %s → +%d doctors (%d req%s)',
        $city['name'], $q, $areaLabel, $found, $reqs,
        ($kind === 'probe' && !$hitCap) ? ', complete' : ''
    ));

    if ($job['cursor'] >= $job['total']) $job['status'] = 'done';
}

// =====================================================================
// ROUTER
// =====================================================================

$action = $_GET['action'] ?? null;
$jobId  = isset($_GET['job']) ? (string) $_GET['job'] : null;

// ----- AJAX: do ONE chunk -----
if ($action === 'step' && $jobId !== null) {
    header('Content-Type: application/json; charset=utf-8');
    @set_time_limit(60);  // each chunk should comfortably finish in < 30s
    if ($apiKey === '') { echo json_encode(['error' => 'no_api_key']); exit; }

    $job = job_load($jobId);
    if (!$job) { http_response_code(404); echo json_encode(['error' => 'job_not_found']); exit; }
    if ($job['status'] === 'paused') { echo json_encode(_job_snapshot($job)); exit; }
    if ($job['status'] === 'done')   { echo json_encode(_job_snapshot($job)); exit; }

    try {
        run_one_chunk($job, $apiKey, $JSON_DIR);
        job_save($job);
    } catch (Throwable $e) {
        $job['status'] = 'error';
        job_append_log($job, 'ERROR: ' . $e->getMessage());
        job_save($job);
    }
    echo json_encode(_job_snapshot($job));
    exit;
}

// ----- AJAX: status only -----
if ($action === 'status' && $jobId !== null) {
    header('Content-Type: application/json; charset=utf-8');
    $job = job_load($jobId);
    if (!$job) { http_response_code(404); echo json_encode(['error' => 'job_not_found']); exit; }
    echo json_encode(_job_snapshot($job));
    exit;
}

// ----- AJAX: pause / resume / cancel -----
if ($action === 'pause' && $jobId !== null && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    $job = job_load($jobId);
    if (!$job) { http_response_code(404); echo json_encode(['error' => 'job_not_found']); exit; }
    if ($job['status'] === 'running') $job['status'] = 'paused';
    elseif ($job['status'] === 'paused') $job['status'] = 'running';
    job_save($job);
    echo json_encode(_job_snapshot($job));
    exit;
}

function _job_snapshot(array $job): array {
    // Strip the giant tasks[] array from the snapshot so the AJAX payload stays small.
    $cur = $job['cursor'];
    $total = $job['total'];
    $currentTask = ($cur < $total) ? $job['tasks'][$cur] : null;
    return [
        'id'         => $job['id'],
        'status'     => $job['status'],
        'cursor'     => $cur,
        'total'      => $total,
        'percent'    => $total > 0 ? round($cur / $total * 100, 1) : 100,
        'totals'     => $job['totals'],
        'log'        => array_slice($job['log'], -30),  // last 30 lines only
        'current'    => $currentTask ? [
            'city'  => $currentTask['city'],
            'state' => $currentTask['state'],
            'q'     => $currentTask['q'],
            'qIdx'  => $currentTask['qIdx'] + 1,
            'qCount'=> $currentTask['qCount'],
            'aIdx'  => $currentTask['aIdx'] + 1,
            'aCount'=> $currentTask['aCount'],
            'kind'  => $currentTask['kind'] ?? 'grid',
        ] : null,
        'updated_at' => $job['updated_at'],
        'cities'     => $job['cities'],
    ];
}

// ----- POST: create job from picker -----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cities'])) {
    if ($apiKey === '') {
        $err = 'GOOGLE_MAPS_API_KEY missing in fetch_doctor/.env';
    } else {
        $picked = [];
        foreach ((array) $_POST['cities'] as $name) {
            $c = find_city($STATES, (string) $name);
            if ($c) $picked[] = $c;
        }
        if (empty($picked)) {
            $err = 'No valid cities selected.';
        } else {
            $newJobId = job_create($picked, $QUERIES);
            header('Location: ?job=' . $newJobId);
            exit;
        }
    }
}

// ----- POST: name-by-name lookup -----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['names'])) {
    if ($apiKey === '') {
        $err = 'GOOGLE_MAPS_API_KEY missing in fetch_doctor/.env';
    } else {
        @set_time_limit(120);
        header('Content-Type: text/plain; charset=utf-8');
        echo "fetch_doctor — name lookup\n" . str_repeat('=', 60) . "\n\n";

        $defaultCity = trim((string) ($_POST['names_city'] ?? 'Ahmedabad'));
        $city = find_city($STATES, $defaultCity) ?? find_city($STATES, 'Ahmedabad') ?? ['name' => $defaultCity, 'state' => '', 'lat' => 23.0225, 'lng' => 72.5714];

        $rawNames = preg_split('/\r?\n/', (string) $_POST['names']);
        $names = array_filter(array_map('trim', $rawNames));

        $jsonDir = __DIR__ . '/json';
        $path = $jsonDir . '/_named-lookups.json';
        $existing = [];
        $existingIds = [];
        if (is_file($path)) {
            $raw = json_decode((string) file_get_contents($path), true);
            if (isset($raw['doctors']) && is_array($raw['doctors'])) {
                $existing = $raw['doctors'];
                foreach ($existing as $d) {
                    if (!empty($d['place_id'])) $existingIds[$d['place_id']] = true;
                }
            }
        }

        $reqCount = 0;
        $newRows  = [];

        foreach ($names as $rawName) {
            $query = $rawName;
            if (stripos($query, $city['name']) === false) $query .= ' ' . $city['name'];
            echo "🔍 {$query}\n";

            $places = fetch_text_search($apiKey, $query, $city['lat'], $city['lng'], 30000, $reqCount);
            if (empty($places)) {
                echo "   ⨯ no result\n";
                continue;
            }

            // Take only the top result for a name lookup.
            $place = $places[0];
            $pid = $place['place_id'] ?? null;
            if (!$pid) { echo "   ⨯ no place_id\n"; continue; }
            if (isset($existingIds[$pid])) { echo "   = already in file\n"; continue; }

            $biz = $place['business_status'] ?? 'OPERATIONAL';
            if (str_starts_with((string) $biz, 'CLOSED_')) { echo "   ⨯ closed\n"; continue; }

            $details = fetch_place_details($apiKey, $pid, $reqCount);
            if (!$details) { echo "   ⨯ details failed\n"; continue; }

            // Guess specialty from the input or types
            $spec = 'gp';
            $hint = strtolower($rawName . ' ' . implode(' ', (array) ($details['types'] ?? [])));
            $map = [
                'dentist' => 'dental', 'dental' => 'dental',
                'homeo' => 'homeo', 'bhms' => 'homeo',
                'pediatric' => 'peds', 'paediatric' => 'peds',
                'dermatolog' => 'derma', 'skin' => 'derma',
                'gyne' => 'gyno', 'obstet' => 'gyno',
                'cardio' => 'cardio',  'heart' => 'cardio',
                'ortho' => 'ortho',
                'physio' => 'physio',
                'ent ' => 'ent', 'ear nose' => 'ent',
                'eye' => 'eye', 'ophthal' => 'eye',
                'neuro' => 'neuro',
                'urolog' => 'urologist',
                'ayurved' => 'ayurveda',
                'psychiatr' => 'psychiatrist',
                'psycholog' => 'psychologist',
                'nephrolog' => 'nephrology',
                'oncolog' => 'oncology',
                'pulmono' => 'pulmonology',
            ];
            foreach ($map as $needle => $code) {
                if (str_contains($hint, $needle)) { $spec = $code; break; }
            }

            $row = format_doctor($place, $details, $city, $spec);
            // Manually-added doctors skip the quality_check city filter so they
            // always survive (we trust the operator to know who they're adding).
            $newRows[] = $row;
            $existingIds[$pid] = true;
            echo "   ✓ {$row['name']} ({$spec}, {$row['reviews']} reviews)\n";
            usleep(150_000);
        }

        if (!empty($newRows)) {
            $merged = dedupe_by_place_id(array_merge($existing, $newRows));
            file_put_contents($path, json_encode([
                'city'       => '(named lookups)',
                'state'      => '',
                'country'    => 'IN',
                'count'      => count($merged),
                'updated_at' => date('Y-m-d H:i:s'),
                'doctors'    => $merged,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        }

        $cost = round(($reqCount / 1000) * 24, 2);
        echo "\n" . str_repeat('=', 60) . "\n";
        echo "Added: " . count($newRows) . " · API requests: {$reqCount} · est. cost: \${$cost}\n";
        echo "\nFile: json/_named-lookups.json (will be picked up by insert_db.php)\n";
        echo "Next: open insert_db.php and import _named-lookups.json\n";
        exit;
    }
}

// ----- View: progress UI -----
if ($jobId !== null) {
    $job = job_load($jobId);
    if (!$job) {
        echo '<p style="font-family:sans-serif;padding:40px;">Job not found.</p>';
        exit;
    }
    render_progress_ui($job);
    exit;
}

// ----- Default: picker UI -----
render_picker_ui($STATES, $QUERIES, $apiKey, $JSON_DIR, $err ?? null);

// =====================================================================
// VIEWS
// =====================================================================

function render_picker_ui(array $STATES, array $QUERIES, string $apiKey, string $jsonDir, ?string $err): void {
    $keyMissing = ($apiKey === '');
    $existingJson = [];
    foreach (glob($jsonDir . '/*.json') ?: [] as $f) {
        $raw = json_decode((string) file_get_contents($f), true);
        $existingJson[basename($f)] = (int) ($raw['count'] ?? 0);
    }
    ?><!doctype html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Fetch Doctors — eClinicPro</title>
<style>
:root { --teal:#0F9B6E; --line:rgba(0,0,0,0.08); --bg:#f8fafc; --mute:#64748b; --ink:#0f172a; }
*{box-sizing:border-box}body{font:14px/1.5 -apple-system,BlinkMacSystemFont,'Segoe UI',system-ui,sans-serif;margin:0;background:var(--bg);color:var(--ink)}
.wrap{max-width:1000px;margin:0 auto;padding:32px 20px 80px}
h1{font-size:24px;font-weight:600;margin:0 0 8px}
.lede{color:var(--mute);margin:0 0 24px}
.warn,.err{padding:12px 16px;border-radius:8px;margin-bottom:18px;font-size:13px}
.warn{background:#fff7ed;border:1px solid #fed7aa;color:#9a3412}
.err{background:#fef2f2;border:1px solid #fecaca;color:#b91c1c}
.toolbar{display:flex;gap:10px;margin-bottom:16px;flex-wrap:wrap;align-items:center}
.toolbar button,.toolbar a{font:inherit;background:#fff;border:1px solid var(--line);padding:7px 14px;border-radius:8px;cursor:pointer;color:var(--ink);text-decoration:none}
.toolbar button:hover,.toolbar a:hover{border-color:var(--teal);color:var(--teal)}
.state{background:#fff;border:1px solid var(--line);border-radius:12px;padding:16px 18px;margin-bottom:12px}
.state-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:10px}
.state-name{font-weight:600;font-size:15px}
.state-action{font-size:12px;color:var(--teal);cursor:pointer;background:none;border:0;padding:0}
.cities{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:8px}
.city{display:flex;align-items:center;gap:8px;padding:8px 12px;border:1px solid var(--line);border-radius:8px;cursor:pointer;background:#fff;font-size:13px}
.city:hover{border-color:var(--teal)}
.city input{margin:0;cursor:pointer}
.city.has-data{background:#ecfdf5;border-color:#a7f3d0}
.city-tag{margin-left:auto;font-size:11px;color:var(--mute)}
.submit{position:sticky;bottom:16px;margin-top:24px;background:var(--ink);color:#fff;padding:14px 18px;border:0;border-radius:12px;font-size:14px;font-weight:500;cursor:pointer;width:100%;box-shadow:0 8px 30px rgba(0,0,0,0.15)}
.submit:hover{background:var(--teal)}
.summary{display:flex;gap:12px;flex-wrap:wrap;font-size:12px;color:var(--mute);margin:8px 0 16px}
.summary strong{color:var(--ink)}
.note{font-size:12px;color:var(--mute);margin-top:16px;padding:12px;background:#fff;border-radius:8px;border-left:3px solid var(--teal)}
@media(max-width:600px){.cities{grid-template-columns:1fr 1fr}}
</style></head><body><div class="wrap">

<h1>🩺 Fetch Doctors</h1>
<p class="lede">Pick cities below. Clicking <strong>Start</strong> opens a live progress page that fetches each city in tiny chunks — no timeouts.</p>

<?php if ($keyMissing): ?>
    <div class="warn">⚠ <strong>GOOGLE_MAPS_API_KEY missing.</strong> Create <code>fetch_doctor/.env</code>: <code>GOOGLE_MAPS_API_KEY=your_key</code></div>
<?php endif; ?>
<?php if ($err): ?><div class="err">❌ <?= htmlspecialchars($err) ?></div><?php endif; ?>

<div class="summary">
    <span>Cities available: <strong><?= array_sum(array_map('count', $STATES)) ?></strong></span>
    <span>States: <strong><?= count($STATES) ?></strong></span>
    <span>Specialty queries per city: <strong><?= count($QUERIES) ?></strong></span>
    <span>JSON files saved: <strong><?= count($existingJson) ?></strong></span>
</div>

<form method="post">
<div class="toolbar">
    <button type="button" onclick="toggleAll(true)">Select all</button>
    <button type="button" onclick="toggleAll(false)">Clear all</button>
    <a href="insert_db.php">→ Go to importer</a>
</div>

<?php foreach ($STATES as $stateName => $cities): ?>
<div class="state">
    <div class="state-head">
        <span class="state-name"><?= htmlspecialchars($stateName) ?> <small style="color:var(--mute);font-weight:400;">(<?= count($cities) ?>)</small></span>
        <button type="button" class="state-action" onclick="toggleState(this)">toggle</button>
    </div>
    <div class="cities">
        <?php foreach ($cities as $c):
            $slug = slugify($c['name']);
            $jsonFile = "{$slug}.json";
            $hasData = isset($existingJson[$jsonFile]);
        ?>
        <label class="city <?= $hasData ? 'has-data' : '' ?>">
            <input type="checkbox" name="cities[]" value="<?= htmlspecialchars($c['name']) ?>">
            <span><?= htmlspecialchars($c['name']) ?></span>
            <?php if ($hasData): ?><span class="city-tag"><?= $existingJson[$jsonFile] ?> saved</span><?php endif; ?>
        </label>
        <?php endforeach; ?>
    </div>
</div>
<?php endforeach; ?>

<button type="submit" class="submit">▶ Start fetch &nbsp;·&nbsp; <span id="count">0</span> selected</button>

<div class="note">
    Each city = <strong><?= count($QUERIES) ?> queries × 25 sub-areas = ~<?= count($QUERIES) * 25 ?> chunks</strong>. Each chunk runs as its own AJAX call (no PHP timeouts). Per-city cost: ~$15-$30 for big metros, ~$5-$10 for smaller cities.
</div>
</form>

<!-- ===================== NAME-BY-NAME LOOKUP ===================== -->
<details class="state" style="margin-top: 28px;">
    <summary style="cursor: pointer; font-weight: 600; font-size: 15px;">
        🎯 Add specific doctors by name <small style="color:var(--mute);font-weight:400;">— for known doctors the area scan missed</small>
    </summary>
    <form method="post" style="margin-top: 14px;">
        <p style="font-size: 12.5px; color: var(--mute); margin: 0 0 12px;">
            Paste one doctor or clinic name per line. Each name is searched via Google Places and added to <code>json/_named-lookups.json</code>. Costs ~$0.024 per name. Useful for filling gaps left by the area scan.
        </p>
        <label style="display:block;font-size:12px;font-weight:500;color:var(--ink);margin-bottom:4px;">Default city <small style="color:var(--mute);font-weight:400;">(used if the name doesn't already include one)</small></label>
        <input type="text" name="names_city" value="Ahmedabad" style="width: 240px; padding:7px 10px; border:1px solid var(--line); border-radius:6px; font-size:13px; margin-bottom:10px;">

        <label style="display:block;font-size:12px;font-weight:500;color:var(--ink);margin-bottom:4px;">Doctor / clinic names (one per line)</label>
        <textarea name="names" rows="6" style="width:100%; padding:10px 12px; border:1px solid var(--line); border-radius:8px; font-size:13px; font-family:inherit;"
placeholder="Dr Mitesh Prajapati Gota
Dr Jayesh Patel Gota
Dr. Feelgood's Clinic Ahmedabad"></textarea>

        <button type="submit" style="margin-top:10px; padding:10px 16px; background:var(--ink); color:#fff; border:0; border-radius:8px; font-size:13.5px; font-weight:500; cursor:pointer;">
            🔍 Fetch these names
        </button>
    </form>
</details>

</div><script>
function toggleAll(on){document.querySelectorAll('input[name="cities[]"]').forEach(i=>i.checked=on);updateCount()}
function toggleState(b){const x=b.closest('.state').querySelectorAll('input[name="cities[]"]'),off=[...x].some(b=>!b.checked);x.forEach(b=>b.checked=off);updateCount()}
function updateCount(){document.getElementById('count').textContent=document.querySelectorAll('input[name="cities[]"]:checked').length}
document.addEventListener('change',e=>{if(e.target.matches('input[name="cities[]"]'))updateCount()})
</script></body></html><?php
}

function render_progress_ui(array $job): void {
    $jobId = $job['id'];
    ?><!doctype html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Fetching… — eClinicPro</title>
<style>
:root { --teal:#0F9B6E; --line:rgba(0,0,0,0.08); --bg:#f8fafc; --mute:#64748b; --ink:#0f172a; }
*{box-sizing:border-box}body{font:14px/1.5 -apple-system,BlinkMacSystemFont,'Segoe UI',system-ui,sans-serif;margin:0;background:var(--bg);color:var(--ink)}
.wrap{max-width:880px;margin:0 auto;padding:32px 20px 80px}
h1{font-size:22px;font-weight:600;margin:0 0 4px}
.sub{color:var(--mute);font-size:13px;margin-bottom:24px}
.bar-wrap{background:#fff;border:1px solid var(--line);border-radius:12px;padding:18px 20px;margin-bottom:16px}
.bar-row{display:flex;justify-content:space-between;align-items:baseline;margin-bottom:10px}
.bar-num{font-size:32px;font-weight:300;letter-spacing:-1px}
.bar-num small{font-size:14px;color:var(--mute);font-weight:400;letter-spacing:0}
.bar-pct{font-size:13px;color:var(--mute)}
.bar{background:var(--bg);border-radius:8px;height:8px;overflow:hidden}
.bar-fill{background:linear-gradient(90deg,var(--teal),#34d399);height:100%;transition:width .25s}
.current{margin-top:14px;padding-top:12px;border-top:1px solid var(--line);font-size:13px;color:var(--mute)}
.current strong{color:var(--ink);font-weight:500}
.stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:10px;margin-bottom:16px}
.stat{background:#fff;border:1px solid var(--line);border-radius:10px;padding:12px 14px}
.stat .v{font-size:22px;font-weight:300;letter-spacing:-0.5px}
.stat .l{font-size:11px;color:var(--mute);text-transform:uppercase;letter-spacing:0.06em;margin-top:2px}
.toolbar{display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap}
.toolbar button,.toolbar a{font:inherit;background:#fff;border:1px solid var(--line);padding:8px 14px;border-radius:8px;cursor:pointer;color:var(--ink);text-decoration:none}
.toolbar .primary{background:var(--ink);color:#fff;border-color:var(--ink)}
.toolbar button:hover{border-color:var(--teal);color:var(--teal)}
.toolbar .primary:hover{background:var(--teal);color:#fff}
.log{background:#0f172a;color:#e2e8f0;border-radius:12px;padding:14px 18px;font:12px/1.55 'JetBrains Mono',ui-monospace,monospace;max-height:380px;overflow-y:auto}
.log .row{padding:1px 0;white-space:pre-wrap}
.log .row:nth-child(odd){opacity:.85}
.done-banner{background:#ecfdf5;border:1px solid #a7f3d0;color:#065f46;padding:18px 20px;border-radius:12px;margin-bottom:16px;font-weight:500}
.err-banner{background:#fef2f2;border:1px solid #fecaca;color:#b91c1c;padding:18px 20px;border-radius:12px;margin-bottom:16px}
.paused{color:#9a3412}
@media(max-width:600px){.bar-num{font-size:26px}.stat .v{font-size:18px}}
</style></head><body>

<div class="wrap" x-data="progress(<?= htmlspecialchars(json_encode(['jobId' => $jobId]), ENT_QUOTES) ?>)" x-init="init()">
    <h1>🩺 Fetching doctors</h1>
    <p class="sub">
        Cities: <strong><?= htmlspecialchars(implode(', ', $job['cities'])) ?></strong>
        · Job <code><?= htmlspecialchars($jobId) ?></code>
    </p>

    <template x-if="s.status === 'done'">
        <div class="done-banner">
            ✅ All done. <span x-text="s.totals.doctors_new"></span> new doctors saved across <span x-text="s.cities.length"></span> cit<span x-text="s.cities.length === 1 ? 'y' : 'ies'"></span>.
            Next: <a href="insert_db.php" style="color:#065f46;font-weight:600;">→ Import to database</a>
        </div>
    </template>
    <template x-if="s.status === 'error'">
        <div class="err-banner">❌ Job ended with an error. See log below.</div>
    </template>

    <div class="bar-wrap">
        <div class="bar-row">
            <div class="bar-num"><span x-text="s.cursor"></span><small> / <span x-text="s.total"></span> chunks</small></div>
            <div class="bar-pct">
                <span x-text="s.percent"></span>%
                <template x-if="s.status === 'paused'"><span class="paused"> · paused</span></template>
            </div>
        </div>
        <div class="bar"><div class="bar-fill" :style="'width:' + s.percent + '%'"></div></div>
        <template x-if="s.current">
            <div class="current">
                Now: <strong x-text="s.current.city"></strong> ·
                <strong x-text="s.current.q"></strong>
                <span x-show="s.current.kind === 'probe'">(city-wide probe, query <span x-text="s.current.qIdx"></span>/<span x-text="s.current.qCount"></span>)</span>
                <span x-show="s.current.kind !== 'probe'">(query <span x-text="s.current.qIdx"></span>/<span x-text="s.current.qCount"></span>,
                area <span x-text="s.current.aIdx"></span>/<span x-text="s.current.aCount"></span>)</span>
            </div>
        </template>
    </div>

    <div class="stats">
        <div class="stat"><div class="v" x-text="s.totals.doctors_new.toLocaleString()"></div><div class="l">Doctors saved</div></div>
        <div class="stat"><div class="v" x-text="s.totals.requests.toLocaleString()"></div><div class="l">API requests</div></div>
        <div class="stat"><div class="v" x-text="'$' + (s.totals.requests * 0.024).toFixed(2)"></div><div class="l">Est. cost</div></div>
        <div class="stat"><div class="v" x-text="(s.totals.skipped_closed + s.totals.skipped_type + s.totals.skipped_addr + s.totals.skipped_city).toLocaleString()"></div><div class="l">Filtered out</div></div>
        <div class="stat"><div class="v" x-text="s.totals.flagged.toLocaleString()"></div><div class="l">Flagged low-quality</div></div>
    </div>

    <div class="toolbar">
        <template x-if="s.status === 'running' || s.status === 'paused'">
            <button type="button" @click="togglePause()" x-text="s.status === 'paused' ? '▶ Resume' : '⏸ Pause'"></button>
        </template>
        <template x-if="s.status === 'done'">
            <a href="insert_db.php" class="primary">→ Import to database</a>
        </template>
        <a href="index.php">← New job</a>
    </div>

    <div class="log">
        <template x-for="line in s.log" :key="line">
            <div class="row" x-text="line"></div>
        </template>
        <template x-if="s.log.length === 0">
            <div class="row" style="opacity:.6;">Waiting for first chunk to complete…</div>
        </template>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
<script>
function progress(cfg){
    return {
        jobId: cfg.jobId,
        s: { status:'running', cursor:0, total:1, percent:0, totals:{doctors_new:0,requests:0,skipped_closed:0,skipped_type:0,skipped_addr:0,skipped_city:0,flagged:0,detail_fail:0}, log:[], current:null, cities:[], updated_at:'' },
        running: false,
        init(){
            // Initial status load, then loop.
            this.fetchStatus().then(()=> this.loop());
        },
        async fetchStatus(){
            try {
                const r = await fetch('?action=status&job=' + this.jobId);
                if (!r.ok) return;
                this.s = await r.json();
            } catch(e) {}
        },
        async loop(){
            if (this.running) return;
            this.running = true;
            while (this.s.status === 'running') {
                try {
                    const r = await fetch('?action=step&job=' + this.jobId);
                    if (!r.ok) { this.s.status = 'error'; break; }
                    this.s = await r.json();
                } catch(e) {
                    // Network blip: pause briefly and continue.
                    await this.sleep(2000);
                }
                await this.sleep(300); // tiny gap so DB write + UI repaint happen
            }
            this.running = false;
        },
        async togglePause(){
            const r = await fetch('?action=pause&job=' + this.jobId, { method:'POST' });
            this.s = await r.json();
            if (this.s.status === 'running' && !this.running) this.loop();
        },
        sleep(ms){ return new Promise(r => setTimeout(r, ms)); },
    };
}
</script>
</body></html><?php
}
