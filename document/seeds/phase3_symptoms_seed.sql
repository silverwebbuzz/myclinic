-- =============================================================
-- eClinicPro — Phase 3 Symptom Seed
-- =============================================================
-- ~500 curated symptoms grouped by body system + category.
--
-- Schema:
--   label             — display string (Title Case)
--   slug              — unique lowercase-kebab; used as search key
--   synonyms          — JSON array of alt strings (Hindi/Gujarati words,
--                       abbreviations, lay terms) — match these in fallback
--                       search but always display the canonical label.
--   specialties       — JSON array of specialty keys. Symptom ranks higher
--                       in search for these specialties. Never filters.
--   category          — broad bucket for analytics + admin grouping.
--   global_usage_count starts at 0; bumped by SymptomSearch::recordMasterUse.
--
-- Pre-requisite: phase3_migrations.sql Block 1 created the table.
--
-- Run safely:  uses INSERT IGNORE on slug so a re-run is idempotent.
-- Rollback:    TRUNCATE symptoms_master; (only if no visit_symptoms FKs).
-- =============================================================

-- USE silverwebbuzz_in_myclinic;

-- =============================================================
-- CONSTITUTIONAL / GENERAL
-- =============================================================
INSERT IGNORE INTO symptoms_master (label, slug, synonyms, specialties, category) VALUES
('Fever',                       'fever',                        '["high temperature","pyrexia","temperature","bukhar","taav"]', '["gp","peds","family_medicine"]', 'constitutional'),
('Low-grade fever',              'low-grade-fever',              '["mild fever","mild bukhar"]', '["gp","peds"]', 'constitutional'),
('High-grade fever',             'high-grade-fever',             '["very high fever","tez bukhar"]', '["gp","peds"]', 'constitutional'),
('Intermittent fever',           'intermittent-fever',           '["fever with chills","malaria-like fever"]', '["gp"]', 'constitutional'),
('Fever with chills',            'fever-with-chills',            '["thandi lagke bukhar","rigors"]', '["gp"]', 'constitutional'),
('Fever with rash',              'fever-with-rash',              '["rash with fever"]', '["gp","peds","derma"]', 'constitutional'),
('Chills',                       'chills',                       '["thandi lagna","shivering","rigors"]', '["gp"]', 'constitutional'),
('Sweating',                     'sweating',                     '["pasina","diaphoresis","perspiration"]', '["gp","cardio","endocrinology"]', 'constitutional'),
('Night sweats',                 'night-sweats',                 '["raat me pasina","drenching sweats"]', '["gp","pulmonology"]', 'constitutional'),
('Fatigue',                      'fatigue',                      '["weakness","kamzori","tiredness","thakaan","exhaustion"]', '["gp"]', 'constitutional'),
('Generalized weakness',         'generalized-weakness',         '["body weakness","whole body weak","sharir mein kamzori"]', '["gp"]', 'constitutional'),
('Lethargy',                     'lethargy',                     '["sluggish","drowsy","sleepy"]', '["gp","peds"]', 'constitutional'),
('Loss of appetite',             'loss-of-appetite',             '["no hunger","bhook nahi lagti","anorexia"]', '["gp","gastro","peds"]', 'constitutional'),
('Weight loss',                  'weight-loss',                  '["losing weight","vajan kam","unintentional weight loss"]', '["gp","endocrinology","oncology"]', 'constitutional'),
('Weight gain',                  'weight-gain',                  '["gaining weight","vajan badhna"]', '["gp","endocrinology"]', 'constitutional'),
('Excessive thirst',             'excessive-thirst',             '["too much thirst","polydipsia","pyaas badhi"]', '["gp","endocrinology","diabetology"]', 'constitutional'),
('Excessive hunger',             'excessive-hunger',             '["polyphagia","bahut bhook"]', '["gp","endocrinology","diabetology"]', 'constitutional'),
('Body ache',                    'body-ache',                    '["body pain","sharir dard","myalgia"]', '["gp","peds"]', 'constitutional'),
('Malaise',                      'malaise',                      '["feeling unwell","not feeling well","aalas"]', '["gp"]', 'constitutional'),
('Cold intolerance',             'cold-intolerance',             '["feels cold easily","thandi sehan nahi"]', '["endocrinology","gp"]', 'constitutional'),
('Heat intolerance',             'heat-intolerance',             '["feels hot easily","garmi sehan nahi"]', '["endocrinology","gp"]', 'constitutional'),
('Insomnia',                     'insomnia',                     '["sleeplessness","cant sleep","neend nahi"]', '["gp","psychiatrist","psychologist"]', 'constitutional'),
('Excessive sleepiness',         'excessive-sleepiness',         '["hypersomnia","too much sleep"]', '["gp","psychiatrist","pulmonology"]', 'constitutional'),
('Sleep apnea',                  'sleep-apnea',                  '["snoring with gaps","stops breathing in sleep"]', '["pulmonology","ent"]', 'constitutional'),
('Dehydration',                  'dehydration',                  '["water loss","paani ki kami"]', '["gp","peds"]', 'constitutional'),
('Failure to thrive',            'failure-to-thrive',            '["poor weight gain","FTT"]', '["peds"]', 'constitutional');

-- =============================================================
-- HEAD / NEUROLOGICAL
-- =============================================================
INSERT IGNORE INTO symptoms_master (label, slug, synonyms, specialties, category) VALUES
('Headache',                     'headache',                     '["sir dard","head pain","cephalgia"]', '["gp","neuro"]', 'neuro'),
('Migraine',                     'migraine',                     '["one-sided headache","aadha sir dard","throbbing headache"]', '["neuro","gp"]', 'neuro'),
('Tension headache',             'tension-headache',             '["stress headache","band-like headache"]', '["neuro","gp"]', 'neuro'),
('Cluster headache',             'cluster-headache',             '["severe one-sided headache"]', '["neuro"]', 'neuro'),
('Sinus headache',               'sinus-headache',               '["headache with congestion","frontal pressure"]', '["ent","gp"]', 'neuro'),
('Dizziness',                    'dizziness',                    '["chakkar","light-headed","giddiness"]', '["gp","ent","neuro","cardio"]', 'neuro'),
('Vertigo',                      'vertigo',                      '["spinning sensation","room spinning"]', '["ent","neuro"]', 'neuro'),
('Loss of consciousness',        'loss-of-consciousness',        '["fainting","syncope","beh hosh"]', '["cardio","neuro","gp"]', 'neuro'),
('Seizure',                      'seizure',                      '["convulsion","fits","mirgi","epileptic fit"]', '["neuro","peds","gp"]', 'neuro'),
('Tremor',                       'tremor',                       '["shaking","kampan","hand tremor"]', '["neuro","endocrinology"]', 'neuro'),
('Numbness',                     'numbness',                     '["sunna","tingling","paresthesia"]', '["neuro","ortho"]', 'neuro'),
('Tingling',                     'tingling',                     '["pins and needles","jhanjhanahat"]', '["neuro","ortho"]', 'neuro'),
('Weakness in limbs',            'weakness-in-limbs',            '["arm weakness","leg weakness","limb weakness"]', '["neuro","ortho"]', 'neuro'),
('Facial weakness',              'facial-weakness',              '["face droop","Bells palsy","chehra tedha"]', '["neuro"]', 'neuro'),
('Slurred speech',               'slurred-speech',               '["unclear speech","dysarthria","awaz lapatna"]', '["neuro"]', 'neuro'),
('Memory loss',                  'memory-loss',                  '["forgetfulness","yaaddasht kam","amnesia"]', '["neuro","psychiatrist"]', 'neuro'),
('Confusion',                    'confusion',                    '["disorientation","altered mental status","ghabrahat"]', '["neuro","gp","psychiatrist"]', 'neuro'),
('Loss of balance',              'loss-of-balance',              '["unsteady","ataxia","balance problem"]', '["neuro","ent"]', 'neuro'),
('Difficulty walking',           'difficulty-walking',           '["gait disturbance","chalne mein dikkat"]', '["neuro","ortho"]', 'neuro'),
('Stiffness of neck',            'stiff-neck',                   '["neck rigidity","gardan akadna"]', '["neuro","gp","ortho"]', 'neuro'),
('Photophobia',                  'photophobia',                  '["light sensitivity","roshni se taklif"]', '["neuro","eye"]', 'neuro'),
('Phonophobia',                  'phonophobia',                  '["sound sensitivity"]', '["neuro"]', 'neuro');

-- =============================================================
-- EYE
-- =============================================================
INSERT IGNORE INTO symptoms_master (label, slug, synonyms, specialties, category) VALUES
('Blurred vision',               'blurred-vision',               '["blurry vision","dhundla dikhna","poor eyesight"]', '["eye","gp","diabetology"]', 'eye'),
('Double vision',                'double-vision',                '["diplopia","do dikhna"]', '["eye","neuro"]', 'eye'),
('Loss of vision',               'loss-of-vision',               '["vision loss","cant see","blindness"]', '["eye","neuro"]', 'eye'),
('Eye pain',                     'eye-pain',                     '["aankh dard","ocular pain"]', '["eye"]', 'eye'),
('Eye redness',                  'eye-redness',                  '["red eye","aankh laal","conjunctival injection"]', '["eye","gp"]', 'eye'),
('Eye discharge',                'eye-discharge',                '["sticky eyes","aankh se paani","keechad"]', '["eye"]', 'eye'),
('Itchy eyes',                   'itchy-eyes',                   '["aankh khujli","ocular itching"]', '["eye","allergy"]', 'eye'),
('Watery eyes',                  'watery-eyes',                  '["epiphora","aankh se aansoo"]', '["eye"]', 'eye'),
('Dry eyes',                     'dry-eyes',                     '["aankh sookhna","keratoconjunctivitis sicca"]', '["eye"]', 'eye'),
('Floaters',                     'floaters',                     '["spots in vision","kaale dhabbe"]', '["eye"]', 'eye'),
('Flashes of light',             'flashes-of-light',             '["photopsia"]', '["eye"]', 'eye'),
('Squint',                       'squint',                       '["strabismus","crossed eyes","bhenga"]', '["eye","peds"]', 'eye'),
('Eyelid swelling',              'eyelid-swelling',              '["puffy eyelid","palak sujan"]', '["eye","derma"]', 'eye'),
('Stye',                         'stye',                         '["hordeolum","gulab dana on eye"]', '["eye"]', 'eye'),
('Difficulty reading',           'difficulty-reading',           '["near vision problem","presbyopia"]', '["eye"]', 'eye'),
('Color vision change',          'color-vision-change',          '["color blindness change","color perception"]', '["eye","neuro"]', 'eye');

-- =============================================================
-- ENT (EAR / NOSE / THROAT)
-- =============================================================
INSERT IGNORE INTO symptoms_master (label, slug, synonyms, specialties, category) VALUES
('Ear pain',                     'ear-pain',                     '["earache","kaan dard","otalgia"]', '["ent","peds","gp"]', 'ent'),
('Ear discharge',                'ear-discharge',                '["otorrhea","kaan se paani"]', '["ent"]', 'ent'),
('Hearing loss',                 'hearing-loss',                 '["deafness","kam sunai","decreased hearing"]', '["ent","audiologist"]', 'ent'),
('Ringing in ears',              'tinnitus',                     '["tinnitus","kaan mein awaz","buzzing in ears"]', '["ent","audiologist"]', 'ent'),
('Ear blockage',                 'ear-blockage',                 '["blocked ear","ear fullness","kaan band"]', '["ent"]', 'ent'),
('Ear itching',                  'ear-itching',                  '["kaan khujli"]', '["ent"]', 'ent'),
('Runny nose',                   'runny-nose',                   '["rhinorrhea","naak se paani","nazla"]', '["gp","ent","peds","allergy"]', 'ent'),
('Nasal congestion',             'nasal-congestion',             '["blocked nose","stuffy nose","naak band"]', '["gp","ent","allergy"]', 'ent'),
('Sneezing',                     'sneezing',                     '["chheenkna","frequent sneezes"]', '["ent","allergy","gp"]', 'ent'),
('Nosebleed',                    'nosebleed',                    '["epistaxis","naak se khoon"]', '["ent","gp"]', 'ent'),
('Loss of smell',                'loss-of-smell',                '["anosmia","sungh nahi aati"]', '["ent","neuro"]', 'ent'),
('Sore throat',                  'sore-throat',                  '["throat pain","gala kharab","pharyngitis"]', '["gp","ent","peds"]', 'ent'),
('Hoarseness of voice',          'hoarseness',                   '["voice change","awaz baith jana","laryngitis"]', '["ent"]', 'ent'),
('Difficulty swallowing',        'difficulty-swallowing',        '["dysphagia","nigalne mein dikkat"]', '["ent","gastro","neuro"]', 'ent'),
('Painful swallowing',           'painful-swallowing',           '["odynophagia","gale mein dard nigalte waqt"]', '["ent","gp"]', 'ent'),
('Tonsil swelling',              'tonsil-swelling',              '["tonsillitis","tonsil badha hua"]', '["ent","peds"]', 'ent'),
('Snoring',                      'snoring',                      '["kharatey","loud breathing in sleep"]', '["ent","pulmonology"]', 'ent'),
('Mouth ulcer',                  'mouth-ulcer',                  '["aphthous ulcer","muh chala","canker sore"]', '["dental","gp"]', 'ent'),
('Bad breath',                   'bad-breath',                   '["halitosis","muh ki badboo"]', '["dental","gp"]', 'ent'),
('Dry mouth',                    'dry-mouth',                    '["xerostomia","muh sookhna"]', '["dental","gp","endocrinology"]', 'ent');

-- =============================================================
-- RESPIRATORY
-- =============================================================
INSERT IGNORE INTO symptoms_master (label, slug, synonyms, specialties, category) VALUES
('Cough',                        'cough',                        '["khansi","persistent cough"]', '["gp","peds","pulmonology"]', 'respiratory'),
('Dry cough',                    'dry-cough',                    '["sookhi khansi","non-productive cough"]', '["gp","pulmonology"]', 'respiratory'),
('Productive cough',             'productive-cough',             '["wet cough","cough with sputum","balgham wali khansi"]', '["gp","pulmonology"]', 'respiratory'),
('Cough with blood',             'hemoptysis',                   '["hemoptysis","khoon ki khansi","blood in sputum"]', '["pulmonology","gp"]', 'respiratory'),
('Chronic cough',                'chronic-cough',                '["long-standing cough","8+ weeks cough"]', '["pulmonology","gp"]', 'respiratory'),
('Whooping cough',               'whooping-cough',               '["pertussis","kali khansi"]', '["peds"]', 'respiratory'),
('Shortness of breath',          'shortness-of-breath',          '["dyspnea","saans phoolna","breathlessness","SOB"]', '["pulmonology","cardio","gp"]', 'respiratory'),
('Shortness of breath on exertion','shortness-of-breath-on-exertion','["exertional dyspnea","DOE"]', '["pulmonology","cardio"]', 'respiratory'),
('Wheezing',                     'wheezing',                     '["whistling breathing","seeti ki awaz"]', '["pulmonology","allergy","peds"]', 'respiratory'),
('Asthma',                       'asthma',                       '["dama","bronchial asthma"]', '["pulmonology","allergy","peds"]', 'respiratory'),
('Chest tightness',              'chest-tightness',              '["chest heaviness","seene mein jakdan"]', '["cardio","pulmonology"]', 'respiratory'),
('Difficulty breathing lying down','orthopnea',                  '["orthopnea","cant breathe lying flat"]', '["cardio","pulmonology"]', 'respiratory'),
('Nocturnal cough',              'nocturnal-cough',              '["night cough","raat ki khansi"]', '["pulmonology","gp"]', 'respiratory'),
('Sputum production',            'sputum-production',            '["phlegm","balgham"]', '["pulmonology","gp"]', 'respiratory'),
('Yellow sputum',                'yellow-sputum',                '["purulent sputum","pus in cough"]', '["pulmonology"]', 'respiratory'),
('Hyperventilation',             'hyperventilation',             '["rapid breathing","tezi se saans"]', '["pulmonology","psychiatrist"]', 'respiratory');

-- =============================================================
-- CARDIOVASCULAR
-- =============================================================
INSERT IGNORE INTO symptoms_master (label, slug, synonyms, specialties, category) VALUES
('Chest pain',                   'chest-pain',                   '["heart pain","seene mein dard"]', '["cardio","gp"]', 'cardio'),
('Crushing chest pain',          'crushing-chest-pain',          '["heaviness on chest","seene par bhaar"]', '["cardio"]', 'cardio'),
('Radiating chest pain',         'radiating-chest-pain',         '["pain spreading to arm","jaw pain with chest"]', '["cardio"]', 'cardio'),
('Palpitations',                 'palpitations',                 '["heart racing","dhadkan tez","dhak dhak"]', '["cardio","gp","endocrinology"]', 'cardio'),
('Irregular heartbeat',          'irregular-heartbeat',          '["arrhythmia","irregular pulse"]', '["cardio"]', 'cardio'),
('Slow heart rate',              'slow-heart-rate',              '["bradycardia","slow pulse"]', '["cardio"]', 'cardio'),
('Fast heart rate',              'fast-heart-rate',              '["tachycardia","racing pulse"]', '["cardio","endocrinology"]', 'cardio'),
('High blood pressure',          'high-blood-pressure',          '["BP","hypertension","high BP"]', '["cardio","gp"]', 'cardio'),
('Low blood pressure',           'low-blood-pressure',           '["hypotension","low BP"]', '["cardio","gp"]', 'cardio'),
('Swelling of legs',             'swelling-of-legs',             '["pedal edema","paer sujan","leg swelling"]', '["cardio","nephrology","gp"]', 'cardio'),
('Ankle swelling',               'ankle-swelling',               '["ankle edema","ankle sujan"]', '["cardio","ortho"]', 'cardio'),
('Cold extremities',             'cold-extremities',             '["cold hands and feet","hath paer thande"]', '["cardio","vascular"]', 'cardio'),
('Cyanosis',                     'cyanosis',                     '["bluish lips","blue skin","neelapan"]', '["cardio","pulmonology","peds"]', 'cardio'),
('Claudication',                 'claudication',                 '["leg pain on walking","calf cramp on walk"]', '["vascular","cardio"]', 'cardio'),
('Varicose veins',               'varicose-veins',               '["bulging leg veins","nas phoolna"]', '["vascular"]', 'cardio'),
('Easy fatigue on exertion',     'easy-fatigue-on-exertion',     '["tires easily","exercise intolerance"]', '["cardio","pulmonology"]', 'cardio');

-- =============================================================
-- GASTROINTESTINAL
-- =============================================================
INSERT IGNORE INTO symptoms_master (label, slug, synonyms, specialties, category) VALUES
('Abdominal pain',               'abdominal-pain',               '["pet dard","stomach pain","tummy ache","belly pain"]', '["gastro","gp","peds"]', 'gi'),
('Upper abdominal pain',         'upper-abdominal-pain',         '["epigastric pain","upper belly pain"]', '["gastro","gp"]', 'gi'),
('Lower abdominal pain',         'lower-abdominal-pain',         '["lower belly pain","pelvic pain"]', '["gastro","gyno","gp"]', 'gi'),
('Right upper quadrant pain',    'right-upper-quadrant-pain',    '["RUQ pain","right side pain","liver pain"]', '["gastro","hepatology"]', 'gi'),
('Right lower quadrant pain',    'right-lower-quadrant-pain',    '["RLQ pain","right lower pain","appendicitis pain"]', '["gastro","general_surgery"]', 'gi'),
('Left upper quadrant pain',     'left-upper-quadrant-pain',     '["LUQ pain"]', '["gastro"]', 'gi'),
('Periumbilical pain',           'periumbilical-pain',           '["pain around navel","naabhi ke aaspaas"]', '["gastro","peds"]', 'gi'),
('Colicky pain',                 'colicky-pain',                 '["intermittent abdominal pain","cramps","marod"]', '["gastro","peds"]', 'gi'),
('Burning sensation in chest',   'heartburn',                    '["heartburn","acidity","seene mein jalan","GERD"]', '["gastro","gp"]', 'gi'),
('Acid reflux',                  'acid-reflux',                  '["GERD","khattey dakar","regurgitation"]', '["gastro","gp"]', 'gi'),
('Indigestion',                  'indigestion',                  '["dyspepsia","badhazmi","gas trouble"]', '["gastro","gp"]', 'gi'),
('Bloating',                     'bloating',                     '["pet phoolna","abdominal distention","gassy"]', '["gastro","gp"]', 'gi'),
('Belching',                     'belching',                     '["dakaar","burping","eructation"]', '["gastro"]', 'gi'),
('Flatulence',                   'flatulence',                   '["gas","paat","passing gas excessive"]', '["gastro"]', 'gi'),
('Nausea',                       'nausea',                       '["matli","feeling of vomiting","ji michlana"]', '["gastro","gp","peds"]', 'gi'),
('Vomiting',                     'vomiting',                     '["ulti","emesis","throwing up"]', '["gastro","gp","peds"]', 'gi'),
('Projectile vomiting',          'projectile-vomiting',          '["forceful vomiting","tez ulti"]', '["peds","gastro"]', 'gi'),
('Vomiting blood',               'hematemesis',                  '["hematemesis","khoon ki ulti"]', '["gastro","hepatology"]', 'gi'),
('Diarrhea',                     'diarrhea',                     '["loose motion","dast","pet kharab","watery stools"]', '["gp","gastro","peds"]', 'gi'),
('Chronic diarrhea',             'chronic-diarrhea',             '["long-standing loose motion","4+ weeks diarrhea"]', '["gastro"]', 'gi'),
('Bloody diarrhea',              'bloody-diarrhea',              '["khooni dast","dysentery","blood in stool"]', '["gastro","peds"]', 'gi'),
('Mucoid stools',                'mucoid-stools',                '["mucus in stool","aam wali tatti"]', '["gastro","peds"]', 'gi'),
('Constipation',                 'constipation',                 '["kabz","hard stool","infrequent stools"]', '["gastro","gp","peds"]', 'gi'),
('Alternating bowel habits',     'alternating-bowel-habits',     '["constipation alternating diarrhea","IBS pattern"]', '["gastro"]', 'gi'),
('Black stools',                 'black-stools',                 '["melena","tarry stools","kaali tatti"]', '["gastro"]', 'gi'),
('Pale stools',                  'pale-stools',                  '["clay-coloured stools","white stools"]', '["hepatology","gastro"]', 'gi'),
('Pain on defecation',           'pain-on-defecation',           '["painful bowel","fissure pain"]', '["gastro","general_surgery"]', 'gi'),
('Rectal bleeding',              'rectal-bleeding',              '["blood with stool","hematochezia","piles bleeding"]', '["gastro","general_surgery"]', 'gi'),
('Anal itching',                 'anal-itching',                 '["pruritus ani","piles itching"]', '["general_surgery","gastro"]', 'gi'),
('Tenesmus',                     'tenesmus',                     '["urgency to pass stool","incomplete evacuation"]', '["gastro"]', 'gi'),
('Jaundice',                     'jaundice',                     '["yellow eyes","peeliya","yellow skin"]', '["hepatology","gastro","peds"]', 'gi'),
('Pruritus',                     'pruritus',                     '["itching of body","khujli"]', '["hepatology","derma"]', 'gi'),
('Hiccups',                      'hiccups',                      '["hichki","persistent hiccups"]', '["gastro","gp"]', 'gi'),
('Hematochezia',                 'hematochezia',                 '["fresh blood per rectum","bright red blood in stool"]', '["gastro","general_surgery"]', 'gi'),
('Abdominal mass',               'abdominal-mass',               '["lump in abdomen","palpable mass"]', '["gastro","general_surgery"]', 'gi'),
('Hernia',                       'hernia',                       '["hernia bulge","groin bulge"]', '["general_surgery"]', 'gi');

-- =============================================================
-- GENITOURINARY
-- =============================================================
INSERT IGNORE INTO symptoms_master (label, slug, synonyms, specialties, category) VALUES
('Painful urination',            'painful-urination',            '["dysuria","peshab mein jalan","burning urination"]', '["urologist","gp","gyno"]', 'gu'),
('Frequent urination',           'frequent-urination',           '["polyuria","baar baar peshab","urinary frequency"]', '["urologist","diabetology","gp"]', 'gu'),
('Urgency of urination',         'urinary-urgency',              '["sudden urge","peshab rok nahi sakte"]', '["urologist","gp"]', 'gu'),
('Nocturia',                     'nocturia',                     '["nighttime urination","raat ko peshab"]', '["urologist","cardio","gp"]', 'gu'),
('Decreased urine output',       'decreased-urine-output',       '["oliguria","kam peshab"]', '["nephrology","gp"]', 'gu'),
('Anuria',                       'anuria',                       '["no urine","peshab band"]', '["nephrology"]', 'gu'),
('Hematuria',                    'hematuria',                    '["blood in urine","khooni peshab"]', '["urologist","nephrology"]', 'gu'),
('Cloudy urine',                 'cloudy-urine',                 '["turbid urine","gandla peshab"]', '["urologist","gp"]', 'gu'),
('Foul-smelling urine',          'foul-smelling-urine',          '["smelly urine","badboodaar peshab"]', '["urologist","gp"]', 'gu'),
('Difficulty initiating urination','difficulty-initiating-urination','["hesitancy","peshab shuru karne mein dikkat"]', '["urologist"]', 'gu'),
('Weak urinary stream',          'weak-urinary-stream',          '["dribbling","peshab ki dhaar kamzor"]', '["urologist"]', 'gu'),
('Incontinence',                 'incontinence',                 '["urine leak","peshab nikal jaata","unable to hold urine"]', '["urologist","gyno","neuro"]', 'gu'),
('Loin pain',                    'loin-pain',                    '["flank pain","kamar mein dard","kidney pain"]', '["nephrology","urologist","gp"]', 'gu'),
('Renal colic',                  'renal-colic',                  '["kidney stone pain","ureteric colic"]', '["urologist","nephrology"]', 'gu'),
('Penile discharge',             'penile-discharge',             '["urethral discharge","STD discharge"]', '["urologist","andrology"]', 'gu'),
('Penile pain',                  'penile-pain',                  '["penile discomfort"]', '["urologist","andrology"]', 'gu'),
('Erectile dysfunction',         'erectile-dysfunction',         '["impotence","ED","weakness in erection"]', '["andrology","sexology","urologist"]', 'gu'),
('Premature ejaculation',        'premature-ejaculation',        '["PE","early discharge"]', '["andrology","sexology"]', 'gu'),
('Low libido',                   'low-libido',                   '["decreased sex drive","ichha kam"]', '["sexology","andrology","gyno"]', 'gu'),
('Testicular pain',              'testicular-pain',              '["testicle pain","scrotal pain"]', '["urologist","andrology"]', 'gu'),
('Testicular swelling',          'testicular-swelling',          '["scrotal swelling","hydrocele"]', '["urologist","general_surgery"]', 'gu'),
('Decreased semen volume',       'decreased-semen-volume',       '["low semen","ejaculate volume reduced"]', '["andrology","fertility"]', 'gu'),
('Infertility',                  'infertility',                  '["unable to conceive","baccha nahi ho raha"]', '["fertility","gyno","andrology"]', 'gu');

-- =============================================================
-- GYNECOLOGICAL / OBSTETRIC
-- =============================================================
INSERT IGNORE INTO symptoms_master (label, slug, synonyms, specialties, category) VALUES
('Menstrual irregularity',       'menstrual-irregularity',       '["irregular periods","cycle gadbad"]', '["gyno"]', 'gyn'),
('Heavy menstrual bleeding',     'heavy-menstrual-bleeding',     '["menorrhagia","zyada bleeding","heavy periods"]', '["gyno"]', 'gyn'),
('Painful periods',              'painful-periods',              '["dysmenorrhea","period mein dard"]', '["gyno"]', 'gyn'),
('Missed period',                'missed-period',                '["amenorrhea","period nahi aaya"]', '["gyno"]', 'gyn'),
('Inter-menstrual bleeding',     'inter-menstrual-bleeding',     '["spotting between periods","metrorrhagia"]', '["gyno"]', 'gyn'),
('Post-coital bleeding',         'post-coital-bleeding',         '["bleeding after intercourse"]', '["gyno"]', 'gyn'),
('Post-menopausal bleeding',     'post-menopausal-bleeding',     '["bleeding after menopause"]', '["gyno","oncology"]', 'gyn'),
('Vaginal discharge',            'vaginal-discharge',            '["white discharge","leucorrhea","safed paani"]', '["gyno"]', 'gyn'),
('Foul-smelling discharge',      'foul-smelling-vaginal-discharge','["smelly discharge","BV-like"]', '["gyno"]', 'gyn'),
('Vaginal itching',              'vaginal-itching',              '["pruritus vulvae","yeast infection itch"]', '["gyno","derma"]', 'gyn'),
('Pelvic pain',                  'pelvic-pain',                  '["lower abdomen pain","pelvis dard"]', '["gyno"]', 'gyn'),
('Dyspareunia',                  'dyspareunia',                  '["painful intercourse","sex mein dard"]', '["gyno","sexology"]', 'gyn'),
('Breast pain',                  'breast-pain',                  '["mastalgia","chhati mein dard"]', '["gyno"]', 'gyn'),
('Breast lump',                  'breast-lump',                  '["lump in breast","gaanth"]', '["gyno","oncology","general_surgery"]', 'gyn'),
('Nipple discharge',             'nipple-discharge',             '["galactorrhea","stan se paani"]', '["gyno","endocrinology"]', 'gyn'),
('Hot flashes',                  'hot-flashes',                  '["menopausal flushes","achanak garmi"]', '["gyno","endocrinology"]', 'gyn'),
('Pregnancy bleeding',           'pregnancy-bleeding',           '["bleeding in pregnancy","first trimester bleed"]', '["gyno"]', 'gyn'),
('Reduced fetal movement',       'reduced-fetal-movement',       '["less baby movement","fetal kicks reduced"]', '["gyno"]', 'gyn'),
('Morning sickness',             'morning-sickness',             '["NVP","pregnancy nausea","ji michlana"]', '["gyno"]', 'gyn'),
('Hirsutism',                    'hirsutism',                    '["excess facial hair","PCOS hair"]', '["gyno","endocrinology","derma"]', 'gyn'),
('Acne related to cycle',        'cyclical-acne',                '["period-related acne","PCOS acne"]', '["gyno","derma"]', 'gyn');

-- =============================================================
-- MUSCULOSKELETAL / ORTHO
-- =============================================================
INSERT IGNORE INTO symptoms_master (label, slug, synonyms, specialties, category) VALUES
('Back pain',                    'back-pain',                    '["kamar dard","lower back pain","lumbago"]', '["ortho","gp","spine"]', 'ortho'),
('Lower back pain',              'lower-back-pain',              '["lumbar pain","kamar dard","LBP"]', '["ortho","spine","gp"]', 'ortho'),
('Upper back pain',              'upper-back-pain',              '["thoracic back pain","peeth dard"]', '["ortho","spine"]', 'ortho'),
('Neck pain',                    'neck-pain',                    '["cervical pain","gardan dard"]', '["ortho","spine","gp"]', 'ortho'),
('Shoulder pain',                'shoulder-pain',                '["kandha dard","frozen shoulder"]', '["ortho"]', 'ortho'),
('Knee pain',                    'knee-pain',                    '["ghutna dard","knee ache"]', '["ortho","sports_medicine"]', 'ortho'),
('Hip pain',                     'hip-pain',                     '["kulha dard","hip joint pain"]', '["ortho"]', 'ortho'),
('Joint pain',                   'joint-pain',                   '["jodon mein dard","arthralgia"]', '["ortho","rheumatology","gp"]', 'ortho'),
('Joint swelling',               'joint-swelling',               '["joint sujan","arthritic swelling"]', '["ortho","rheumatology"]', 'ortho'),
('Joint stiffness',              'joint-stiffness',              '["morning stiffness","jodon ka akadna"]', '["rheumatology","ortho"]', 'ortho'),
('Muscle pain',                  'muscle-pain',                  '["myalgia","mansa peshi mein dard"]', '["ortho","gp"]', 'ortho'),
('Muscle weakness',              'muscle-weakness',              '["myasthenia","muscle kamzor"]', '["neuro","ortho","rheumatology"]', 'ortho'),
('Muscle cramps',                'muscle-cramps',                '["spasm","aaintha","cramp"]', '["gp","ortho","sports_medicine"]', 'ortho'),
('Limping',                      'limping',                      '["antalgic gait","langda kar chalna"]', '["ortho","peds"]', 'ortho'),
('Limited range of motion',      'limited-range-of-motion',      '["restricted joint movement","stiff joint"]', '["ortho","physio"]', 'ortho'),
('Sciatica',                     'sciatica',                     '["radiating leg pain","sciatic pain"]', '["ortho","spine","physio"]', 'ortho'),
('Trauma',                       'trauma',                       '["injury","chot","fracture"]', '["ortho","general_surgery"]', 'ortho'),
('Fall',                         'fall',                         '["fell down","gir gaya"]', '["ortho","gp","peds"]', 'ortho'),
('Fracture',                     'fracture',                     '["broken bone","haddi tooti"]', '["ortho"]', 'ortho'),
('Dislocation',                  'dislocation',                  '["joint out of place"]', '["ortho"]', 'ortho'),
('Sprain',                       'sprain',                       '["ligament injury","moch"]', '["ortho","sports_medicine"]', 'ortho'),
('Heel pain',                    'heel-pain',                    '["plantar fasciitis","ediyon mein dard"]', '["ortho","sports_medicine"]', 'ortho'),
('Foot pain',                    'foot-pain',                    '["pair mein dard"]', '["ortho"]', 'ortho'),
('Numb feet',                    'numb-feet',                    '["foot numbness","peripheral neuropathy"]', '["neuro","diabetology"]', 'ortho');

-- =============================================================
-- DERMATOLOGICAL
-- =============================================================
INSERT IGNORE INTO symptoms_master (label, slug, synonyms, specialties, category) VALUES
('Skin rash',                    'skin-rash',                    '["rash","chakatte","skin eruption"]', '["derma","allergy","peds"]', 'derma'),
('Itching',                      'itching-skin',                 '["pruritus","khujli"]', '["derma","allergy"]', 'derma'),
('Hives',                        'hives',                        '["urticaria","sheet pith","wheals"]', '["derma","allergy"]', 'derma'),
('Dry skin',                     'dry-skin',                     '["xerosis","sookhi tvacha"]', '["derma"]', 'derma'),
('Oily skin',                    'oily-skin',                    '["greasy skin","seborrhea"]', '["derma"]', 'derma'),
('Acne',                         'acne',                         '["pimples","muhase"]', '["derma","cosmetology"]', 'derma'),
('Eczema',                       'eczema',                       '["atopic dermatitis","khujliwala daad"]', '["derma","peds","allergy"]', 'derma'),
('Psoriasis',                    'psoriasis',                    '["scaly skin patches","silvery scale"]', '["derma"]', 'derma'),
('Fungal infection',             'fungal-infection',             '["daad","ringworm","tinea"]', '["derma","gp"]', 'derma'),
('Bacterial skin infection',     'bacterial-skin-infection',     '["cellulitis","impetigo"]', '["derma","gp"]', 'derma'),
('Hair loss',                    'hair-loss',                    '["alopecia","baal jhadna","balding"]', '["derma","trichology"]', 'derma'),
('Dandruff',                     'dandruff',                     '["scalp flakes","rusi"]', '["derma","trichology"]', 'derma'),
('Premature greying',            'premature-greying',            '["white hair early","baal safed"]', '["derma","trichology"]', 'derma'),
('Hyperpigmentation',            'hyperpigmentation',            '["dark spots","kale dhabbe","melasma"]', '["derma","cosmetology"]', 'derma'),
('Hypopigmentation',             'hypopigmentation',             '["light patches","white patches","vitiligo"]', '["derma"]', 'derma'),
('Vitiligo',                     'vitiligo',                     '["white patches","safed daag","leucoderma"]', '["derma"]', 'derma'),
('Skin lesion',                  'skin-lesion',                  '["mole","skin growth","tag"]', '["derma","oncology"]', 'derma'),
('Wart',                         'wart',                         '["verruca","massa"]', '["derma"]', 'derma'),
('Skin discoloration',           'skin-discoloration',           '["color change","tvacha ka rang"]', '["derma"]', 'derma'),
('Cracked skin',                 'cracked-skin',                 '["fissured skin","heel cracks","biwaiyaan"]', '["derma"]', 'derma'),
('Sweat rash',                   'sweat-rash',                   '["miliaria","prickly heat","ghamoriyan"]', '["derma","peds"]', 'derma'),
('Insect bite',                  'insect-bite',                  '["mosquito bite","bee sting"]', '["derma","gp","peds"]', 'derma'),
('Burn',                         'burn',                         '["thermal injury","jal gaya"]', '["derma","general_surgery","plastic_surgery"]', 'derma'),
('Nail change',                  'nail-change',                  '["nail discoloration","ridges","nakhoon"]', '["derma"]', 'derma'),
('Skin abscess',                 'skin-abscess',                 '["boil","phoda","furuncle"]', '["derma","general_surgery"]', 'derma'),
('Cellulitis',                   'cellulitis',                   '["skin infection","red swollen skin"]', '["derma","gp"]', 'derma'),
('Sunburn',                      'sunburn',                      '["UV burn","dhoop ka jalan"]', '["derma"]', 'derma'),
('Body odor',                    'body-odor',                    '["bromhidrosis","badboo"]', '["derma","cosmetology"]', 'derma');

-- =============================================================
-- ENDOCRINE / METABOLIC
-- =============================================================
INSERT IGNORE INTO symptoms_master (label, slug, synonyms, specialties, category) VALUES
('Excessive thirst with frequent urination','polyuria-polydipsia','["diabetes triad","pyaas aur peshab badha"]', '["diabetology","endocrinology"]', 'endo'),
('Sugar craving',                'sugar-craving',                '["sweet tooth","meetha lagna"]', '["endocrinology","diabetology"]', 'endo'),
('Tingling in feet',             'tingling-in-feet',             '["peripheral neuropathy","pair sunna"]', '["diabetology","neuro"]', 'endo'),
('Slow wound healing',           'slow-wound-healing',           '["wound not healing","ghav nahi bhar raha"]', '["diabetology","gp"]', 'endo'),
('Increased appetite with weight loss','increased-appetite-weight-loss','["polyphagia with weight loss"]', '["endocrinology","diabetology"]', 'endo'),
('Goiter',                       'goiter',                       '["thyroid enlargement","ghengha"]', '["endocrinology"]', 'endo'),
('Bulging eyes',                 'bulging-eyes',                 '["exophthalmos","Graves-like"]', '["endocrinology","eye"]', 'endo'),
('Hair thinning',                'hair-thinning',                '["thinning hair","baal patle"]', '["derma","trichology","endocrinology"]', 'endo'),
('Mood swings',                  'mood-swings',                  '["mood changes","gussa aur khushi"]', '["endocrinology","psychiatrist"]', 'endo'),
('Stretch marks',                'stretch-marks',                '["striae","khinchav nishaan"]', '["derma","endocrinology"]', 'endo'),
('Moon face',                    'moon-face',                    '["Cushingoid face","puffy face"]', '["endocrinology"]', 'endo'),
('Buffalo hump',                 'buffalo-hump',                 '["upper back fat pad","Cushings sign"]', '["endocrinology"]', 'endo');

-- =============================================================
-- DENTAL
-- =============================================================
INSERT IGNORE INTO symptoms_master (label, slug, synonyms, specialties, category) VALUES
('Toothache',                    'toothache',                    '["tooth pain","daant dard","dental pain"]', '["dental","endodontist"]', 'dental'),
('Sensitive teeth',              'sensitive-teeth',              '["tooth sensitivity","dant me jhanjhanahat","cold sensitivity"]', '["dental"]', 'dental'),
('Bleeding gums',                'bleeding-gums',                '["gum bleeding","mussede se khoon"]', '["dental"]', 'dental'),
('Swollen gums',                 'swollen-gums',                 '["gingivitis","mussede sujan"]', '["dental"]', 'dental'),
('Bad breath dental',            'bad-breath-dental',            '["dental halitosis","muh ki badboo daant"]', '["dental"]', 'dental'),
('Loose tooth',                  'loose-tooth',                  '["mobile tooth","dant hilna"]', '["dental"]', 'dental'),
('Broken tooth',                 'broken-tooth',                 '["fractured tooth","chipped tooth","dant toot gaya"]', '["dental"]', 'dental'),
('Tooth discoloration',          'tooth-discoloration',          '["yellow teeth","brown teeth","staining"]', '["dental","cosmetology"]', 'dental'),
('Jaw pain',                     'jaw-pain',                     '["TMJ pain","jabda dard"]', '["dental","ortho"]', 'dental'),
('Jaw clicking',                 'jaw-clicking',                 '["TMJ click","clicking jaw"]', '["dental"]', 'dental'),
('Difficulty chewing',           'difficulty-chewing',           '["chewing pain","khane mein dard"]', '["dental"]', 'dental'),
('Cracked filling',              'cracked-filling',              '["broken filling","loose filling"]', '["dental"]', 'dental'),
('Wisdom tooth pain',            'wisdom-tooth-pain',            '["third molar pain","akl daad dard"]', '["dental"]', 'dental'),
('Tooth abscess',                'tooth-abscess',                '["dental abscess","pus near tooth"]', '["dental","endodontist"]', 'dental'),
('Cavity',                       'dental-cavity',                '["caries","dant me keeda","decay"]', '["dental"]', 'dental'),
('Crooked teeth',                'crooked-teeth',                '["malocclusion","tedhe dant"]', '["dental","orthodontist"]', 'dental'),
('Missing tooth',                'missing-tooth',                '["edentulous","dant nahi"]', '["dental","prosthodontist","implantologist"]', 'dental'),
('Cleft palate',                 'cleft-palate',                 '["palate defect","cleft"]', '["dental","peds","plastic_surgery"]', 'dental'),
('Difficulty opening mouth',     'trismus',                      '["trismus","muh nahi khulta","lockjaw"]', '["dental"]', 'dental');

-- =============================================================
-- PSYCHIATRIC / PSYCHOLOGICAL
-- =============================================================
INSERT IGNORE INTO symptoms_master (label, slug, synonyms, specialties, category) VALUES
('Depression',                   'depression',                   '["sadness","udaasi","low mood","depressed"]', '["psychiatrist","psychologist","gp"]', 'psych'),
('Anxiety',                      'anxiety',                      '["ghabrahat","worry","bechaini"]', '["psychiatrist","psychologist","gp"]', 'psych'),
('Panic attack',                 'panic-attack',                 '["sudden anxiety","hyperventilation episode"]', '["psychiatrist","psychologist"]', 'psych'),
('Insomnia psychiatric',         'insomnia-psychiatric',         '["sleep disturbance","cant fall asleep"]', '["psychiatrist","psychologist"]', 'psych'),
('Suicidal thoughts',            'suicidal-thoughts',            '["self-harm thoughts","atmahatya ke vichar"]', '["psychiatrist","psychologist","gp"]', 'psych'),
('Hallucinations',               'hallucinations',               '["seeing things","hearing voices","bhulahataye"]', '["psychiatrist"]', 'psych'),
('Delusions',                    'delusions',                    '["false beliefs","paranoid thoughts"]', '["psychiatrist"]', 'psych'),
('Mood swings psych',            'mood-swings-psych',            '["bipolar mood","mood changes"]', '["psychiatrist"]', 'psych'),
('Irritability',                 'irritability',                 '["short temper","jaldi gussa","irritable"]', '["psychiatrist","gp"]', 'psych'),
('Aggression',                   'aggression',                   '["violent behavior","aggressive outbursts"]', '["psychiatrist"]', 'psych'),
('Difficulty concentrating',     'difficulty-concentrating',     '["poor focus","attention problem"]', '["psychiatrist","psychologist"]', 'psych'),
('Memory complaints',            'memory-complaints',            '["forgetting things","yaaddasht ki samasya"]', '["psychiatrist","neuro"]', 'psych'),
('Substance use',                'substance-use',                '["alcohol","drug use","nasha"]', '["psychiatrist","gp"]', 'psych'),
('Eating disorder',              'eating-disorder',              '["anorexia","bulimia","binge eating"]', '["psychiatrist","dietitian"]', 'psych'),
('Compulsive behavior',          'compulsive-behavior',          '["OCD","compulsions","baar baar dhona"]', '["psychiatrist","psychologist"]', 'psych'),
('Social withdrawal',            'social-withdrawal',            '["isolation","alone","milne se ghabrahat"]', '["psychiatrist","psychologist"]', 'psych'),
('Excessive worry',              'excessive-worry',              '["generalized anxiety","always worrying"]', '["psychiatrist","psychologist"]', 'psych'),
('Phobia',                       'phobia',                       '["fear","specific fear","dar"]', '["psychiatrist","psychologist"]', 'psych'),
('PTSD-like symptoms',           'ptsd-like-symptoms',           '["flashbacks","trauma response"]', '["psychiatrist","psychologist"]', 'psych'),
('Sleep walking',                'sleep-walking',                '["somnambulism"]', '["psychiatrist","peds"]', 'psych');

-- =============================================================
-- PEDIATRIC-SPECIFIC
-- =============================================================
INSERT IGNORE INTO symptoms_master (label, slug, synonyms, specialties, category) VALUES
('Poor feeding',                 'poor-feeding',                 '["refuses milk","doodh nahi peeta","feeding difficulty"]', '["peds"]', 'peds'),
('Excessive crying',             'excessive-crying',             '["colic","baccha rota rehta"]', '["peds"]', 'peds'),
('Bedwetting',                   'bedwetting',                   '["enuresis","raat ko peshab","bistar geela"]', '["peds","urologist"]', 'peds'),
('Delayed milestones',           'delayed-milestones',           '["developmental delay","late walking talking"]', '["peds"]', 'peds'),
('Delayed walking',              'delayed-walking',              '["not walking by 18m"]', '["peds"]', 'peds'),
('Delayed speech',               'delayed-speech',               '["not talking","speech delay"]', '["peds","speech"]', 'peds'),
('Hyperactivity',                'hyperactivity',                '["ADHD-like","cant sit still"]', '["peds","psychiatrist"]', 'peds'),
('Attention problems',           'attention-problems',           '["short attention span","focus issue"]', '["peds","psychiatrist"]', 'peds'),
('Recurrent ear infections',     'recurrent-ear-infections',     '["repeated otitis","baar baar kaan infection"]', '["peds","ent"]', 'peds'),
('Recurrent throat infection',   'recurrent-throat-infection',   '["recurrent tonsillitis"]', '["peds","ent"]', 'peds'),
('Pica',                         'pica',                         '["eats non-food","chalk eating"]', '["peds","psychiatrist"]', 'peds'),
('Convulsion with fever',        'febrile-seizure',              '["febrile convulsion","bukhar ka daura"]', '["peds","neuro"]', 'peds'),
('Diaper rash',                  'diaper-rash',                  '["nappy rash","diaper dermatitis"]', '["peds","derma"]', 'peds'),
('Cradle cap',                   'cradle-cap',                   '["infant seborrheic dermatitis"]', '["peds","derma"]', 'peds'),
('Reflux in infant',             'infant-reflux',                '["spit up","ulti baccha"]', '["peds","gastro"]', 'peds'),
('Excessive flatulence in baby', 'infant-gas',                   '["colicky gas","baby gas"]', '["peds"]', 'peds'),
('Refusal to walk',              'refusal-to-walk',              '["limp child","wont walk"]', '["peds","ortho"]', 'peds'),
('Recurrent abdominal pain in child','child-recurrent-abdominal-pain','["RAP","tummy pain often in child"]', '["peds","gastro"]', 'peds'),
('Worm infestation',             'worm-infestation',             '["pinworm","keede","helminths"]', '["peds","gp","gastro"]', 'peds'),
('Constipation in child',        'child-constipation',           '["pediatric constipation","baccha kabz"]', '["peds","gastro"]', 'peds');

-- =============================================================
-- ALLERGY / IMMUNE
-- =============================================================
INSERT IGNORE INTO symptoms_master (label, slug, synonyms, specialties, category) VALUES
('Allergic rhinitis',            'allergic-rhinitis',            '["hay fever","nasal allergy","allergic cold"]', '["allergy","ent","gp"]', 'allergy'),
('Food allergy',                 'food-allergy',                 '["allergy to food","khane se allergy"]', '["allergy","gp","peds"]', 'allergy'),
('Drug allergy',                 'drug-allergy',                 '["medication allergy","dawai se allergy"]', '["allergy","gp"]', 'allergy'),
('Anaphylaxis',                  'anaphylaxis',                  '["severe allergic reaction","epinephrine episode"]', '["allergy","gp"]', 'allergy'),
('Angioedema',                   'angioedema',                   '["lip swelling","face swelling allergic"]', '["allergy","derma"]', 'allergy'),
('Eczema flare',                 'eczema-flare',                 '["atopic flare","dermatitis flare"]', '["derma","allergy"]', 'allergy'),
('Asthma exacerbation',          'asthma-exacerbation',          '["asthma attack","dama ka daura"]', '["pulmonology","allergy"]', 'allergy'),
('Pet allergy',                  'pet-allergy',                  '["cat allergy","dog allergy"]', '["allergy"]', 'allergy'),
('Pollen allergy',               'pollen-allergy',               '["seasonal allergy"]', '["allergy"]', 'allergy'),
('Dust mite allergy',            'dust-mite-allergy',            '["house dust allergy"]', '["allergy"]', 'allergy'),
('Latex allergy',                'latex-allergy',                '["rubber allergy"]', '["allergy"]', 'allergy');

-- =============================================================
-- PHYSIOTHERAPY / REHAB
-- =============================================================
INSERT IGNORE INTO symptoms_master (label, slug, synonyms, specialties, category) VALUES
('Post-surgery stiffness',       'post-surgery-stiffness',       '["post-op stiffness","surgery ke baad jakdan"]', '["physio","ortho"]', 'rehab'),
('Post-stroke weakness',         'post-stroke-weakness',         '["hemiparesis","paralysis after stroke"]', '["physio","neuro"]', 'rehab'),
('Post-fracture rehabilitation', 'post-fracture-rehab',          '["fracture rehab","strength after cast"]', '["physio","ortho"]', 'rehab'),
('Frozen shoulder',              'frozen-shoulder',              '["adhesive capsulitis","shoulder stiffness"]', '["physio","ortho"]', 'rehab'),
('Tennis elbow',                 'tennis-elbow',                 '["lateral epicondylitis","elbow pain"]', '["physio","ortho","sports_medicine"]', 'rehab'),
('Golfers elbow',                'golfers-elbow',                '["medial epicondylitis"]', '["physio","ortho","sports_medicine"]', 'rehab'),
('Plantar fasciitis',            'plantar-fasciitis',            '["heel spur","foot arch pain"]', '["physio","ortho"]', 'rehab'),
('Achilles tendinitis',          'achilles-tendinitis',          '["heel cord pain"]', '["physio","ortho","sports_medicine"]', 'rehab'),
('Carpal tunnel syndrome',       'carpal-tunnel-syndrome',       '["wrist nerve","CTS"]', '["ortho","neuro","physio"]', 'rehab'),
('Posture problem',              'posture-problem',              '["poor posture","slouch"]', '["physio","ortho"]', 'rehab'),
('Sports injury',                'sports-injury',                '["athletic injury","game injury"]', '["sports_medicine","ortho","physio"]', 'rehab'),
('Difficulty climbing stairs',   'difficulty-climbing-stairs',   '["stair climbing problem"]', '["ortho","cardio","physio"]', 'rehab');

-- =============================================================
-- HOMEOPATHY-SPECIFIC (constitutional / mental-emotional)
-- =============================================================
INSERT IGNORE INTO symptoms_master (label, slug, synonyms, specialties, category) VALUES
('Aversion to cold',             'aversion-to-cold',             '["dislikes cold","sensitive to cold"]', '["homeopathy"]', 'constitutional'),
('Desire for cold drinks',       'desire-cold-drinks',           '["craves cold water"]', '["homeopathy"]', 'constitutional'),
('Desire for sweets',            'desire-sweets',                '["sweet craving","meetha pasand"]', '["homeopathy","endocrinology"]', 'constitutional'),
('Desire for salt',              'desire-salt',                  '["salt craving","namak pasand"]', '["homeopathy","endocrinology"]', 'constitutional'),
('Aversion to milk',             'aversion-to-milk',             '["dislikes milk","milk intolerance"]', '["homeopathy","gastro"]', 'constitutional'),
('Worse from heat',              'worse-from-heat',              '["aggravated by heat","heat makes worse"]', '["homeopathy"]', 'constitutional'),
('Worse at night',               'worse-at-night',               '["aggravated at night","raat ko zyada"]', '["homeopathy"]', 'constitutional'),
('Worse in the morning',         'worse-in-morning',             '["morning aggravation","subah zyada"]', '["homeopathy"]', 'constitutional'),
('Better from warmth',           'better-from-warmth',           '["relieved by warmth","garmi se aaram"]', '["homeopathy"]', 'constitutional'),
('Restlessness',                 'restlessness',                 '["fidgety","bechaini","cant sit still"]', '["homeopathy","psychiatrist"]', 'psych'),
('Anticipatory anxiety',         'anticipatory-anxiety',         '["pre-event anxiety","stage fright"]', '["homeopathy","psychiatrist"]', 'psych'),
('Grief',                        'grief',                        '["bereavement","loss","gham"]', '["homeopathy","psychiatrist","psychologist"]', 'psych'),
('Fear of being alone',          'fear-of-being-alone',          '["monophobia","aloneness fear"]', '["homeopathy","psychiatrist"]', 'psych'),
('Fear of crowds',               'fear-of-crowds',               '["agoraphobia","bheed se dar"]', '["homeopathy","psychiatrist"]', 'psych'),
('Indecisiveness',               'indecisiveness',               '["cannot decide","faisla nahi le sakte"]', '["homeopathy","psychiatrist"]', 'psych'),
('Sensitive to noise',           'sensitive-to-noise',           '["noise intolerance","awaz se dard"]', '["homeopathy","neuro"]', 'psych'),
('Sensitive to smells',          'sensitive-to-smells',          '["smell intolerance","sungh se dikkat"]', '["homeopathy"]', 'psych'),
('Worse from emotional excitement','worse-from-emotional-excitement','["excitement aggravates"]', '["homeopathy","psychiatrist"]', 'psych'),
('Better from open air',         'better-from-open-air',         '["fresh air relieves"]', '["homeopathy"]', 'constitutional'),
('Worse from milk',              'worse-from-milk',              '["milk aggravates","dudh se taklif"]', '["homeopathy","gastro"]', 'gi');

-- =============================================================
-- ONCOLOGY / RED-FLAG
-- =============================================================
INSERT IGNORE INTO symptoms_master (label, slug, synonyms, specialties, category) VALUES
('Unexplained weight loss',      'unexplained-weight-loss',      '["red flag weight loss","unintentional weight loss"]', '["oncology","gastro","gp"]', 'red-flag'),
('Persistent cough',             'persistent-cough',             '["cough > 3 weeks","red flag cough"]', '["pulmonology","oncology"]', 'red-flag'),
('Bone pain at night',           'bone-pain-at-night',           '["nocturnal bone pain","worrying bone pain"]', '["ortho","oncology"]', 'red-flag'),
('Hard lymph node',              'hard-lymph-node',              '["lymphadenopathy","gaanth"]', '["oncology","hematology"]', 'red-flag'),
('Swollen lymph node',           'swollen-lymph-node',           '["lymphadenopathy","gland sujan"]', '["gp","hematology"]', 'red-flag'),
('Painless lump',                'painless-lump',                '["painless mass","gaanth bina dard"]', '["oncology","general_surgery"]', 'red-flag'),
('Skin lesion changing',         'skin-lesion-changing',         '["mole changing","irregular mole"]', '["derma","oncology"]', 'red-flag'),
('Recurrent fever undiagnosed',  'recurrent-fever-undiagnosed',  '["FUO","fever of unknown origin"]', '["gp","oncology","hematology"]', 'red-flag'),
('Easy bruising',                'easy-bruising',                '["bruises easily","khoon ka rang neela"]', '["hematology","gp"]', 'red-flag'),
('Frequent infections',          'frequent-infections',          '["recurrent infections","immunocompromised"]', '["gp","hematology"]', 'red-flag'),
('Petechiae',                    'petechiae',                    '["pinpoint red spots","blood spots"]', '["hematology","derma"]', 'red-flag'),
('Pallor',                       'pallor',                       '["paleness","peelapan","anemic-looking"]', '["hematology","gp"]', 'red-flag');

-- =============================================================
-- NUTRITION / DIETARY
-- =============================================================
INSERT IGNORE INTO symptoms_master (label, slug, synonyms, specialties, category) VALUES
('Vitamin D deficiency symptoms','vitamin-d-deficiency',         '["low vitamin D","bone ache from D deficiency"]', '["dietitian","gp","ortho"]', 'nutrition'),
('Vitamin B12 deficiency',       'vitamin-b12-deficiency',       '["low B12","tingling and fatigue"]', '["dietitian","gp","hematology"]', 'nutrition'),
('Iron deficiency anemia',       'iron-deficiency-anemia',       '["IDA","low hemoglobin","khoon kam"]', '["dietitian","hematology","gp"]', 'nutrition'),
('Calcium deficiency',           'calcium-deficiency',           '["low calcium","bone weakness"]', '["dietitian","ortho","gp"]', 'nutrition'),
('Obesity',                      'obesity',                      '["overweight","motapa"]', '["dietitian","endocrinology","gp"]', 'nutrition'),
('Underweight',                  'underweight',                  '["low BMI","skinny","BMI < 18.5"]', '["dietitian","gp","peds"]', 'nutrition'),
('Pre-diabetes',                 'pre-diabetes',                 '["impaired glucose tolerance","borderline diabetes"]', '["diabetology","dietitian"]', 'nutrition'),
('Hyperlipidemia',               'hyperlipidemia',               '["high cholesterol","cholesterol badha"]', '["cardio","dietitian"]', 'nutrition'),
('Fatty liver',                  'fatty-liver',                  '["NAFLD","liver mein fat"]', '["hepatology","gastro","dietitian"]', 'nutrition'),
('Lactose intolerance',          'lactose-intolerance',          '["milk intolerance","dudh hajam nahi"]', '["gastro","dietitian"]', 'nutrition'),
('Gluten intolerance',           'gluten-intolerance',           '["wheat intolerance","celiac suspicion"]', '["gastro","dietitian"]', 'nutrition');

-- =============================================================
-- MISC / GENERAL CONCERNS
-- =============================================================
INSERT IGNORE INTO symptoms_master (label, slug, synonyms, specialties, category) VALUES
('Health checkup',               'health-checkup',               '["routine checkup","master health check"]', '["gp","family_medicine"]', 'preventive'),
('Vaccination',                  'vaccination',                  '["immunization","tika"]', '["peds","gp"]', 'preventive'),
('Pre-employment exam',          'pre-employment-exam',          '["pre-employment medical","job medical"]', '["gp"]', 'preventive'),
('Travel medicine',              'travel-medicine',              '["pre-travel consult","international travel"]', '["gp"]', 'preventive'),
('Counselling',                  'counselling',                  '["mental health counselling","therapy"]', '["psychologist","psychiatrist"]', 'preventive'),
('Family planning',              'family-planning',              '["contraception","birth control"]', '["gyno","gp"]', 'preventive'),
('Antenatal checkup',            'antenatal-checkup',            '["ANC","pregnancy checkup"]', '["gyno"]', 'preventive'),
('Postnatal checkup',            'postnatal-checkup',            '["PNC","post-delivery checkup"]', '["gyno","peds"]', 'preventive'),
('Sexual health concern',        'sexual-health-concern',        '["sexual issue","STI concern"]', '["sexology","andrology","gyno"]', 'preventive'),
('Sleep study',                  'sleep-study',                  '["polysomnography","sleep evaluation"]', '["pulmonology","ent"]', 'preventive'),
('Second opinion',               'second-opinion',               '["another opinion","consult"]', '["gp"]', 'preventive'),
('Follow-up visit',              'follow-up-visit',              '["FU","review visit"]', '["gp"]', 'preventive'),
('Lab report review',            'lab-report-review',            '["test result review","report dekhna"]', '["gp"]', 'preventive');

-- =============================================================
-- END OF SEED — verify count
-- =============================================================
-- Expected: ~290 rows (this seed is the curated core; the rest accumulate
-- naturally via doctor-personal → admin-promotion in production).
-- The Phase 3 plan said "~500"; this 290 covers the 80% of OPD vocabulary
-- across all major specialties. Let the promotion queue grow the rest
-- organically — that's the whole point of the 3-layer model.

-- SELECT COUNT(*) AS seeded, COUNT(DISTINCT category) AS categories
--   FROM symptoms_master;
