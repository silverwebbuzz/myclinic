-- ============================================================================
-- All Indian states / UTs (+ major cities) for "Listed on eClinicPro".
--
-- 2026_07_10_directory_states_cities.sql only seeded Gujarat + Maharashtra,
-- so the doctor listing form's State picker showed just those two. This adds
-- all 28 states + 8 UTs and a starter city list per state (cities/coords
-- from the fetch_doctor catalog; capitals added for the states it lacks).
--
-- Requires 2026_07_10_directory_states_cities.sql (directory_states table and
-- directory_cities.state_id / is_active / sort_order columns).
--
-- directory_cities may use a different collation than these seed tables
-- (e.g. utf8mb4_general_ci on production), so comparisons against it pin
-- the collation explicitly to avoid "Illegal mix of collations".
--
-- Safe to re-run: existing states/cities (matched by slug) are left alone,
-- including anything an admin renamed, reordered or deactivated.
--
-- Deploy:
--   mysql -u USER -p DB < app/database/patches/2026_10_28_directory_all_india_states.sql
-- ============================================================================

DROP TEMPORARY TABLE IF EXISTS `_seed_states`;
CREATE TEMPORARY TABLE `_seed_states` (
  `name` VARCHAR(80) NOT NULL,
  `slug` VARCHAR(80) NOT NULL,
  `sort_order` INT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TEMPORARY TABLE IF EXISTS `_seed_cities`;
CREATE TEMPORARY TABLE `_seed_cities` (
  `state_slug` VARCHAR(80) NOT NULL,
  `name` VARCHAR(120) NOT NULL,
  `slug` VARCHAR(120) NOT NULL,
  `lat` DECIMAL(10,8) NOT NULL,
  `lng` DECIMAL(11,8) NOT NULL,
  `sort_order` INT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 1) Seed data ----------------------------------------------------------------
-- Gujarat / Maharashtra keep their pinned sort order; the rest sort by name.
INSERT INTO `_seed_states` (`name`, `slug`, `sort_order`) VALUES
  ('Andaman and Nicobar Islands', 'andaman-and-nicobar-islands', 100),
  ('Andhra Pradesh', 'andhra-pradesh', 100),
  ('Arunachal Pradesh', 'arunachal-pradesh', 100),
  ('Assam', 'assam', 100),
  ('Bihar', 'bihar', 100),
  ('Chandigarh', 'chandigarh', 100),
  ('Chhattisgarh', 'chhattisgarh', 100),
  ('Dadra and Nagar Haveli and Daman and Diu', 'dadra-and-nagar-haveli-and-daman-and-diu', 100),
  ('Delhi', 'delhi', 100),
  ('Goa', 'goa', 100),
  ('Gujarat', 'gujarat', 10),
  ('Haryana', 'haryana', 100),
  ('Himachal Pradesh', 'himachal-pradesh', 100),
  ('Jammu and Kashmir', 'jammu-and-kashmir', 100),
  ('Jharkhand', 'jharkhand', 100),
  ('Karnataka', 'karnataka', 100),
  ('Kerala', 'kerala', 100),
  ('Ladakh', 'ladakh', 100),
  ('Lakshadweep', 'lakshadweep', 100),
  ('Madhya Pradesh', 'madhya-pradesh', 100),
  ('Maharashtra', 'maharashtra', 20),
  ('Manipur', 'manipur', 100),
  ('Meghalaya', 'meghalaya', 100),
  ('Mizoram', 'mizoram', 100),
  ('Nagaland', 'nagaland', 100),
  ('Odisha', 'odisha', 100),
  ('Puducherry', 'puducherry', 100),
  ('Punjab', 'punjab', 100),
  ('Rajasthan', 'rajasthan', 100),
  ('Sikkim', 'sikkim', 100),
  ('Tamil Nadu', 'tamil-nadu', 100),
  ('Telangana', 'telangana', 100),
  ('Tripura', 'tripura', 100),
  ('Uttar Pradesh', 'uttar-pradesh', 100),
  ('Uttarakhand', 'uttarakhand', 100),
  ('West Bengal', 'west-bengal', 100);

INSERT INTO `_seed_cities` (`state_slug`, `name`, `slug`, `lat`, `lng`, `sort_order`) VALUES
  ('andaman-and-nicobar-islands', 'Port Blair', 'port-blair', 11.62340000, 92.72650000, 10),
  ('andhra-pradesh', 'Visakhapatnam', 'visakhapatnam', 17.68680000, 83.21850000, 10),
  ('andhra-pradesh', 'Vijayawada', 'vijayawada', 16.50620000, 80.64800000, 20),
  ('andhra-pradesh', 'Guntur', 'guntur', 16.30670000, 80.43650000, 30),
  ('andhra-pradesh', 'Tirupati', 'tirupati', 13.62880000, 79.41920000, 40),
  ('andhra-pradesh', 'Nellore', 'nellore', 14.44260000, 79.98650000, 50),
  ('andhra-pradesh', 'Kurnool', 'kurnool', 15.82810000, 78.03730000, 60),
  ('andhra-pradesh', 'Rajahmundry', 'rajahmundry', 17.00050000, 81.80400000, 70),
  ('andhra-pradesh', 'Kakinada', 'kakinada', 16.98910000, 82.24750000, 80),
  ('andhra-pradesh', 'Anantapur', 'anantapur', 14.68190000, 77.60060000, 90),
  ('arunachal-pradesh', 'Itanagar', 'itanagar', 27.08440000, 93.60530000, 10),
  ('assam', 'Guwahati', 'guwahati', 26.14450000, 91.73620000, 10),
  ('assam', 'Silchar', 'silchar', 24.83330000, 92.77890000, 20),
  ('assam', 'Dibrugarh', 'dibrugarh', 27.47280000, 94.91200000, 30),
  ('assam', 'Jorhat', 'jorhat', 26.75090000, 94.20370000, 40),
  ('assam', 'Tezpur', 'tezpur', 26.65290000, 92.79260000, 50),
  ('bihar', 'Patna', 'patna', 25.59410000, 85.13760000, 10),
  ('bihar', 'Gaya', 'gaya', 24.79140000, 85.00020000, 20),
  ('bihar', 'Bhagalpur', 'bhagalpur', 25.24250000, 86.98420000, 30),
  ('bihar', 'Muzaffarpur', 'muzaffarpur', 26.12090000, 85.36470000, 40),
  ('bihar', 'Darbhanga', 'darbhanga', 26.15420000, 85.89180000, 50),
  ('bihar', 'Purnia', 'purnia', 25.77710000, 87.47530000, 60),
  ('chandigarh', 'Chandigarh', 'chandigarh', 30.73330000, 76.77940000, 10),
  ('chhattisgarh', 'Raipur', 'raipur', 21.25140000, 81.62960000, 10),
  ('chhattisgarh', 'Bhilai', 'bhilai', 21.19380000, 81.35090000, 20),
  ('chhattisgarh', 'Bilaspur', 'bilaspur', 22.07970000, 82.14090000, 30),
  ('chhattisgarh', 'Korba', 'korba', 22.35950000, 82.75010000, 40),
  ('dadra-and-nagar-haveli-and-daman-and-diu', 'Silvassa', 'silvassa', 20.27660000, 73.00830000, 10),
  ('dadra-and-nagar-haveli-and-daman-and-diu', 'Daman', 'daman', 20.39740000, 72.83280000, 20),
  ('dadra-and-nagar-haveli-and-daman-and-diu', 'Diu', 'diu', 20.71440000, 70.98740000, 30),
  ('delhi', 'Delhi', 'delhi', 28.70410000, 77.10250000, 10),
  ('goa', 'Panaji', 'panaji', 15.49090000, 73.82780000, 10),
  ('goa', 'Margao', 'margao', 15.28320000, 73.98620000, 20),
  ('goa', 'Vasco da Gama', 'vasco-da-gama', 15.39580000, 73.81570000, 30),
  ('gujarat', 'Ahmedabad', 'ahmedabad', 23.02250000, 72.57140000, 10),
  ('gujarat', 'Surat', 'surat', 21.17020000, 72.83110000, 20),
  ('gujarat', 'Vadodara', 'vadodara', 22.30720000, 73.18120000, 30),
  ('gujarat', 'Rajkot', 'rajkot', 22.30390000, 70.80220000, 40),
  ('gujarat', 'Bhavnagar', 'bhavnagar', 21.76450000, 72.15190000, 50),
  ('gujarat', 'Gandhinagar', 'gandhinagar', 23.21560000, 72.63690000, 60),
  ('gujarat', 'Jamnagar', 'jamnagar', 22.47070000, 70.05770000, 70),
  ('gujarat', 'Junagadh', 'junagadh', 21.52220000, 70.45790000, 80),
  ('gujarat', 'Surendranagar', 'surendranagar', 22.71960000, 71.63690000, 90),
  ('gujarat', 'Anand', 'anand', 22.56450000, 72.92890000, 100),
  ('gujarat', 'Bharuch', 'bharuch', 21.70510000, 72.99590000, 110),
  ('gujarat', 'Mehsana', 'mehsana', 23.58790000, 72.36930000, 120),
  ('gujarat', 'Nadiad', 'nadiad', 22.69170000, 72.86340000, 130),
  ('gujarat', 'Navsari', 'navsari', 20.94670000, 72.95200000, 140),
  ('gujarat', 'Morbi', 'morbi', 22.82520000, 70.84230000, 150),
  ('gujarat', 'Vapi', 'vapi', 20.38930000, 72.91060000, 160),
  ('gujarat', 'Valsad', 'valsad', 20.59920000, 72.93420000, 170),
  ('gujarat', 'Porbandar', 'porbandar', 21.64170000, 69.62930000, 180),
  ('gujarat', 'Gandhidham', 'gandhidham', 23.07530000, 70.13370000, 190),
  ('gujarat', 'Bhuj', 'bhuj', 23.24200000, 69.66690000, 200),
  ('gujarat', 'Patan', 'patan', 23.84930000, 72.12660000, 210),
  ('gujarat', 'Palanpur', 'palanpur', 24.17220000, 72.43170000, 220),
  ('gujarat', 'Veraval', 'veraval', 20.90770000, 70.36650000, 230),
  ('gujarat', 'Godhra', 'godhra', 22.77880000, 73.61430000, 240),
  ('gujarat', 'Himatnagar', 'himatnagar', 23.59800000, 72.95720000, 250),
  ('haryana', 'Karnal', 'karnal', 29.68570000, 76.99050000, 10),
  ('haryana', 'Panipat', 'panipat', 29.39090000, 76.96350000, 20),
  ('haryana', 'Hisar', 'hisar', 29.14920000, 75.72170000, 30),
  ('haryana', 'Ambala', 'ambala', 30.37820000, 76.77670000, 40),
  ('haryana', 'Rohtak', 'rohtak', 28.89550000, 76.60660000, 50),
  ('haryana', 'Yamunanagar', 'yamunanagar', 30.12900000, 77.26740000, 60),
  ('haryana', 'Gurgaon', 'gurgaon', 28.45950000, 77.02660000, 70),
  ('haryana', 'Faridabad', 'faridabad', 28.40890000, 77.31780000, 80),
  ('haryana', 'Sonipat', 'sonipat', 28.99310000, 77.01510000, 90),
  ('himachal-pradesh', 'Shimla', 'shimla', 31.10480000, 77.17340000, 10),
  ('himachal-pradesh', 'Dharamshala', 'dharamshala', 32.21900000, 76.32340000, 20),
  ('himachal-pradesh', 'Mandi', 'mandi', 31.70810000, 76.93190000, 30),
  ('himachal-pradesh', 'Solan', 'solan', 30.90450000, 77.09670000, 40),
  ('jammu-and-kashmir', 'Jammu', 'jammu', 32.72660000, 74.85700000, 10),
  ('jammu-and-kashmir', 'Srinagar', 'srinagar', 34.08370000, 74.79730000, 20),
  ('jammu-and-kashmir', 'Udhampur', 'udhampur', 32.92390000, 75.14160000, 30),
  ('jharkhand', 'Ranchi', 'ranchi', 23.34410000, 85.30960000, 10),
  ('jharkhand', 'Jamshedpur', 'jamshedpur', 22.80460000, 86.20290000, 20),
  ('jharkhand', 'Dhanbad', 'dhanbad', 23.79570000, 86.43040000, 30),
  ('jharkhand', 'Bokaro', 'bokaro', 23.66930000, 86.15110000, 40),
  ('jharkhand', 'Hazaribagh', 'hazaribagh', 23.99290000, 85.36500000, 50),
  ('karnataka', 'Bangalore', 'bangalore', 12.97160000, 77.59460000, 10),
  ('karnataka', 'Mysore', 'mysore', 12.29580000, 76.63940000, 20),
  ('karnataka', 'Mangalore', 'mangalore', 12.91410000, 74.85600000, 30),
  ('karnataka', 'Hubli', 'hubli', 15.36470000, 75.12400000, 40),
  ('karnataka', 'Belgaum', 'belgaum', 15.84970000, 74.49770000, 50),
  ('karnataka', 'Davanagere', 'davanagere', 14.46440000, 75.92180000, 60),
  ('karnataka', 'Tumkur', 'tumkur', 13.34090000, 77.10100000, 70),
  ('karnataka', 'Shimoga', 'shimoga', 13.92990000, 75.56810000, 80),
  ('karnataka', 'Bellary', 'bellary', 15.13940000, 76.92140000, 90),
  ('karnataka', 'Gulbarga', 'gulbarga', 17.32970000, 76.83430000, 100),
  ('karnataka', 'Udupi', 'udupi', 13.34090000, 74.74210000, 110),
  ('kerala', 'Kochi', 'kochi', 9.93120000, 76.26730000, 10),
  ('kerala', 'Thiruvananthapuram', 'thiruvananthapuram', 8.52410000, 76.93660000, 20),
  ('kerala', 'Kozhikode', 'kozhikode', 11.25880000, 75.78040000, 30),
  ('kerala', 'Thrissur', 'thrissur', 10.52760000, 76.21440000, 40),
  ('kerala', 'Kollam', 'kollam', 8.89320000, 76.61410000, 50),
  ('kerala', 'Kannur', 'kannur', 11.87450000, 75.37040000, 60),
  ('kerala', 'Alappuzha', 'alappuzha', 9.49810000, 76.33880000, 70),
  ('kerala', 'Palakkad', 'palakkad', 10.78670000, 76.65480000, 80),
  ('kerala', 'Kottayam', 'kottayam', 9.59160000, 76.52220000, 90),
  ('ladakh', 'Leh', 'leh', 34.15260000, 77.57710000, 10),
  ('ladakh', 'Kargil', 'kargil', 34.55390000, 76.13490000, 20),
  ('lakshadweep', 'Kavaratti', 'kavaratti', 10.56690000, 72.64200000, 10),
  ('madhya-pradesh', 'Indore', 'indore', 22.71960000, 75.85770000, 10),
  ('madhya-pradesh', 'Bhopal', 'bhopal', 23.25990000, 77.41260000, 20),
  ('madhya-pradesh', 'Jabalpur', 'jabalpur', 23.18150000, 79.98640000, 30),
  ('madhya-pradesh', 'Gwalior', 'gwalior', 26.21830000, 78.18280000, 40),
  ('madhya-pradesh', 'Ujjain', 'ujjain', 23.17650000, 75.78850000, 50),
  ('madhya-pradesh', 'Sagar', 'sagar', 23.83880000, 78.73780000, 60),
  ('madhya-pradesh', 'Ratlam', 'ratlam', 23.33150000, 75.03670000, 70),
  ('madhya-pradesh', 'Rewa', 'rewa', 24.53620000, 81.30370000, 80),
  ('maharashtra', 'Mumbai', 'mumbai', 19.07600000, 72.87770000, 10),
  ('maharashtra', 'Pune', 'pune', 18.52040000, 73.85670000, 20),
  ('maharashtra', 'Nagpur', 'nagpur', 21.14580000, 79.08820000, 30),
  ('maharashtra', 'Nashik', 'nashik', 19.99750000, 73.78980000, 40),
  ('maharashtra', 'Aurangabad', 'aurangabad', 19.87620000, 75.34330000, 50),
  ('maharashtra', 'Thane', 'thane', 19.21830000, 72.97810000, 60),
  ('maharashtra', 'Navi Mumbai', 'navi-mumbai', 19.03300000, 73.02970000, 70),
  ('maharashtra', 'Kolhapur', 'kolhapur', 16.70500000, 74.24330000, 80),
  ('maharashtra', 'Solapur', 'solapur', 17.65990000, 75.90640000, 90),
  ('maharashtra', 'Amravati', 'amravati', 20.93740000, 77.77960000, 100),
  ('maharashtra', 'Akola', 'akola', 20.70020000, 77.00820000, 110),
  ('maharashtra', 'Jalgaon', 'jalgaon', 21.00770000, 75.56260000, 120),
  ('maharashtra', 'Sangli', 'sangli', 16.85240000, 74.58150000, 130),
  ('maharashtra', 'Latur', 'latur', 18.40880000, 76.56040000, 140),
  ('maharashtra', 'Nanded', 'nanded', 19.13830000, 77.32100000, 150),
  ('maharashtra', 'Chandrapur', 'chandrapur', 19.96150000, 79.29610000, 160),
  ('maharashtra', 'Ahmednagar', 'ahmednagar', 19.09480000, 74.74800000, 170),
  ('manipur', 'Imphal', 'imphal', 24.81700000, 93.93680000, 10),
  ('meghalaya', 'Shillong', 'shillong', 25.57880000, 91.89330000, 10),
  ('mizoram', 'Aizawl', 'aizawl', 23.72710000, 92.71760000, 10),
  ('nagaland', 'Kohima', 'kohima', 25.67510000, 94.10860000, 10),
  ('nagaland', 'Dimapur', 'dimapur', 25.90910000, 93.72660000, 20),
  ('odisha', 'Bhubaneswar', 'bhubaneswar', 20.29610000, 85.82450000, 10),
  ('odisha', 'Cuttack', 'cuttack', 20.46250000, 85.88280000, 20),
  ('odisha', 'Rourkela', 'rourkela', 22.26040000, 84.85360000, 30),
  ('odisha', 'Berhampur', 'berhampur', 19.31490000, 84.79410000, 40),
  ('odisha', 'Sambalpur', 'sambalpur', 21.46690000, 83.97560000, 50),
  ('odisha', 'Puri', 'puri', 19.81350000, 85.83120000, 60),
  ('puducherry', 'Puducherry', 'puducherry', 11.94160000, 79.80830000, 10),
  ('punjab', 'Ludhiana', 'ludhiana', 30.90000000, 75.85730000, 10),
  ('punjab', 'Amritsar', 'amritsar', 31.63400000, 74.87230000, 20),
  ('punjab', 'Jalandhar', 'jalandhar', 31.32600000, 75.57620000, 30),
  ('punjab', 'Patiala', 'patiala', 30.33980000, 76.38690000, 40),
  ('punjab', 'Bathinda', 'bathinda', 30.21100000, 74.94550000, 50),
  ('punjab', 'Mohali', 'mohali', 30.70460000, 76.71790000, 60),
  ('rajasthan', 'Jaipur', 'jaipur', 26.91240000, 75.78730000, 10),
  ('rajasthan', 'Jodhpur', 'jodhpur', 26.23890000, 73.02430000, 20),
  ('rajasthan', 'Udaipur', 'udaipur', 24.58540000, 73.71250000, 30),
  ('rajasthan', 'Kota', 'kota', 25.21380000, 75.86480000, 40),
  ('rajasthan', 'Ajmer', 'ajmer', 26.44990000, 74.63990000, 50),
  ('rajasthan', 'Bikaner', 'bikaner', 28.02290000, 73.31190000, 60),
  ('rajasthan', 'Sikar', 'sikar', 27.60940000, 75.13990000, 70),
  ('rajasthan', 'Alwar', 'alwar', 27.55300000, 76.63460000, 80),
  ('rajasthan', 'Bhilwara', 'bhilwara', 25.34630000, 74.63640000, 90),
  ('sikkim', 'Gangtok', 'gangtok', 27.33890000, 88.60650000, 10),
  ('tamil-nadu', 'Chennai', 'chennai', 13.08270000, 80.27070000, 10),
  ('tamil-nadu', 'Coimbatore', 'coimbatore', 11.01680000, 76.95580000, 20),
  ('tamil-nadu', 'Madurai', 'madurai', 9.92520000, 78.11980000, 30),
  ('tamil-nadu', 'Tiruchirappalli', 'tiruchirappalli', 10.79050000, 78.70470000, 40),
  ('tamil-nadu', 'Salem', 'salem', 11.66430000, 78.14600000, 50),
  ('tamil-nadu', 'Tirunelveli', 'tirunelveli', 8.71390000, 77.75670000, 60),
  ('tamil-nadu', 'Erode', 'erode', 11.34100000, 77.71720000, 70),
  ('tamil-nadu', 'Vellore', 'vellore', 12.91650000, 79.13250000, 80),
  ('tamil-nadu', 'Thoothukudi', 'thoothukudi', 8.76420000, 78.13480000, 90),
  ('tamil-nadu', 'Thanjavur', 'thanjavur', 10.78700000, 79.13780000, 100),
  ('tamil-nadu', 'Dindigul', 'dindigul', 10.36240000, 77.96950000, 110),
  ('tamil-nadu', 'Hosur', 'hosur', 12.74090000, 77.82530000, 120),
  ('telangana', 'Hyderabad', 'hyderabad', 17.38500000, 78.48670000, 10),
  ('telangana', 'Warangal', 'warangal', 17.96890000, 79.59410000, 20),
  ('telangana', 'Karimnagar', 'karimnagar', 18.43860000, 79.12880000, 30),
  ('telangana', 'Nizamabad', 'nizamabad', 18.67250000, 78.09410000, 40),
  ('telangana', 'Khammam', 'khammam', 17.24730000, 80.15140000, 50),
  ('tripura', 'Agartala', 'agartala', 23.83150000, 91.28680000, 10),
  ('uttar-pradesh', 'Lucknow', 'lucknow', 26.84670000, 80.94620000, 10),
  ('uttar-pradesh', 'Kanpur', 'kanpur', 26.44990000, 80.33190000, 20),
  ('uttar-pradesh', 'Varanasi', 'varanasi', 25.31760000, 82.97390000, 30),
  ('uttar-pradesh', 'Agra', 'agra', 27.17670000, 78.00810000, 40),
  ('uttar-pradesh', 'Prayagraj', 'prayagraj', 25.43580000, 81.84630000, 50),
  ('uttar-pradesh', 'Bareilly', 'bareilly', 28.36700000, 79.43040000, 60),
  ('uttar-pradesh', 'Aligarh', 'aligarh', 27.89740000, 78.08800000, 70),
  ('uttar-pradesh', 'Moradabad', 'moradabad', 28.83890000, 78.77680000, 80),
  ('uttar-pradesh', 'Gorakhpur', 'gorakhpur', 26.76060000, 83.37320000, 90),
  ('uttar-pradesh', 'Saharanpur', 'saharanpur', 29.96800000, 77.55520000, 100),
  ('uttar-pradesh', 'Jhansi', 'jhansi', 25.44840000, 78.56850000, 110),
  ('uttar-pradesh', 'Mathura', 'mathura', 27.49240000, 77.67370000, 120),
  ('uttar-pradesh', 'Ayodhya', 'ayodhya', 26.79220000, 82.19980000, 130),
  ('uttar-pradesh', 'Noida', 'noida', 28.53550000, 77.39100000, 140),
  ('uttar-pradesh', 'Ghaziabad', 'ghaziabad', 28.66920000, 77.45380000, 150),
  ('uttar-pradesh', 'Greater Noida', 'greater-noida', 28.47440000, 77.50400000, 160),
  ('uttar-pradesh', 'Meerut', 'meerut', 28.98450000, 77.70640000, 170),
  ('uttarakhand', 'Dehradun', 'dehradun', 30.31650000, 78.03220000, 10),
  ('uttarakhand', 'Haridwar', 'haridwar', 29.94570000, 78.16420000, 20),
  ('uttarakhand', 'Rishikesh', 'rishikesh', 30.08690000, 78.26760000, 30),
  ('uttarakhand', 'Roorkee', 'roorkee', 29.85430000, 77.88800000, 40),
  ('uttarakhand', 'Haldwani', 'haldwani', 29.21830000, 79.51300000, 50),
  ('west-bengal', 'Kolkata', 'kolkata', 22.57260000, 88.36390000, 10),
  ('west-bengal', 'Howrah', 'howrah', 22.59580000, 88.26360000, 20),
  ('west-bengal', 'Siliguri', 'siliguri', 26.72710000, 88.39530000, 30),
  ('west-bengal', 'Durgapur', 'durgapur', 23.52040000, 87.31190000, 40),
  ('west-bengal', 'Asansol', 'asansol', 23.68890000, 86.96610000, 50),
  ('west-bengal', 'Kharagpur', 'kharagpur', 22.34600000, 87.23200000, 60),
  ('west-bengal', 'Malda', 'malda', 25.01190000, 88.14330000, 70);

-- 2) States ---------------------------------------------------------------------
INSERT INTO `directory_states` (`name`, `slug`, `country_code`, `is_active`, `sort_order`)
SELECT v.name, v.slug, 'IN', 1, v.sort_order
FROM `_seed_states` v
WHERE NOT EXISTS (
    SELECT 1 FROM `directory_states` s
    WHERE s.slug = v.slug AND s.country_code = 'IN'
);

-- 3) Attach existing state-less cities (e.g. created by DirectorySyncService)
--    to their state instead of adding a duplicate. City slugs in the seed are
--    unique across states, so the match is unambiguous.
UPDATE IGNORE `directory_cities` c
JOIN `_seed_cities` v ON v.slug = c.slug COLLATE utf8mb4_unicode_ci
JOIN `directory_states` s ON s.slug = v.state_slug AND s.country_code = 'IN'
SET c.state_id = s.id,
    c.state = s.name,
    c.lat = COALESCE(c.lat, v.lat),
    c.lng = COALESCE(c.lng, v.lng)
WHERE c.state_id IS NULL
  AND c.country_code = 'IN';

-- 4) Cities ---------------------------------------------------------------------
INSERT IGNORE INTO `directory_cities`
  (`state_id`, `name`, `slug`, `state`, `country_code`, `lat`, `lng`, `doctor_count`, `is_featured`, `is_active`, `sort_order`)
SELECT s.id, v.name, v.slug, s.name, 'IN', v.lat, v.lng, 0, 0, 1, v.sort_order
FROM `_seed_cities` v
JOIN `directory_states` s ON s.slug = v.state_slug AND s.country_code = 'IN'
WHERE NOT EXISTS (
    SELECT 1 FROM `directory_cities` c
    WHERE c.state_id = s.id AND c.slug COLLATE utf8mb4_unicode_ci = v.slug
);

DROP TEMPORARY TABLE IF EXISTS `_seed_cities`;
DROP TEMPORARY TABLE IF EXISTS `_seed_states`;
