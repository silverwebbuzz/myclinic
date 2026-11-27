-- =====================================================================
-- Wellness Hub — demo / test content (2026-11-28).
--
-- RUN AFTER all Wellness Hub patches (phase 1 → 5). Safe to run more than
-- once (everything is "insert if missing").   phpMyAdmin → Import → this file.
--
-- 1. Patient account +91 9998010029 → Health Creator "eClinicPro"
--    (Verified Organisation), approved for ALL six types.
-- 2. 5 recipes, 5 exercises, 5 fitness tips by that creator — PUBLISHED.
--    Written for eClinicPro (not copied); no medical claims.
-- 3. 5 FREE CAMPS for December 2026 — PUBLISHED under "eClinicPro Desk".
--    All are standing, free Government of India programmes (sources in
--    each description). Venue = the government facilities that run them.
-- 4. 5 EVENTS for December 2026 — left "submitted" (waiting for review)
--    under "eClinicPro Desk". Dates come from event-listing websites and
--    ticket prices are NOT confirmed: check each one in
--    /admin/wellness/events, fix the fee / timings, then Publish.
--
-- To remove everything later: see the DELETE block at the end (commented out).
-- =====================================================================

-- ---------------------------------------------------------------------
-- 0. City slugs as your directory spells them (fallback if a city is missing)
-- ---------------------------------------------------------------------
SET @c_ahd := COALESCE((SELECT `slug` FROM `directory_cities` WHERE `slug` = 'ahmedabad' LIMIT 1), 'ahmedabad');
SET @c_srt := COALESCE((SELECT `slug` FROM `directory_cities` WHERE `slug` = 'surat' LIMIT 1), 'surat');
SET @c_vad := COALESCE((SELECT `slug` FROM `directory_cities` WHERE `slug` IN ('vadodara', 'baroda') ORDER BY `slug` = 'vadodara' DESC LIMIT 1), 'vadodara');
SET @c_mum := COALESCE((SELECT `slug` FROM `directory_cities` WHERE `slug` = 'mumbai' LIMIT 1), 'mumbai');
SET @c_blr := COALESCE((SELECT `slug` FROM `directory_cities` WHERE `slug` IN ('bengaluru', 'bangalore') ORDER BY `slug` = 'bengaluru' DESC LIMIT 1), 'bengaluru');
SET @c_del := COALESCE((SELECT `slug` FROM `directory_cities` WHERE `slug` IN ('new-delhi', 'delhi') ORDER BY `slug` = 'new-delhi' DESC LIMIT 1), 'new-delhi');

-- ---------------------------------------------------------------------
-- 1. You as a Health Creator
-- ---------------------------------------------------------------------
INSERT INTO `patient_identities` (`phone`, `name`, `first_name`, `source`, `phone_verified_at`)
SELECT '+919998010029', 'eClinicPro', 'eClinicPro', 'self_signup', NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `patient_identities` WHERE `phone` = '+919998010029');
SET @iid := (SELECT `id` FROM `patient_identities` WHERE `phone` = '+919998010029' LIMIT 1);

-- Health Tips contributor row (recipes / exercises / tips belong to it)
INSERT INTO `health_tip_contributors` (`identity_id`, `slug`, `display_name`, `headline`, `bio`, `status`, `terms_accepted_at`, `terms_version`, `granted_at`, `admin_note`)
SELECT @iid, 'eclinicpro-team', 'eClinicPro', 'Healthy recipes, simple workouts and everyday health habits',
       'Recipes, exercises and tips put together by the eClinicPro team. General wellness information — not medical advice.',
       'active', NOW(), 'wh-2026-11-27', NOW(), 'Demo seed 2026-11-28'
FROM DUAL
WHERE @iid IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `health_tip_contributors` WHERE `identity_id` = @iid)
  AND NOT EXISTS (SELECT 1 FROM `health_tip_contributors` WHERE `slug` = 'eclinicpro-team');
SET @hc := (SELECT `id` FROM `health_tip_contributors` WHERE `identity_id` = @iid LIMIT 1);
UPDATE `health_tip_contributors` SET `status` = 'active', `terms_accepted_at` = COALESCE(`terms_accepted_at`, NOW()) WHERE `id` = @hc;

-- Wellness creator profile
INSERT INTO `wh_creators` (`identity_id`, `legacy_contributor_id`, `slug`, `display_name`, `creator_type`, `headline`, `bio`,
                           `city`, `state`, `org_name`, `website_url`, `status`, `terms_accepted_at`, `terms_version`,
                           `verify_status`, `verify_requested_at`, `verified_at`, `verify_register`, `admin_note`)
SELECT @iid, @hc, 'eclinicpro-team', 'eClinicPro', 'company',
       'Healthy recipes, simple workouts and everyday health habits',
       'Recipes, exercises and tips put together by the eClinicPro team. General wellness information — not medical advice.',
       'Ahmedabad', 'Gujarat', 'Silver Webbuzz Private Limited (eClinicPro)', 'https://eclinicpro.com', 'active', NOW(), '2026-11-27',
       'verified', NOW(), NOW(), 'Own organisation', 'Demo seed 2026-11-28'
FROM DUAL
WHERE @iid IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `wh_creators` WHERE `identity_id` = @iid)
  AND NOT EXISTS (SELECT 1 FROM `wh_creators` WHERE `slug` = 'eclinicpro-team');
-- If you had already joined as a creator with this number, link your posts to it.
UPDATE `wh_creators` SET `legacy_contributor_id` = @hc, `status` = 'active'
 WHERE `identity_id` = @iid AND `legacy_contributor_id` IS NULL
   AND NOT EXISTS (SELECT 1 FROM (SELECT `id` FROM `wh_creators` WHERE `legacy_contributor_id` = @hc) x);
SET @cid := (SELECT `id` FROM `wh_creators` WHERE `identity_id` = @iid LIMIT 1);

-- Approved for every type
INSERT INTO `wh_creator_permissions` (`creator_id`, `content_type`, `status`, `applied_at`, `reviewed_at`, `review_note`)
SELECT @cid, t.ct, 'approved', NOW(), NOW(), 'Demo seed — all types'
FROM (SELECT 'recipe' AS ct UNION ALL SELECT 'exercise' UNION ALL SELECT 'tip' UNION ALL SELECT 'video'
      UNION ALL SELECT 'free_camp' UNION ALL SELECT 'event') t
WHERE @cid IS NOT NULL
ON DUPLICATE KEY UPDATE `status` = 'approved', `reviewed_at` = NOW();

SET @desk := (SELECT `id` FROM `wh_creators` WHERE `slug` = 'eclinicpro-desk' LIMIT 1);

-- =====================================================================
-- 2a. RECIPES (5)
-- =====================================================================

-- R1 ---------------------------------------------------------------
INSERT INTO `health_tips` (`contributor_id`, `type`, `title`, `slug`, `summary`, `body`, `servings`, `prep_minutes`, `cook_minutes`, `protein_g`, `calories_kcal`, `status`, `submitted_at`, `reviewed_at`, `published_at`)
SELECT @hc, 'recipe', 'Moong dal chilla with paneer', 'moong-dal-chilla-with-paneer',
       'Soft, savoury pancakes made from soaked moong dal and filled with grated paneer — a filling, protein-rich vegetarian breakfast.',
       'Soak the dal in the morning (or the night before) and breakfast comes together in 20 minutes. Leftover batter keeps for a day in the fridge. Nutrition is an approximate estimate per serving.',
       2, 10, 15, 18.0, 320, 'published', NOW(), NOW(), NOW()
FROM DUAL WHERE @hc IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `health_tips` WHERE `slug` = 'moong-dal-chilla-with-paneer');
SET @t := (SELECT `id` FROM `health_tips` WHERE `slug` = 'moong-dal-chilla-with-paneer' LIMIT 1);
INSERT INTO `health_tip_ingredients` (`tip_id`, `sort`, `quantity`, `item`)
SELECT @t, x.s, x.q, x.i FROM (
    SELECT 0 AS s, '1 cup' AS q, 'yellow moong dal, soaked 3–4 hours' AS i UNION ALL
    SELECT 1, '1 inch', 'ginger' UNION ALL
    SELECT 2, '1', 'green chilli' UNION ALL
    SELECT 3, '½ tsp', 'cumin seeds' UNION ALL
    SELECT 4, '100 g', 'paneer, grated' UNION ALL
    SELECT 5, '1 small', 'onion, finely chopped' UNION ALL
    SELECT 6, '2 tbsp', 'fresh coriander, chopped' UNION ALL
    SELECT 7, '2 tsp', 'oil' UNION ALL
    SELECT 8, NULL, 'salt to taste'
) x WHERE @t IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `health_tip_ingredients` WHERE `tip_id` = @t);
INSERT INTO `health_tip_steps` (`tip_id`, `sort`, `body`)
SELECT @t, x.s, x.b FROM (
    SELECT 0 AS s, 'Drain the dal and grind it with the ginger, green chilli and a little water into a smooth, thick batter.' AS b UNION ALL
    SELECT 1, 'Stir in the cumin seeds and salt. The batter should spread easily but not be runny.' UNION ALL
    SELECT 2, 'For the filling, mix the paneer, onion and coriander with a pinch of salt.' UNION ALL
    SELECT 3, 'Heat a tawa, pour a ladle of batter and spread it thin. Drizzle a few drops of oil around the edge.' UNION ALL
    SELECT 4, 'Cook for about 2 minutes until the base is golden, spoon some filling on one half and fold.' UNION ALL
    SELECT 5, 'Serve hot with green chutney or curd.'
) x WHERE @t IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `health_tip_steps` WHERE `tip_id` = @t);
INSERT IGNORE INTO `health_tip_tag_map` (`tip_id`, `tag_id`)
SELECT @t, `id` FROM `health_tip_tags` WHERE @t IS NOT NULL AND `slug` IN ('vegetarian', 'gluten-free', 'high-protein', 'breakfast');

-- R2 ---------------------------------------------------------------
INSERT INTO `health_tips` (`contributor_id`, `type`, `title`, `slug`, `summary`, `body`, `servings`, `prep_minutes`, `cook_minutes`, `protein_g`, `calories_kcal`, `status`, `submitted_at`, `reviewed_at`, `published_at`)
SELECT @hc, 'recipe', 'Sprouts chaat bowl', 'sprouts-chaat-bowl',
       'Lightly steamed mixed sprouts tossed with crunchy vegetables, lemon and chaat masala — a fresh, high-fibre evening snack.',
       'Make it just before eating so the vegetables stay crunchy. Swap peanuts for roasted chana if you prefer. Nutrition is an approximate estimate per serving.',
       2, 10, 5, 10.0, 220, 'published', NOW(), NOW(), NOW()
FROM DUAL WHERE @hc IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `health_tips` WHERE `slug` = 'sprouts-chaat-bowl');
SET @t := (SELECT `id` FROM `health_tips` WHERE `slug` = 'sprouts-chaat-bowl' LIMIT 1);
INSERT INTO `health_tip_ingredients` (`tip_id`, `sort`, `quantity`, `item`)
SELECT @t, x.s, x.q, x.i FROM (
    SELECT 0 AS s, '1½ cups' AS q, 'mixed sprouts (moong, matki)' AS i UNION ALL
    SELECT 1, '1', 'tomato, chopped' UNION ALL
    SELECT 2, '1 small', 'onion, chopped' UNION ALL
    SELECT 3, '½', 'cucumber, chopped' UNION ALL
    SELECT 4, '1 tbsp', 'lemon juice' UNION ALL
    SELECT 5, '½ tsp', 'chaat masala' UNION ALL
    SELECT 6, '¼ tsp', 'roasted cumin powder' UNION ALL
    SELECT 7, '2 tbsp', 'fresh coriander, chopped' UNION ALL
    SELECT 8, '1 tbsp', 'roasted peanuts (optional)'
) x WHERE @t IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `health_tip_ingredients` WHERE `tip_id` = @t);
INSERT INTO `health_tip_steps` (`tip_id`, `sort`, `body`)
SELECT @t, x.s, x.b FROM (
    SELECT 0 AS s, 'Steam the sprouts for 4–5 minutes until just tender, then let them cool.' AS b UNION ALL
    SELECT 1, 'Chop the tomato, onion and cucumber into small pieces.' UNION ALL
    SELECT 2, 'Toss the sprouts and vegetables with lemon juice, chaat masala, cumin powder and salt if needed.' UNION ALL
    SELECT 3, 'Top with coriander and peanuts and serve straight away.'
) x WHERE @t IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `health_tip_steps` WHERE `tip_id` = @t);
INSERT IGNORE INTO `health_tip_tag_map` (`tip_id`, `tag_id`)
SELECT @t, `id` FROM `health_tip_tags` WHERE @t IS NOT NULL AND `slug` IN ('vegan', 'gluten-free', 'high-fibre', 'low-oil', 'snack');

-- R3 ---------------------------------------------------------------
INSERT INTO `health_tips` (`contributor_id`, `type`, `title`, `slug`, `summary`, `body`, `servings`, `prep_minutes`, `cook_minutes`, `protein_g`, `calories_kcal`, `status`, `submitted_at`, `reviewed_at`, `published_at`)
SELECT @hc, 'recipe', 'Vegetable oats upma', 'vegetable-oats-upma',
       'A South Indian-style upma made with rolled oats and plenty of vegetables, ready in about 25 minutes.',
       'Dry-roasting the oats first keeps the upma from turning mushy. Use any vegetables you have. Nutrition is an approximate estimate per serving.',
       2, 10, 15, 8.0, 280, 'published', NOW(), NOW(), NOW()
FROM DUAL WHERE @hc IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `health_tips` WHERE `slug` = 'vegetable-oats-upma');
SET @t := (SELECT `id` FROM `health_tips` WHERE `slug` = 'vegetable-oats-upma' LIMIT 1);
INSERT INTO `health_tip_ingredients` (`tip_id`, `sort`, `quantity`, `item`)
SELECT @t, x.s, x.q, x.i FROM (
    SELECT 0 AS s, '1 cup' AS q, 'rolled oats' AS i UNION ALL
    SELECT 1, '1 tsp', 'oil' UNION ALL
    SELECT 2, '½ tsp', 'mustard seeds' UNION ALL
    SELECT 3, '1 tsp', 'urad dal' UNION ALL
    SELECT 4, '8–10', 'curry leaves' UNION ALL
    SELECT 5, '1', 'onion, chopped' UNION ALL
    SELECT 6, '1 cup', 'mixed vegetables (carrot, beans, peas), chopped' UNION ALL
    SELECT 7, '1', 'green chilli, slit' UNION ALL
    SELECT 8, '1¼ cups', 'hot water' UNION ALL
    SELECT 9, '1 tbsp', 'lemon juice' UNION ALL
    SELECT 10, NULL, 'salt and fresh coriander'
) x WHERE @t IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `health_tip_ingredients` WHERE `tip_id` = @t);
INSERT INTO `health_tip_steps` (`tip_id`, `sort`, `body`)
SELECT @t, x.s, x.b FROM (
    SELECT 0 AS s, 'Dry-roast the oats in a pan for 3–4 minutes until lightly toasted. Set aside.' AS b UNION ALL
    SELECT 1, 'Heat the oil, add mustard seeds; when they splutter add urad dal, curry leaves and green chilli.' UNION ALL
    SELECT 2, 'Add the onion and cook until soft, then the vegetables. Cook for about 5 minutes.' UNION ALL
    SELECT 3, 'Pour in the hot water with salt and bring it to a boil.' UNION ALL
    SELECT 4, 'Stir in the oats, cover and cook for 2–3 minutes until the water is absorbed.' UNION ALL
    SELECT 5, 'Finish with lemon juice and coriander.'
) x WHERE @t IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `health_tip_steps` WHERE `tip_id` = @t);
INSERT IGNORE INTO `health_tip_tag_map` (`tip_id`, `tag_id`)
SELECT @t, `id` FROM `health_tip_tags` WHERE @t IS NOT NULL AND `slug` IN ('vegan', 'high-fibre', 'low-oil', 'breakfast');

-- R4 ---------------------------------------------------------------
INSERT INTO `health_tips` (`contributor_id`, `type`, `title`, `slug`, `summary`, `body`, `servings`, `prep_minutes`, `cook_minutes`, `protein_g`, `calories_kcal`, `status`, `submitted_at`, `reviewed_at`, `published_at`)
SELECT @hc, 'recipe', 'Ragi banana pancakes', 'ragi-banana-pancakes',
       'Eggless pancakes made with ragi (finger millet) flour and ripe banana — naturally sweet and loved by kids.',
       'The riper the banana, the less jaggery you need. Makes about 6 small pancakes. Nutrition is an approximate estimate per serving.',
       2, 10, 15, 7.0, 300, 'published', NOW(), NOW(), NOW()
FROM DUAL WHERE @hc IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `health_tips` WHERE `slug` = 'ragi-banana-pancakes');
SET @t := (SELECT `id` FROM `health_tips` WHERE `slug` = 'ragi-banana-pancakes' LIMIT 1);
INSERT INTO `health_tip_ingredients` (`tip_id`, `sort`, `quantity`, `item`)
SELECT @t, x.s, x.q, x.i FROM (
    SELECT 0 AS s, '¾ cup' AS q, 'ragi (finger millet) flour' AS i UNION ALL
    SELECT 1, '¼ cup', 'whole wheat flour' UNION ALL
    SELECT 2, '1', 'ripe banana, mashed' UNION ALL
    SELECT 3, '¾ cup', 'milk' UNION ALL
    SELECT 4, '½ tsp', 'baking powder' UNION ALL
    SELECT 5, 'a pinch', 'cinnamon powder' UNION ALL
    SELECT 6, '1 tsp', 'jaggery powder (optional)' UNION ALL
    SELECT 7, '2 tsp', 'ghee, for the pan'
) x WHERE @t IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `health_tip_ingredients` WHERE `tip_id` = @t);
INSERT INTO `health_tip_steps` (`tip_id`, `sort`, `body`)
SELECT @t, x.s, x.b FROM (
    SELECT 0 AS s, 'Mash the banana and whisk it with the milk.' AS b UNION ALL
    SELECT 1, 'Add both flours, baking powder, cinnamon and jaggery. Mix into a thick, pourable batter and rest it for 5 minutes.' UNION ALL
    SELECT 2, 'Heat a pan and grease it lightly with ghee.' UNION ALL
    SELECT 3, 'Pour about 2 tablespoons of batter per pancake. Cook for 2 minutes until bubbles appear, flip and cook 1 more minute.' UNION ALL
    SELECT 4, 'Serve warm with sliced fruit.'
) x WHERE @t IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `health_tip_steps` WHERE `tip_id` = @t);
INSERT IGNORE INTO `health_tip_tag_map` (`tip_id`, `tag_id`)
SELECT @t, `id` FROM `health_tip_tags` WHERE @t IS NOT NULL AND `slug` IN ('vegetarian', 'calcium-rich', 'kids-friendly', 'breakfast');

-- R5 ---------------------------------------------------------------
INSERT INTO `health_tips` (`contributor_id`, `type`, `title`, `slug`, `summary`, `body`, `servings`, `prep_minutes`, `cook_minutes`, `protein_g`, `calories_kcal`, `status`, `submitted_at`, `reviewed_at`, `published_at`)
SELECT @hc, 'recipe', 'Vegetable dalia khichdi', 'vegetable-dalia-khichdi',
       'A comforting one-pot khichdi made with broken wheat (dalia), moong dal and seasonal vegetables.',
       'Add more water for a softer, porridge-like khichdi. Lovely with curd and a simple salad. Nutrition is an approximate estimate per serving.',
       3, 10, 20, 12.0, 330, 'published', NOW(), NOW(), NOW()
FROM DUAL WHERE @hc IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `health_tips` WHERE `slug` = 'vegetable-dalia-khichdi');
SET @t := (SELECT `id` FROM `health_tips` WHERE `slug` = 'vegetable-dalia-khichdi' LIMIT 1);
INSERT INTO `health_tip_ingredients` (`tip_id`, `sort`, `quantity`, `item`)
SELECT @t, x.s, x.q, x.i FROM (
    SELECT 0 AS s, '½ cup' AS q, 'broken wheat (dalia)' AS i UNION ALL
    SELECT 1, '¼ cup', 'yellow moong dal' UNION ALL
    SELECT 2, '1 tsp', 'ghee' UNION ALL
    SELECT 3, '½ tsp', 'cumin seeds' UNION ALL
    SELECT 4, 'a pinch', 'hing (asafoetida)' UNION ALL
    SELECT 5, '1 inch', 'ginger, grated' UNION ALL
    SELECT 6, '1 cup', 'chopped vegetables (carrot, beans, peas, bottle gourd)' UNION ALL
    SELECT 7, '¼ tsp', 'turmeric' UNION ALL
    SELECT 8, '3 cups', 'water' UNION ALL
    SELECT 9, NULL, 'salt to taste'
) x WHERE @t IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `health_tip_ingredients` WHERE `tip_id` = @t);
INSERT INTO `health_tip_steps` (`tip_id`, `sort`, `body`)
SELECT @t, x.s, x.b FROM (
    SELECT 0 AS s, 'Rinse the dalia and dal together and drain.' AS b UNION ALL
    SELECT 1, 'Heat the ghee in a pressure cooker; add cumin, hing and ginger.' UNION ALL
    SELECT 2, 'Add the vegetables and turmeric and stir for 2 minutes.' UNION ALL
    SELECT 3, 'Add the dalia, dal, salt and water. Pressure cook for 3 whistles, then let the pressure release on its own.' UNION ALL
    SELECT 4, 'Stir, adjust the consistency with hot water if needed, and serve with curd.'
) x WHERE @t IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `health_tip_steps` WHERE `tip_id` = @t);
INSERT IGNORE INTO `health_tip_tag_map` (`tip_id`, `tag_id`)
SELECT @t, `id` FROM `health_tip_tags` WHERE @t IS NOT NULL AND `slug` IN ('vegetarian', 'high-fibre', 'lunch', 'dinner');

-- =====================================================================
-- 2b. EXERCISES (5)
-- =====================================================================

-- X1 ---------------------------------------------------------------
INSERT INTO `health_tips` (`contributor_id`, `type`, `title`, `slug`, `summary`, `body`, `duration_minutes`, `difficulty`, `reps_sets`, `equipment`, `body_area`, `precautions`, `status`, `submitted_at`, `reviewed_at`, `published_at`)
SELECT @hc, 'exercise', '10-minute desk stretch routine', '10-minute-desk-stretch-routine',
       'Six gentle stretches you can do in your chair to loosen the neck, shoulders, back and wrists after long hours at a desk.',
       'Do this once in the morning and once after lunch. Breathe slowly and never bounce into a stretch.',
       10, 'easy', '1 round — hold each stretch 20–30 seconds', 'A chair', 'Neck, shoulders, back, wrists',
       'Move slowly and stop if anything hurts. If you have a neck or back injury, check with your doctor or physiotherapist first.',
       'published', NOW(), NOW(), NOW()
FROM DUAL WHERE @hc IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `health_tips` WHERE `slug` = '10-minute-desk-stretch-routine');
SET @t := (SELECT `id` FROM `health_tips` WHERE `slug` = '10-minute-desk-stretch-routine' LIMIT 1);
INSERT INTO `health_tip_steps` (`tip_id`, `sort`, `body`)
SELECT @t, x.s, x.b FROM (
    SELECT 0 AS s, 'Neck side tilt: sit tall and gently tilt your right ear towards your right shoulder. Hold, then switch sides.' AS b UNION ALL
    SELECT 1, 'Shoulder rolls: roll both shoulders backwards 10 times, then forwards 10 times.' UNION ALL
    SELECT 2, 'Seated twist: hold the back of the chair with your right hand and turn your upper body to the right. Hold, then switch.' UNION ALL
    SELECT 3, 'Chest opener: clasp your hands behind you, straighten your arms and lift your chest.' UNION ALL
    SELECT 4, 'Wrist stretch: hold one arm out, palm up, and gently pull the fingers back with the other hand. Switch.' UNION ALL
    SELECT 5, 'Seated hip stretch: rest your right ankle on your left knee and lean forward slightly with a straight back. Switch.'
) x WHERE @t IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `health_tip_steps` WHERE `tip_id` = @t);
INSERT IGNORE INTO `health_tip_tag_map` (`tip_id`, `tag_id`)
SELECT @t, `id` FROM `health_tip_tags` WHERE @t IS NOT NULL AND `slug` IN ('desk-friendly', 'posture', 'yoga', 'beginner');

-- X2 ---------------------------------------------------------------
INSERT INTO `health_tips` (`contributor_id`, `type`, `title`, `slug`, `summary`, `body`, `duration_minutes`, `difficulty`, `reps_sets`, `equipment`, `body_area`, `precautions`, `status`, `submitted_at`, `reviewed_at`, `published_at`)
SELECT @hc, 'exercise', 'Wall push-ups for beginners', 'wall-push-ups-for-beginners',
       'The easiest way to start push-ups: using a wall to build upper-body strength with good form.',
       'When 3 sets of 15 feel easy, move to a kitchen counter or a sturdy table to make it harder.',
       8, 'easy', '3 sets × 10 reps, 60 seconds rest', 'A wall', 'Chest, shoulders, arms, core',
       'Keep your back straight and your neck relaxed. Avoid if you have a recent shoulder or wrist injury.',
       'published', NOW(), NOW(), NOW()
FROM DUAL WHERE @hc IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `health_tips` WHERE `slug` = 'wall-push-ups-for-beginners');
SET @t := (SELECT `id` FROM `health_tips` WHERE `slug` = 'wall-push-ups-for-beginners' LIMIT 1);
INSERT INTO `health_tip_steps` (`tip_id`, `sort`, `body`)
SELECT @t, x.s, x.b FROM (
    SELECT 0 AS s, 'Stand an arm’s length from a wall and place your palms on it at shoulder height, a little wider than your shoulders.' AS b UNION ALL
    SELECT 1, 'Keep your body in a straight line from head to heels and tighten your stomach muscles.' UNION ALL
    SELECT 2, 'Bend your elbows and slowly bring your chest towards the wall.' UNION ALL
    SELECT 3, 'Push back to the start. Breathe out as you push.' UNION ALL
    SELECT 4, 'Do 10 slow reps, rest for a minute, and repeat for 3 sets.'
) x WHERE @t IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `health_tip_steps` WHERE `tip_id` = @t);
INSERT IGNORE INTO `health_tip_tag_map` (`tip_id`, `tag_id`)
SELECT @t, `id` FROM `health_tip_tags` WHERE @t IS NOT NULL AND `slug` IN ('strength', 'beginner');

-- X3 ---------------------------------------------------------------
INSERT INTO `health_tips` (`contributor_id`, `type`, `title`, `slug`, `summary`, `body`, `duration_minutes`, `difficulty`, `reps_sets`, `equipment`, `body_area`, `precautions`, `status`, `submitted_at`, `reviewed_at`, `published_at`)
SELECT @hc, 'exercise', 'Chair squats for stronger legs', 'chair-squats-for-stronger-legs',
       'A safe way to learn the squat: sit back towards a chair and stand up again, using your leg muscles.',
       'Go slowly — 3 seconds down, 1 second up. As you get stronger, just touch the chair instead of sitting.',
       10, 'easy', '3 sets × 12 reps, 60 seconds rest', 'A sturdy chair without wheels', 'Legs, hips, core',
       'Keep your knees in line with your toes. If your knees hurt, reduce how low you go. People with knee problems should ask a physiotherapist first.',
       'published', NOW(), NOW(), NOW()
FROM DUAL WHERE @hc IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `health_tips` WHERE `slug` = 'chair-squats-for-stronger-legs');
SET @t := (SELECT `id` FROM `health_tips` WHERE `slug` = 'chair-squats-for-stronger-legs' LIMIT 1);
INSERT INTO `health_tip_steps` (`tip_id`, `sort`, `body`)
SELECT @t, x.s, x.b FROM (
    SELECT 0 AS s, 'Stand in front of a chair with your feet shoulder-width apart.' AS b UNION ALL
    SELECT 1, 'Stretch your arms out in front for balance.' UNION ALL
    SELECT 2, 'Push your hips back and bend your knees to lower yourself slowly onto the chair.' UNION ALL
    SELECT 3, 'Press through your heels to stand up straight again.' UNION ALL
    SELECT 4, 'Repeat 12 times, rest, and do 3 sets.'
) x WHERE @t IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `health_tip_steps` WHERE `tip_id` = @t);
INSERT IGNORE INTO `health_tip_tag_map` (`tip_id`, `tag_id`)
SELECT @t, `id` FROM `health_tip_tags` WHERE @t IS NOT NULL AND `slug` IN ('strength', 'beginner');

-- X4 ---------------------------------------------------------------
INSERT INTO `health_tips` (`contributor_id`, `type`, `title`, `slug`, `summary`, `body`, `duration_minutes`, `difficulty`, `reps_sets`, `equipment`, `body_area`, `precautions`, `status`, `submitted_at`, `reviewed_at`, `published_at`)
SELECT @hc, 'exercise', '30-minute brisk walking plan', '30-minute-brisk-walking-plan',
       'A simple walking routine anyone can start: warm up, walk briskly for 20 minutes, then cool down.',
       '“Brisk” means you can talk but not sing. Morning or evening both work — pick a time you can keep up most days of the week.',
       30, 'easy', '5 min easy + 20 min brisk + 5 min easy', 'Comfortable walking shoes', 'Full body',
       'If you feel chest pain, dizziness or unusual breathlessness, stop and seek medical help. Ask your doctor before starting if you have a heart or lung condition.',
       'published', NOW(), NOW(), NOW()
FROM DUAL WHERE @hc IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `health_tips` WHERE `slug` = '30-minute-brisk-walking-plan');
SET @t := (SELECT `id` FROM `health_tips` WHERE `slug` = '30-minute-brisk-walking-plan' LIMIT 1);
INSERT INTO `health_tip_steps` (`tip_id`, `sort`, `body`)
SELECT @t, x.s, x.b FROM (
    SELECT 0 AS s, 'Warm up: walk at an easy pace for 5 minutes.' AS b UNION ALL
    SELECT 1, 'Speed up to a brisk pace — swing your arms and take quicker steps. Keep this up for 20 minutes.' UNION ALL
    SELECT 2, 'Stand tall, look ahead and let your heel touch the ground first.' UNION ALL
    SELECT 3, 'Cool down: slow back to an easy pace for the last 5 minutes.' UNION ALL
    SELECT 4, 'Finish with a gentle calf and thigh stretch.'
) x WHERE @t IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `health_tip_steps` WHERE `tip_id` = @t);
INSERT IGNORE INTO `health_tip_tag_map` (`tip_id`, `tag_id`)
SELECT @t, `id` FROM `health_tip_tags` WHERE @t IS NOT NULL AND `slug` IN ('cardio', 'beginner');

-- X5 ---------------------------------------------------------------
INSERT INTO `health_tips` (`contributor_id`, `type`, `title`, `slug`, `summary`, `body`, `duration_minutes`, `difficulty`, `reps_sets`, `equipment`, `body_area`, `precautions`, `status`, `submitted_at`, `reviewed_at`, `published_at`)
SELECT @hc, 'exercise', 'Morning cat-cow and child’s pose flow', 'morning-cat-cow-and-childs-pose-flow',
       'A gentle 7-minute yoga flow to wake up the spine and relax the back before the day starts.',
       'Move with your breath: breathe in as you arch, breathe out as you round. Keep the movement slow and comfortable.',
       7, 'easy', '8–10 slow cat-cow rounds, then 1 minute child’s pose', 'A yoga mat or folded blanket', 'Spine, back, hips',
       'Avoid deep bending if you are pregnant, have a recent back injury or surgery — check with your doctor. Use a cushion under your knees if they hurt.',
       'published', NOW(), NOW(), NOW()
FROM DUAL WHERE @hc IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `health_tips` WHERE `slug` = 'morning-cat-cow-and-childs-pose-flow');
SET @t := (SELECT `id` FROM `health_tips` WHERE `slug` = 'morning-cat-cow-and-childs-pose-flow' LIMIT 1);
INSERT INTO `health_tip_steps` (`tip_id`, `sort`, `body`)
SELECT @t, x.s, x.b FROM (
    SELECT 0 AS s, 'Come onto your hands and knees — hands under shoulders, knees under hips.' AS b UNION ALL
    SELECT 1, 'Cow: breathe in, let your stomach sink and lift your chest and gaze gently.' UNION ALL
    SELECT 2, 'Cat: breathe out, round your back towards the ceiling and tuck your chin.' UNION ALL
    SELECT 3, 'Repeat slowly 8–10 times.' UNION ALL
    SELECT 4, 'Child’s pose: sit back on your heels, stretch your arms forward and rest your forehead down for 1 minute.'
) x WHERE @t IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `health_tip_steps` WHERE `tip_id` = @t);
INSERT IGNORE INTO `health_tip_tag_map` (`tip_id`, `tag_id`)
SELECT @t, `id` FROM `health_tip_tags` WHERE @t IS NOT NULL AND `slug` IN ('yoga', 'posture', 'beginner');

-- =====================================================================
-- 2c. FITNESS / HEALTH TIPS (5)
-- =====================================================================
INSERT INTO `health_tips` (`contributor_id`, `type`, `title`, `slug`, `summary`, `body`, `status`, `submitted_at`, `reviewed_at`, `published_at`)
SELECT @hc, x.ty, x.ti, x.sl, x.su, x.bo, 'published', NOW(), NOW(), NOW() FROM (
    SELECT 'tip' AS ty, 'Take a 3-minute movement break every hour' AS ti, 'take-a-3-minute-movement-break-every-hour' AS sl,
           'Long hours of sitting make the body stiff. A short break every hour keeps you moving through the day.' AS su,
           'Set a gentle reminder on your phone or watch. When it goes off, stand up, walk to fill your water bottle, roll your shoulders or do a few stretches. Taking calls while standing or walking is another easy way to add movement to a busy workday.' AS bo
    UNION ALL
    SELECT 'tip', 'Add a protein source to every meal', 'add-a-protein-source-to-every-meal',
           'Instead of one large protein meal, spread protein across breakfast, lunch and dinner.',
           'Easy vegetarian options: dal, chana, rajma, sprouts, paneer, curd, buttermilk, soya and peanuts. If you eat eggs, an egg at breakfast is a simple start. Aim to see at least one of these on your plate at every meal.'
    UNION ALL
    SELECT 'tip', 'Keep a water bottle within reach', 'keep-a-water-bottle-within-reach',
           'People drink more water through the day when it’s right in front of them.',
           'Keep a refillable bottle on your desk, in your bag and by your bed. Sip regularly rather than drinking a lot at once. Water needs change with the weather and how active you are — on hot days or after exercise, drink a little more. Fruits like watermelon, cucumber and oranges add water too.'
    UNION ALL
    SELECT 'tip', 'Go to bed at the same time every night', 'go-to-bed-at-the-same-time-every-night',
           'A regular bedtime and wake-up time is one of the simplest sleep habits to build.',
           'Pick a bedtime you can keep on most days, including weekends. Put screens away 30 minutes before bed and keep the room dark and cool. Most adults feel their best with 7–9 hours of sleep. If you snore loudly or feel very tired despite enough sleep, talk to a doctor.'
    UNION ALL
    SELECT 'tip', 'Fill half your plate with vegetables', 'fill-half-your-plate-with-vegetables',
           'A simple way to plan any meal: half vegetables, a quarter protein and a quarter grains.',
           'In an Indian thali, that could be sabzi and salad on half the plate, dal or paneer on a quarter, and roti or rice on the rest. Try to include different colours through the week — greens, carrots, beetroot, tomatoes and pumpkin each bring something different.'
) x
WHERE @hc IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `health_tips` h WHERE h.`slug` = x.sl COLLATE utf8mb4_unicode_ci);

INSERT IGNORE INTO `health_tip_tag_map` (`tip_id`, `tag_id`)
SELECT h.`id`, g.`id` FROM `health_tips` h JOIN `health_tip_tags` g
WHERE (h.`slug` = 'take-a-3-minute-movement-break-every-hour' AND g.`slug` IN ('desk-friendly', 'beginner'))
   OR (h.`slug` = 'add-a-protein-source-to-every-meal' AND g.`slug` IN ('high-protein', 'vegetarian'))
   OR (h.`slug` = 'keep-a-water-bottle-within-reach' AND g.`slug` IN ('drink'))
   OR (h.`slug` = 'fill-half-your-plate-with-vegetables' AND g.`slug` IN ('high-fibre', 'lunch', 'dinner'));

-- =====================================================================
-- 3. FREE CAMPS — December 2026, PUBLISHED under eClinicPro Desk.
--    Standing free Government of India programmes; venues are the
--    government facilities that run them. No phone number is stored —
--    people should contact their nearest government health facility.
-- =====================================================================
INSERT INTO `wh_events` (`creator_id`, `kind`, `category_slug`, `title`, `slug`, `summary`, `description`, `start_at`, `end_at`, `mode`,
                         `venue_name`, `address`, `city_name`, `city_slug`, `state`, `fee_amount`, `join_mode`, `register_url`,
                         `audience`, `languages`, `organised_by`, `status`, `submitted_at`, `reviewed_at`, `published_at`)
SELECT @desk, 'free_camp', x.cat, x.ti, x.sl, x.su, x.de, x.st, x.en, 'in_person', x.ve, x.ad, x.cn, x.cs, x.sta, 0, 'walk_in', NULL,
       x.au, x.la, x.ob, 'published', NOW(), NOW(), NOW()
FROM (
    SELECT 'womens-health' AS cat,
           'Free antenatal check-up for pregnant women (PMSMA) — 9 December' AS ti,
           'free-antenatal-checkup-pmsma-ahmedabad-dec-2026' AS sl,
           'Free antenatal check-up by a doctor for pregnant women at government health facilities in Ahmedabad on 9 December.' AS su,
           'Under the Pradhan Mantri Surakshit Matritva Abhiyan (PMSMA), pregnant women — especially in the 2nd and 3rd trimester — can get a free antenatal check-up by a specialist or medical officer on the 9th of every month at government hospitals, community health centres and primary health centres.\n\nWhat to carry: your Mother & Child Protection (MCP) card and any earlier reports.\n\nNo booking is usually needed — confirm timings with your nearest government health facility.\n\nSource: Ministry of Health & Family Welfare — https://pmsma.mohfw.gov.in/' AS de,
           '2026-12-09 09:00:00' AS st, '2026-12-09 16:00:00' AS en,
           'Government hospitals, CHCs and PHCs' AS ve, 'Participating government health facilities across Ahmedabad' AS ad,
           'Ahmedabad' AS cn, @c_ahd AS cs, 'Gujarat' AS sta, 'women' AS au, 'Gujarati, Hindi, English' AS la,
           'Ministry of Health & Family Welfare — PMSMA' AS ob
    UNION ALL
    SELECT 'womens-health',
           'Free antenatal check-up for pregnant women (PMSMA) — 9 December',
           'free-antenatal-checkup-pmsma-surat-dec-2026',
           'Free antenatal check-up by a doctor for pregnant women at government health facilities in Surat on 9 December.',
           'Under the Pradhan Mantri Surakshit Matritva Abhiyan (PMSMA), pregnant women — especially in the 2nd and 3rd trimester — can get a free antenatal check-up by a specialist or medical officer on the 9th of every month at government hospitals, community health centres and primary health centres.\n\nWhat to carry: your Mother & Child Protection (MCP) card and any earlier reports.\n\nNo booking is usually needed — confirm timings with your nearest government health facility.\n\nSource: Ministry of Health & Family Welfare — https://pmsma.mohfw.gov.in/',
           '2026-12-09 09:00:00', '2026-12-09 16:00:00',
           'Government hospitals, CHCs and PHCs', 'Participating government health facilities across Surat',
           'Surat', @c_srt, 'Gujarat', 'women', 'Gujarati, Hindi, English',
           'Ministry of Health & Family Welfare — PMSMA'
    UNION ALL
    SELECT 'general-checkup',
           'World AIDS Day: free, confidential HIV counselling and testing',
           'world-aids-day-free-hiv-testing-mumbai-dec-2026',
           'Free and confidential HIV counselling and testing at government ICTC centres in Mumbai — on World AIDS Day (1 December) and all year.',
           'Integrated Counselling and Testing Centres (ICTCs) at government hospitals offer free, confidential HIV counselling and testing. Results are shared privately, and counsellors can guide you on next steps.\n\nWorld AIDS Day is on 1 December — a good reminder to get tested and to know your status. The service is available on working days through the year.\n\nAsk at the ICTC of your nearest government hospital.\n\nSource: National AIDS Control Organisation (NACO) — https://naco.gov.in/' AS de,
           '2026-12-01 10:00:00', '2026-12-01 16:00:00',
           'Government hospital ICTC centres', 'ICTC centres at government hospitals across Mumbai',
           'Mumbai', @c_mum, 'Maharashtra', 'all', 'Marathi, Hindi, English',
           'NACO — Integrated Counselling & Testing Centres'
    UNION ALL
    SELECT 'diabetes-bp',
           'Free screening for diabetes, BP and common cancers (30+ years) — December',
           'free-ncd-screening-ayushman-arogya-mandir-vadodara-dec-2026',
           'Adults aged 30 and above can get free screening for high blood pressure, diabetes and common cancers at Ayushman Arogya Mandir centres in Vadodara.',
           'Under the National Health Mission, Ayushman Arogya Mandir centres (formerly Health & Wellness Centres) offer free population-based screening for people aged 30 and above: blood pressure, blood sugar, and oral, breast and cervical cancer screening.\n\nWhat to carry: Aadhaar or any ID, and earlier reports if you have them.\n\nVisit your nearest Ayushman Arogya Mandir or primary health centre on a working day and confirm timings locally.\n\nSource: National Health Mission — https://nhm.gov.in/' AS de,
           '2026-12-01 09:00:00', '2026-12-31 16:00:00',
           'Ayushman Arogya Mandir centres', 'Ayushman Arogya Mandir and primary health centres across Vadodara',
           'Vadodara', @c_vad, 'Gujarat', 'all', 'Gujarati, Hindi',
           'National Health Mission — Ayushman Arogya Mandir'
    UNION ALL
    SELECT 'vaccination',
           'Free vaccines for children and pregnant women — routine immunisation days',
           'free-routine-immunisation-new-delhi-dec-2026',
           'Free vaccines under the Universal Immunisation Programme for babies, children and pregnant women at government health centres in Delhi.',
           'The Universal Immunisation Programme gives the recommended vaccines free of cost to babies, children and pregnant women at government hospitals, dispensaries and health centres on their routine immunisation days.\n\nWhat to carry: the child’s vaccination card (or MCP card) so the staff can see which doses are due.\n\nAsk your nearest government health centre for its immunisation days this month.\n\nSource: Ministry of Health & Family Welfare — https://www.mohfw.gov.in/' AS de,
           '2026-12-01 09:00:00', '2026-12-31 16:00:00',
           'Government health centres and dispensaries', 'Government immunisation centres across Delhi',
           'New Delhi', @c_del, 'Delhi', 'children', 'Hindi, English',
           'Universal Immunisation Programme — MoHFW'
) x
WHERE @desk IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `wh_events` e WHERE e.`slug` = x.sl COLLATE utf8mb4_unicode_ci);

-- =====================================================================
-- 4. EVENTS — December 2026, SUBMITTED (waiting for your review).
--    From event-listing websites: CONFIRM dates, timings and ticket prices
--    on the organiser's site, edit them in admin, then Publish.
--    fee_amount is 0 for now = would show "Free" — fix before publishing.
-- =====================================================================
INSERT INTO `wh_events` (`creator_id`, `kind`, `category_slug`, `title`, `slug`, `summary`, `description`, `start_at`, `end_at`, `mode`,
                         `venue_name`, `address`, `city_name`, `city_slug`, `state`, `fee_amount`, `fee_note`, `join_mode`, `register_url`,
                         `audience`, `languages`, `organised_by`, `status`, `submitted_at`, `review_note`)
SELECT @desk, 'event', x.cat, x.ti, x.sl, x.su, x.de, x.st, x.en, 'in_person', x.ve, x.ad, x.cn, x.cs, x.sta, 0,
       'Ticket price not confirmed — check the organiser’s site', 'register', x.ru, x.au, 'English', x.ob, 'submitted', NOW(),
       'SEED: verify date, timings and fee on the source link before publishing.'
FROM (
    SELECT 'health-expo' AS cat,
           'IHFF — International Health, Sports & Fitness Festival' AS ti, 'ihff-health-sports-fitness-festival-mumbai-dec-2026' AS sl,
           'A three-day festival on fitness, sports and nutrition at the Bombay Exhibition Centre, Goregaon.' AS su,
           'The International Health, Sports & Fitness Festival brings together gym and fitness equipment, sports nutrition, wellness brands and fitness professionals, with exhibits and sessions over three days.\n\nListed dates: 18–20 December 2026. Please confirm dates, timings and entry details on the organiser’s website before you go.\n\nListing: https://visitexpo.in/event/ihff-international-health-sports-fitness-festival-mumbai-2026' AS de,
           '2026-12-18 10:00:00' AS st, '2026-12-20 18:00:00' AS en,
           'Bombay Exhibition Centre (NESCO)' AS ve, 'Western Express Highway, Goregaon East' AS ad, 'Mumbai' AS cn, @c_mum AS cs, 'Maharashtra' AS sta,
           'https://visitexpo.in/event/ihff-international-health-sports-fitness-festival-mumbai-2026' AS ru, 'all' AS au,
           'IHFF (International Health, Sports & Fitness Festival)' AS ob
    UNION ALL
    SELECT 'marathon-walkathon',
           'Fit Mumbai Marathon', 'fit-mumbai-marathon-dec-2026',
           'A city running event in Mumbai with multiple race distances, listed for Sunday, 6 December 2026.',
           'Running calendars list the Fit Mumbai Marathon for 6 December 2026. Check the official race page for distances, start time, route and registration fees before signing up.\n\nListing: https://fatmarathoner.com/upcoming-marathon-calendar-india/',
           '2026-12-06 05:30:00', '2026-12-06 10:00:00',
           NULL, NULL, 'Mumbai', @c_mum, 'Maharashtra',
           'https://fatmarathoner.com/upcoming-marathon-calendar-india/', 'all',
           'Fit Mumbai Marathon organisers'
    UNION ALL
    SELECT 'workshop',
           'International Workshop on Acupuncture and Mental Wellness', 'international-workshop-acupuncture-mental-wellness-bengaluru-dec-2026',
           'A one-day workshop in Whitefield, Bengaluru, for professionals, academics and students interested in acupuncture and mental wellness.',
           'Listed for 13 December 2026 at a hotel in Whitefield, Bengaluru. Aimed at industry professionals, experts, academicians and students. Confirm the venue, timings and fees on the listing before registering.\n\nListing: https://internationalconferencealerts.com/event-iwamw-bangalore-in-202612-101283603',
           '2026-12-13 09:00:00', '2026-12-13 17:00:00',
           'Whitefield (hotel venue — see listing)', 'Whitefield', 'Bengaluru', @c_blr, 'Karnataka',
           'https://internationalconferencealerts.com/event-iwamw-bangalore-in-202612-101283603', 'all',
           'International Conference Alerts (listing)'
    UNION ALL
    SELECT 'conference',
           'National Conclave on Type 1 Diabetes', 'national-conclave-type-1-diabetes-bengaluru-dec-2026',
           'A two-day national conclave on Type 1 diabetes care in Bengaluru, listed for 19–20 December 2026.',
           'Event listings show a National Conclave for Type 1 Diabetes in Bengaluru on 19–20 December 2026. Confirm the organiser, venue, audience (doctors / families) and registration on the listing before you go.\n\nListing: https://www.freeconferencealerts.com/bangalore/health',
           '2026-12-19 09:00:00', '2026-12-20 17:00:00',
           NULL, NULL, 'Bengaluru', @c_blr, 'Karnataka',
           'https://www.freeconferencealerts.com/bangalore/health', 'all',
           'See listing'
    UNION ALL
    SELECT 'conference',
           'Nutriwell Conclave', 'nutriwell-conclave-new-delhi-dec-2026',
           'A two-day conclave on nutrition and wellness in New Delhi, listed for 17–18 December 2026.',
           'Event listings show the Nutriwell Conclave in New Delhi on 17–18 December 2026, covering nutrition and wellness. Confirm the venue, sessions and registration on the listing before you go.\n\nListing: https://10times.com/newdelhi-in/wellness-healthcare',
           '2026-12-17 09:30:00', '2026-12-18 17:30:00',
           NULL, NULL, 'New Delhi', @c_del, 'Delhi',
           'https://10times.com/newdelhi-in/wellness-healthcare', 'all',
           'See listing'
) x
WHERE @desk IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `wh_events` e WHERE e.`slug` = x.sl COLLATE utf8mb4_unicode_ci);

-- Refresh the sitemap cache so the new pages are listed.
-- (Then run: php workers/sitemap_build.php)

-- =====================================================================
-- REMOVE THE DEMO CONTENT LATER (uncomment and run):
-- =====================================================================
-- DELETE m FROM health_tip_tag_map m JOIN health_tips h ON h.id = m.tip_id WHERE h.contributor_id = (SELECT id FROM health_tip_contributors WHERE slug = 'eclinicpro-team');
-- DELETE s FROM health_tip_steps s JOIN health_tips h ON h.id = s.tip_id WHERE h.contributor_id = (SELECT id FROM health_tip_contributors WHERE slug = 'eclinicpro-team');
-- DELETE i FROM health_tip_ingredients i JOIN health_tips h ON h.id = i.tip_id WHERE h.contributor_id = (SELECT id FROM health_tip_contributors WHERE slug = 'eclinicpro-team');
-- DELETE FROM health_tips WHERE contributor_id = (SELECT id FROM health_tip_contributors WHERE slug = 'eclinicpro-team');
-- DELETE FROM wh_events WHERE slug IN (
--   'free-antenatal-checkup-pmsma-ahmedabad-dec-2026','free-antenatal-checkup-pmsma-surat-dec-2026','world-aids-day-free-hiv-testing-mumbai-dec-2026',
--   'free-ncd-screening-ayushman-arogya-mandir-vadodara-dec-2026','free-routine-immunisation-new-delhi-dec-2026',
--   'ihff-health-sports-fitness-festival-mumbai-dec-2026','fit-mumbai-marathon-dec-2026','international-workshop-acupuncture-mental-wellness-bengaluru-dec-2026',
--   'national-conclave-type-1-diabetes-bengaluru-dec-2026','nutriwell-conclave-new-delhi-dec-2026');
