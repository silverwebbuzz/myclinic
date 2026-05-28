-- =============================================================
-- eClinicPro — Phase 4 Diet Template Seed
-- =============================================================
-- 12 system diet templates (clinic_id NULL, doctor_id NULL).
-- These ship with the product and are always available; doctors
-- apply, tweak, and save personal copies.
--
-- plan_json shape (matches diet_plans.plan_json):
--   { instructions, encouraged[], avoid[], sample_day{}, notes }
--
-- Pre-requisite: diet_templates table exists.
-- Idempotent: each INSERT guards with WHERE NOT EXISTS on name.
-- Rollback: DELETE FROM diet_templates WHERE clinic_id IS NULL;
--
-- NOTE: every column in the SELECT is ALIASED. MySQL names derived
-- columns after their expression, so bare `SELECT NULL, NULL, ...`
-- produces two columns both named "NULL" → #1060. Aliases fix it.
-- =============================================================

-- USE silverwebbuzz_in_myclinic;

INSERT INTO diet_templates (clinic_id, doctor_id, name, description, condition_tag, veg_type, plan_json, is_active)
SELECT * FROM (
  SELECT NULL AS clinic_id, NULL AS doctor_id,
         'Diabetic diet' AS name,
         'Low glycemic, portion-controlled plan for diabetes' AS description,
         'diabetes' AS condition_tag, 'any' AS veg_type,
         JSON_OBJECT(
           'instructions', 'Small, frequent meals. Avoid sugar and refined carbs. Walk 30 min after dinner.',
           'encouraged', JSON_ARRAY('Whole grains (oats, brown rice, jowar/bajra)', 'Leafy vegetables', 'Dals and sprouts', 'Low-fat dairy', 'Nuts in moderation'),
           'avoid', JSON_ARRAY('Sugar, sweets, sweetened drinks', 'White flour (maida)', 'Deep-fried foods', 'Fruit juices', 'White rice in excess'),
           'sample_day', JSON_OBJECT('breakfast','Vegetable oats / 2 multigrain rotis','midmorning','Buttermilk or a small fruit','lunch','2 rotis + dal + sabzi + salad','evening','Roasted chana / sprouts','dinner','Multigrain roti + sabzi + salad'),
           'notes', 'Monitor sugar regularly. Stay hydrated.'
         ) AS plan_json, 1 AS is_active
) t WHERE NOT EXISTS (SELECT 1 FROM diet_templates WHERE clinic_id IS NULL AND name = 'Diabetic diet');

INSERT INTO diet_templates (clinic_id, doctor_id, name, description, condition_tag, veg_type, plan_json, is_active)
SELECT * FROM (
  SELECT NULL AS clinic_id, NULL AS doctor_id,
         'Hypertension — low salt' AS name,
         'DASH-style low-sodium plan for high blood pressure' AS description,
         'hypertension' AS condition_tag, 'any' AS veg_type,
         JSON_OBJECT(
           'instructions', 'Keep salt under 5 g/day. No papad/pickle. Limit processed foods.',
           'encouraged', JSON_ARRAY('Fresh fruits and vegetables', 'Whole grains', 'Low-fat dairy', 'Potassium-rich foods (banana, coconut water)'),
           'avoid', JSON_ARRAY('Added salt, pickles, papad', 'Processed and packaged foods', 'Salted snacks', 'Excess tea/coffee'),
           'sample_day', JSON_OBJECT('breakfast','Poha (low salt) + fruit','midmorning','Coconut water','lunch','Rotis + dal + sabzi (less salt)','evening','Fruit + handful of nuts','dinner','Khichdi + curd'),
           'notes', 'Check BP regularly. Reduce stress.'
         ) AS plan_json, 1 AS is_active
) t WHERE NOT EXISTS (SELECT 1 FROM diet_templates WHERE clinic_id IS NULL AND name = 'Hypertension — low salt');

INSERT INTO diet_templates (clinic_id, doctor_id, name, description, condition_tag, veg_type, plan_json, is_active)
SELECT * FROM (
  SELECT NULL AS clinic_id, NULL AS doctor_id,
         'Weight loss' AS name,
         'Calorie-controlled balanced plan for weight reduction' AS description,
         'obesity' AS condition_tag, 'any' AS veg_type,
         JSON_OBJECT(
           'instructions', 'Eat slowly. Fill half the plate with vegetables. No second helpings.',
           'encouraged', JSON_ARRAY('High-fibre vegetables', 'Lean protein (dal, egg, paneer, chicken)', 'Whole fruits', 'Plenty of water'),
           'avoid', JSON_ARRAY('Sugary drinks and sweets', 'Fried foods', 'Refined carbs', 'Late-night snacking'),
           'sample_day', JSON_OBJECT('breakfast','Vegetable upma + 1 fruit','midmorning','Green tea','lunch','1 roti + dal + big salad','evening','Roasted makhana','dinner','Soup + grilled veg/paneer'),
           'notes', '30–45 min activity daily. Aim for 0.5 kg/week.'
         ) AS plan_json, 1 AS is_active
) t WHERE NOT EXISTS (SELECT 1 FROM diet_templates WHERE clinic_id IS NULL AND name = 'Weight loss');

INSERT INTO diet_templates (clinic_id, doctor_id, name, description, condition_tag, veg_type, plan_json, is_active)
SELECT * FROM (
  SELECT NULL AS clinic_id, NULL AS doctor_id,
         'Thyroid support' AS name,
         'Balanced plan supporting thyroid health' AS description,
         'thyroid' AS condition_tag, 'any' AS veg_type,
         JSON_OBJECT(
           'instructions', 'Take thyroid medicine empty stomach, 30–60 min before breakfast.',
           'encouraged', JSON_ARRAY('Iodized salt (in moderation)', 'Nuts and seeds (selenium, zinc)', 'Whole grains', 'Fresh fruits and vegetables'),
           'avoid', JSON_ARRAY('Excess raw cruciferous veg (cabbage, cauliflower)', 'Soy in excess', 'Processed foods'),
           'sample_day', JSON_OBJECT('breakfast','Eggs / poha + nuts','midmorning','Fruit','lunch','Rice/roti + dal + sabzi','evening','Roasted seeds','dinner','Roti + sabzi + curd'),
           'notes', 'Take medication consistently. Recheck TSH as advised.'
         ) AS plan_json, 1 AS is_active
) t WHERE NOT EXISTS (SELECT 1 FROM diet_templates WHERE clinic_id IS NULL AND name = 'Thyroid support');

INSERT INTO diet_templates (clinic_id, doctor_id, name, description, condition_tag, veg_type, plan_json, is_active)
SELECT * FROM (
  SELECT NULL AS clinic_id, NULL AS doctor_id,
         'PCOS diet' AS name,
         'Low-GI, anti-inflammatory plan for PCOS/PCOD' AS description,
         'pcos' AS condition_tag, 'any' AS veg_type,
         JSON_OBJECT(
           'instructions', 'Low glycemic index foods. Regular meal timing. Reduce dairy and sugar.',
           'encouraged', JSON_ARRAY('Whole grains', 'Lean protein', 'Leafy greens', 'Flax/chia seeds', 'Cinnamon'),
           'avoid', JSON_ARRAY('Sugar and sweets', 'Refined carbs (maida)', 'Sugary drinks', 'Excess dairy'),
           'sample_day', JSON_OBJECT('breakfast','Besan chilla + veg','midmorning','Handful of nuts','lunch','Roti + dal + sabzi + salad','evening','Green tea + roasted chana','dinner','Quinoa/millet + veg'),
           'notes', 'Combine with regular exercise for best results.'
         ) AS plan_json, 1 AS is_active
) t WHERE NOT EXISTS (SELECT 1 FROM diet_templates WHERE clinic_id IS NULL AND name = 'PCOS diet');

INSERT INTO diet_templates (clinic_id, doctor_id, name, description, condition_tag, veg_type, plan_json, is_active)
SELECT * FROM (
  SELECT NULL AS clinic_id, NULL AS doctor_id,
         'IBS-friendly' AS name,
         'Gut-friendly low-FODMAP-leaning plan for IBS' AS description,
         'ibs' AS condition_tag, 'any' AS veg_type,
         JSON_OBJECT(
           'instructions', 'Eat at regular times. Identify and avoid trigger foods. Stay hydrated.',
           'encouraged', JSON_ARRAY('Cooked vegetables', 'Rice and oats', 'Bananas', 'Curd/probiotics', 'Plenty of water'),
           'avoid', JSON_ARRAY('Very spicy/oily food', 'Excess raw onion and garlic', 'Carbonated drinks', 'Excess caffeine', 'Beans in excess'),
           'sample_day', JSON_OBJECT('breakfast','Oats porridge + banana','midmorning','Curd','lunch','Rice + dal (mild) + cooked veg','evening','Plain biscuit + tea','dinner','Khichdi + curd'),
           'notes', 'Keep a food-symptom diary to spot triggers.'
         ) AS plan_json, 1 AS is_active
) t WHERE NOT EXISTS (SELECT 1 FROM diet_templates WHERE clinic_id IS NULL AND name = 'IBS-friendly');

INSERT INTO diet_templates (clinic_id, doctor_id, name, description, condition_tag, veg_type, plan_json, is_active)
SELECT * FROM (
  SELECT NULL AS clinic_id, NULL AS doctor_id,
         'Low cholesterol' AS name,
         'Heart-healthy low-saturated-fat plan' AS description,
         'cholesterol' AS condition_tag, 'any' AS veg_type,
         JSON_OBJECT(
           'instructions', 'Reduce fried and fatty foods. Use minimal oil. Prefer steaming/grilling.',
           'encouraged', JSON_ARRAY('Oats and whole grains', 'Fruits and vegetables', 'Nuts (almonds, walnuts)', 'Fish (if non-veg)', 'Olive/mustard oil in small amounts'),
           'avoid', JSON_ARRAY('Fried foods and ghee in excess', 'Red meat and organ meat', 'Full-fat dairy', 'Bakery items, butter'),
           'sample_day', JSON_OBJECT('breakfast','Oats + fruit','midmorning','Handful of almonds','lunch','Roti + dal + sabzi (less oil)','evening','Fruit','dinner','Grilled veg/fish + salad'),
           'notes', 'Recheck lipid profile as advised. Stay active.'
         ) AS plan_json, 1 AS is_active
) t WHERE NOT EXISTS (SELECT 1 FROM diet_templates WHERE clinic_id IS NULL AND name = 'Low cholesterol');

INSERT INTO diet_templates (clinic_id, doctor_id, name, description, condition_tag, veg_type, plan_json, is_active)
SELECT * FROM (
  SELECT NULL AS clinic_id, NULL AS doctor_id,
         'Post-surgery recovery' AS name,
         'High-protein healing plan after surgery' AS description,
         'post_surgery' AS condition_tag, 'any' AS veg_type,
         JSON_OBJECT(
           'instructions', 'Prioritise protein for healing. Small frequent meals. Stay hydrated.',
           'encouraged', JSON_ARRAY('Protein (dal, egg, paneer, chicken, fish)', 'Soft cooked vegetables', 'Fruits rich in vitamin C', 'Plenty of fluids'),
           'avoid', JSON_ARRAY('Very spicy/oily food', 'Raw/hard-to-digest foods initially', 'Alcohol', 'Excess caffeine'),
           'sample_day', JSON_OBJECT('breakfast','Egg/paneer + soft roti','midmorning','Fruit + milk','lunch','Rice/roti + dal + soft veg','evening','Soup','dinner','Khichdi + curd'),
           'notes', 'Follow surgeon advice on food restrictions.'
         ) AS plan_json, 1 AS is_active
) t WHERE NOT EXISTS (SELECT 1 FROM diet_templates WHERE clinic_id IS NULL AND name = 'Post-surgery recovery');

INSERT INTO diet_templates (clinic_id, doctor_id, name, description, condition_tag, veg_type, plan_json, is_active)
SELECT * FROM (
  SELECT NULL AS clinic_id, NULL AS doctor_id,
         'Pregnancy nutrition' AS name,
         'Balanced plan for a healthy pregnancy' AS description,
         'pregnancy' AS condition_tag, 'any' AS veg_type,
         JSON_OBJECT(
           'instructions', 'Eat for nourishment, not quantity. Take iron/folic acid as prescribed.',
           'encouraged', JSON_ARRAY('Iron-rich foods (greens, dates, jaggery)', 'Calcium (milk, curd, paneer)', 'Protein', 'Fruits and vegetables', 'Folic-acid-rich foods'),
           'avoid', JSON_ARRAY('Raw/undercooked foods', 'Unpasteurised dairy', 'Excess caffeine', 'Papaya and pineapple in excess'),
           'sample_day', JSON_OBJECT('breakfast','Milk + poha/upma + fruit','midmorning','Dry fruits','lunch','Rotis + dal + green sabzi + curd','evening','Fruit + nuts','dinner','Rice/roti + dal + veg'),
           'notes', 'Regular antenatal checkups. Stay hydrated.'
         ) AS plan_json, 1 AS is_active
) t WHERE NOT EXISTS (SELECT 1 FROM diet_templates WHERE clinic_id IS NULL AND name = 'Pregnancy nutrition');

INSERT INTO diet_templates (clinic_id, doctor_id, name, description, condition_tag, veg_type, plan_json, is_active)
SELECT * FROM (
  SELECT NULL AS clinic_id, NULL AS doctor_id,
         'Pediatric general' AS name,
         'Balanced growth-supporting plan for children' AS description,
         'pediatric' AS condition_tag, 'any' AS veg_type,
         JSON_OBJECT(
           'instructions', 'Variety and regular meals. Limit junk and sugary snacks.',
           'encouraged', JSON_ARRAY('Milk and dairy', 'Fruits and vegetables', 'Whole grains', 'Eggs/dal/paneer for protein', 'Nuts (age-appropriate)'),
           'avoid', JSON_ARRAY('Sugary drinks and candy', 'Packaged chips/snacks', 'Excess fried food', 'Caffeinated drinks'),
           'sample_day', JSON_OBJECT('breakfast','Milk + paratha/idli + fruit','midmorning','Fruit','lunch','Rice/roti + dal + sabzi','evening','Milk + healthy snack','dinner','Khichdi/roti + veg'),
           'notes', 'Encourage outdoor play. Track growth at checkups.'
         ) AS plan_json, 1 AS is_active
) t WHERE NOT EXISTS (SELECT 1 FROM diet_templates WHERE clinic_id IS NULL AND name = 'Pediatric general');

INSERT INTO diet_templates (clinic_id, doctor_id, name, description, condition_tag, veg_type, plan_json, is_active)
SELECT * FROM (
  SELECT NULL AS clinic_id, NULL AS doctor_id,
         'Gout / low purine' AS name,
         'Low-purine plan to reduce uric acid' AS description,
         'gout' AS condition_tag, 'any' AS veg_type,
         JSON_OBJECT(
           'instructions', 'Drink plenty of water. Avoid high-purine foods. Limit alcohol.',
           'encouraged', JSON_ARRAY('Plenty of water', 'Low-fat dairy', 'Whole grains', 'Most vegetables', 'Cherries and citrus'),
           'avoid', JSON_ARRAY('Organ meats, red meat', 'Seafood (prawns, sardines)', 'Alcohol (especially beer)', 'Sugary drinks', 'Excess lentils/beans'),
           'sample_day', JSON_OBJECT('breakfast','Oats + fruit','midmorning','Coconut water','lunch','Rice + light dal + sabzi','evening','Fruit','dinner','Roti + veg + curd'),
           'notes', 'Stay hydrated — 2.5–3 L water/day.'
         ) AS plan_json, 1 AS is_active
) t WHERE NOT EXISTS (SELECT 1 FROM diet_templates WHERE clinic_id IS NULL AND name = 'Gout / low purine');

INSERT INTO diet_templates (clinic_id, doctor_id, name, description, condition_tag, veg_type, plan_json, is_active)
SELECT * FROM (
  SELECT NULL AS clinic_id, NULL AS doctor_id,
         'Anemia / iron-rich' AS name,
         'Iron- and vitamin-C-rich plan to build hemoglobin' AS description,
         'anemia' AS condition_tag, 'any' AS veg_type,
         JSON_OBJECT(
           'instructions', 'Pair iron foods with vitamin C. Avoid tea/coffee with meals.',
           'encouraged', JSON_ARRAY('Leafy greens (spinach, methi)', 'Jaggery and dates', 'Beetroot, pomegranate', 'Vitamin C foods (citrus, amla)', 'Eggs/meat if non-veg'),
           'avoid', JSON_ARRAY('Tea/coffee with meals', 'Excess calcium with iron meals', 'Processed foods'),
           'sample_day', JSON_OBJECT('breakfast','Methi paratha + amla','midmorning','Dates + nuts','lunch','Spinach dal + rice + lemon','evening','Pomegranate/citrus fruit','dinner','Roti + green sabzi + salad'),
           'notes', 'Take iron supplements as prescribed. Recheck Hb as advised.'
         ) AS plan_json, 1 AS is_active
) t WHERE NOT EXISTS (SELECT 1 FROM diet_templates WHERE clinic_id IS NULL AND name = 'Anemia / iron-rich');

-- =============================================================
-- Verify
-- =============================================================
-- SELECT COUNT(*) FROM diet_templates WHERE clinic_id IS NULL AND doctor_id IS NULL;
-- Expected: 12
