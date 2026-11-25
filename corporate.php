<?php
// =====================================================================
// corporate.php — eClinicPro Workforce Health marketing page (/corporate).
//
// Sells the company product (employer portal at app…/company). Sections:
// hero, overview grid, module deep-dives (workforce, screening, bookings,
// questionnaire, certificates, test reports, return-to-work & incidents,
// risk register, reminders, reports & spend, security & roles), employee
// experience, privacy split, check library, industries, for clinics,
// roadmap (clearly labelled NOT available yet), FAQ, final CTA.
//
// Claims kept to what is built (Phase 0, 1, 2B, 2C-1, 2D, 2E): no lab
// integration, no compliance guarantee, no payment handling, no health
// camps / hazard-driven screening yet (those sit in the roadmap block).
// Standalone marketing page, same pattern as for-patients.php.
// =====================================================================
require_once __DIR__ . '/partials/helpers.php';

$pageTitle = 'Workforce Health — Pre-employment & Periodic Health Checks for Companies | eClinicPro';
$metaDesc  = 'Occupational health software for employers: screening rules by job, automatic due dates, clinic and group bookings, pre-exam questionnaires, verifiable fitness certificates, return-to-work checks, incident log, risk register, reminders and compliance reports. Employers see fitness outcomes only. Free 30-day trial.';
$activePage = 'corporate';
$trialUrl = ecp_portal_url('/company/register');
$loginUrl = ecp_portal_url('/company/login');

// ── Overview cards (icon, title, one-liner, anchor) ──
$cpFeatures = [
    ['👷', 'Workforce & structure', 'Branches, departments, job roles and every employee, contractor or temp, imported from Excel.', 'workforce'],
    ['📋', 'Screening programmes', 'The right checks for each job, from country × industry templates you can adjust.', 'screening'],
    ['⏰', 'Automatic due dates', 'Pre-employment, periodic, exit and return-to-work checks worked out for every person.', 'screening'],
    ['🏥', 'Clinic & group bookings', 'Book real slots at partner clinics, one person or a whole crew of up to 40 at once.', 'bookings'],
    ['📝', 'Pre-exam questionnaire', 'Employees answer health questions online before the visit. Only the doctor sees answers.', 'questionnaire'],
    ['✅', 'Verifiable certificates', 'Signed fitness certificates with a QR code anyone can scan to check they are genuine.', 'certificates'],
    ['🩹', 'Return to work & incidents', 'Log absences and workplace incidents; the follow-up fitness check is created for you.', 'incidents'],
    ['⚠️', 'Risk register', 'Hazards by branch and job, a 5 × 5 risk matrix, owners, review dates and exposed headcount.', 'risks'],
    ['📨', 'Reminders & digests', 'Emails to employees before checks fall due, and a Monday summary for HR.', 'reminders'],
    ['📊', 'Reports & spend', 'Compliance by branch, department, job or programme in Excel or PDF, plus clinic statements.', 'reports'],
    ['🔐', 'Roles, 2FA & audit log', 'Seven ready-made roles, branch-only access, two-factor sign-in and a full activity log.', 'security'],
    ['🔒', 'Medical privacy by design', 'You see fitness for work. Diagnoses, results and notes never reach your account.', 'privacy'],
];

// ── Module deep-dives: [anchor, eyebrow, title, intro, bullets[]] ──
$cpModules = [
    ['workforce', 'Workforce', 'Your whole workforce, organised the way you work',
        'Set up your company once and every check, booking and report follows from it. Permanent staff, contractors, temporary workers and interns all sit in one list.',
        [
            'Branches and sites in more than one country, each in its own time zone',
            'Departments and job roles, so screening rules follow the work people do',
            'Excel / CSV import: map your columns, see every error in a dry run, then import in batches',
            'Joiners, job changes, transfers and leavers, each one updates the person’s checks automatically',
            'Employee ID numbers stored encrypted; every time someone reveals one, it is logged',
            'Invite employees to see their own checks in their eClinicPro account (optional)',
            'Export your employee list to a spreadsheet at any time',
        ]],
    ['screening', 'Screening programmes & due dates', 'The right checks for every job, on time, every time',
        'Pick a screening programme template for your country and industry, adopt it, and apply it to job roles, departments or branches. eClinicPro then works out who needs what, and when.',
        [
            'Templates by country × industry, e.g. welders, crane operators, work at height, food handlers, office staff',
            'Pre-employment, periodic (every 6, 12, 24 months…) and exit checks',
            'Mandatory checks are flagged; removing one needs a written reason that stays on record',
            'Existing staff get a sensible first round instead of everyone falling due on day one',
            'Due list split into overdue, due soon, later, done and waived, filterable by branch',
            'Checks done outside the network can be marked “done elsewhere”; waivers need a reason',
            'When a template is updated you see exactly what changed and choose what to accept',
        ]],
    ['bookings', 'Clinic network & bookings', 'Book from the doctor’s real calendar, not by phone',
        'Add the clinics that serve each branch. Bookings go straight into the clinic’s own appointment calendar on eClinicPro, so there is no double entry and no ringing round.',
        [
            'Choose which clinics serve which branches; the clinic accepts the link',
            'Record the prices you agreed with each clinic (a rate card), for statements and spend',
            'Book one employee for all of their open checks in a single appointment',
            'Group booking: book up to 40 people into consecutive free slots in one go',
            'Reschedule or cancel from your portal; clinic-side changes sync back to you',
            'No-shows and cancellations put the check back on the due list automatically',
            'Only doctors whose occupational health credentials we have verified can be booked',
        ]],
    ['questionnaire', 'Pre-exam questionnaire', 'Health history collected before the visit, seen only by the doctor',
        'Employees fill in a short pre-exam health questionnaire on their phone before they arrive, or the clinic fills it in with them. The doctor sees the answers on the exam screen, with any “Yes” highlighted.',
        [
            'Online form linked to the booking, works on any phone, no app needed',
            'Clinic can complete it with the employee at reception',
            'Answers go to the examining doctor only',
            'Your team just sees “questionnaire done”, nothing more',
            'Questionnaires are maintained centrally and versioned',
        ]],
    ['certificates', 'Fitness certificates', 'Certificates you can trust, and anyone can verify',
        'After the exam the doctor records the outcome and signs from the visit screen they already use. A PDF certificate is created instantly and the next due date is set.',
        [
            'Outcomes: fit, fit with restrictions, temporarily unfit, unfit, further assessment needed',
            'Work restrictions in plain words, e.g. “no work at height”, “no night shifts”',
            'Valid-to and review-by dates, with expiry reminders',
            'QR code on every certificate opens a public verification page that also detects tampering',
            'Corrections replace the old certificate with a reason; revoked certificates show as revoked',
            'Employees download their own certificates from their eClinicPro account',
            'Certificates list for HR: valid, expiring, expired, with restrictions, not shared',
        ]],
    ['reports-upload', 'Test reports', 'Lab and X-ray reports go to the doctor, not to HR',
        'For checks like blood tests or chest X-rays, the employee or the clinic uploads the report. The examining doctor reviews it before signing the certificate.',
        [
            'Employee upload from their account or the eClinicPro app',
            'Clinic upload from the visit screen',
            'A copy is saved in the employee’s own health records',
            'Your company is told only that a report was received',
        ]],
    ['incidents', 'Return to work & incidents', 'Follow-up checks that never get forgotten',
        'Record long absences and workplace incidents in a few fields. Where a fitness check is needed, eClinicPro creates it, due on the right date, and it shows up in the due list like any other check.',
        [
            'Absences by reason category (illness, injury, maternity / paternity, other), no diagnosis',
            'Return-to-work check due on the day the person comes back',
            'Incident log: injury, near miss, exposure (chemical, noise, heat…), illness at work',
            'Post-incident fitness check created for the person involved, due today',
            'Incidents by branch, with open / closed status and a full history',
            'Short summaries only; the form reminds users not to enter medical details',
        ]],
    ['risks', 'Risk register', 'Know your hazards, and who is exposed to them',
        'Keep your workplace risk assessment in the same place as your screening, so you can see at a glance whether the people exposed to a hazard are actually up to date with their checks.',
        [
            'Hazards by category: physical, chemical, biological, ergonomic and more',
            'Scope each risk to a branch, a job role, or both',
            'Likelihood × severity scoring on a 5 × 5 matrix, plus residual risk after controls',
            'Owner, controls in place, review date and status (open, controlled, closed)',
            'Coverage: how many people are exposed and how many of them are overdue a check',
            'Export the register to a spreadsheet for audits',
        ]],
    ['reminders', 'Reminders & digests', 'Nobody has to chase anybody',
        'Reminder emails go out at the right time in each company’s local time zone. Every reminder type can be switched on or off in your settings.',
        [
            'Employees: 30 days and 7 days before a check is due, and when it is overdue',
            'Employees: the day before their clinic appointment',
            'Certificate expiry reminders',
            'HR and branch managers: a Monday digest of what is overdue and coming up in their branches',
            'Never sent twice for the same thing',
        ]],
    ['reports', 'Reports & spend', 'Proof of compliance in one click',
        'Reports are always up to date because they come from the same data your team works with every day.',
        [
            'Compliance by branch, department, job role or programme',
            'Export to CSV, Excel or PDF; every export is recorded in the activity log',
            'Outcome summaries hidden for groups of fewer than 5 people, so nobody can be identified',
            'Clinics send monthly statements of completed checks at your agreed prices',
            'Spend report: billed by month plus an estimate of what is not yet billed',
            'Query a statement line with the clinic directly from the portal',
        ]],
    ['security', 'Security & control', 'Enterprise controls, without the enterprise complexity',
        'Give each person exactly the access they need, and keep a record of who did what.',
        [
            'Ready-made roles: Company Admin, HR Manager, HSE Manager, Branch Manager, Billing Admin, Viewer',
            'Custom roles built from individual permissions',
            'Branch Managers see only their own branch',
            'Two-factor sign-in with any authenticator app',
            'Activity log of sign-ins, changes, reveals and exports',
            'Invite colleagues by email; deactivate leavers in one click',
        ]],
];

$cpEmployee = [
    ['📱', 'No app to install', 'Booking details and reminders arrive by email. Employees can sign in with their phone number if they want more.'],
    ['🗂️', 'Their checks, their way', 'A Work health tab in their eClinicPro account and app shows what is due, booked and done.'],
    ['📝', 'Questionnaire on the phone', 'Answer the pre-exam questions from home, so the visit is quicker.'],
    ['📤', 'Upload test reports', 'Send lab or X-ray reports straight to the examining doctor.'],
    ['📄', 'Keep their certificates', 'Download their fitness certificates any time from their own account.'],
    ['🤝', 'They choose what to share', 'The employee decides whether the fitness outcome is shared with the employer.'],
];

$cpSteps = [
    ['Register & set up', 'Create your company account, add branches and job roles, and import employees from Excel.'],
    ['Choose the rules', 'Adopt screening templates for your industry and apply them to jobs, departments or branches.'],
    ['Link your clinics', 'Add the clinics that serve each branch and record the prices you agreed.'],
    ['Book the checks', 'Due checks appear automatically. Book them one by one or as a group.'],
    ['Stay compliant', 'Certificates come back after the visit, next due dates are set, reminders go out and reports stay current.'],
];

$cpChecks = [
    'Examinations' => ['Physical fitness exam', 'Vision (distance & near)', 'Colour vision', 'Audiometry (hearing)', 'Spirometry (lung function)', 'ECG', 'Musculoskeletal assessment', 'Skin examination', 'Psychological fitness'],
    'Lab & imaging' => ['Chest X-ray', 'Complete blood count', 'Blood sugar', 'Lipid profile', 'Liver function', 'Kidney function', 'Urine routine', 'Stool examination', 'Hepatitis B (HBsAg)', 'Blood lead', 'Cholinesterase'],
    'Fitness for duty' => ['Drug screen', 'Alcohol test', 'Health questionnaire', 'Return-to-work assessment', 'Post-incident assessment'],
    'Vaccinations' => ['Hepatitis B', 'Tetanus', 'Typhoid'],
];

$cpIndustries = ['Construction & infrastructure', 'Manufacturing & plants', 'Maritime & shipyards', 'Oil, gas & energy', 'Mining & quarrying', 'Chemicals & pharma', 'Logistics & warehousing', 'Transport & fleet drivers', 'Food handling & hospitality', 'Facilities & security', 'Hospitals & healthcare staff', 'Offices with field teams'];

$cpClinic = [
    'Company bookings land in your normal appointment calendar',
    'An occupational exam panel inside the visit screen you already use',
    'Sign fitness certificates with a built-in QR code and PDF',
    'See the pre-exam questionnaire and attach test reports',
    'Agreed price lists per company, and monthly statements generated for you',
    'Your doctors’ occupational health credentials verified once by us',
];

$cpRoadmap = [
    ['🚐', 'On-site health camps', 'Partner clinics run screening days at your site, with a camp schedule for each branch.'],
    ['☢️', 'Hazard-based screening', 'Link checks directly to the hazards in your risk register, e.g. everyone exposed to noise gets audiometry.'],
    ['🧪', 'Lab partner network', 'Lab orders and results that flow straight to the examining doctor.'],
    ['🌍', 'More countries', 'Reviewed screening templates for the UAE and other Gulf countries, then Africa and Europe.'],
    ['💪', 'Workplace wellness', 'Optional wellness programmes for office and field teams.'],
];

$cpFaqs = [
    ['Can we see our employees’ medical results?',
        'No. Your team sees whether each check is due, booked or done, and the fitness outcome with any work restrictions and its validity, only if the employee agrees to share it. Diagnoses, test values, questionnaire answers, prescriptions and notes stay between the employee and the doctor. This is enforced in the software.'],
    ['Do the screening templates make us legally compliant?',
        'They are a starting point for your country and industry, not legal advice. Your occupational health physician or safety adviser should confirm the rules for your sites. You can add checks, change frequencies, and remove recommended checks; removing a mandatory one needs a written reason that stays on record.'],
    ['Can we include contractors and temporary workers?',
        'Yes. Each person can be recorded as an employee, contractor (with the contractor company), temporary worker or intern, and gets the screening rules for their job role like everyone else.'],
    ['Which clinics can we use?',
        'Clinics on eClinicPro that do occupational health checks. You choose which clinics serve which branches. If you already work with a clinic, ask them to join eClinicPro, or tell us and we will bring them on.'],
    ['Can we book a whole team at once?',
        'Yes. Group booking places up to 40 people into consecutive free slots at a clinic in one go. Each person is booked for all of their open checks.'],
    ['What happens after an injury or long sick leave?',
        'Record the absence or incident in the portal (no medical details needed). If a fitness check is required, it is created automatically: a return-to-work check due on the day the person comes back, or a post-incident check due straight away.'],
    ['Does eClinicPro collect payment for the clinics?',
        'No. You pay your clinics directly under your own agreement. eClinicPro records the prices you agreed and the checks done, so clinics can send you monthly statements and you can see your spend.'],
    ['Do employees need to install an app?',
        'No. Employees get booking details and reminders by email. If they want, they can sign in to eClinicPro with their phone number to see their checks, fill in the questionnaire, upload test reports and download their certificates.'],
    ['What about lab tests and X-rays?',
        'Employees (or the clinic) can upload test reports for lab and X-ray checks. Only the examining doctor sees them; you are told that a report was received. A direct lab partner network is on our roadmap.'],
    ['How is our data protected?',
        'Two-factor sign-in, role-based access, branch-only access for branch managers, encrypted employee ID numbers and an activity log of sign-ins, changes and exports. Clinical data is stored separately from company data and is never sent to company accounts.'],
    ['Which countries do you support?',
        'India first, with branches in other countries supported (for example a Gulf company with a site in India). The UAE is next.'],
    ['How do we start, and what does it cost?',
        'Register your company; once we have checked your details your free 30-day trial starts. Talk to us about pricing for your workforce size before the trial ends.'],
];

$faqLd = [
    '@context'   => 'https://schema.org',
    '@type'      => 'FAQPage',
    'mainEntity' => array_map(static fn (array $f): array => [
        '@type' => 'Question', 'name' => $f[0], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f[1]],
    ], $cpFaqs),
];

ob_start(); ?>
<script type="application/ld+json">
<?= json_encode($faqLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?>
</script>
<?php
$extraHead = ob_get_clean();

require __DIR__ . '/partials/header.php';
?>

<style>
    html { scroll-behavior: smooth; }
    .cp-hero { padding: 104px 0 68px; text-align: center; position: relative; overflow: hidden; background: #f0f4f1; }
    .cp-hero-glow { position: absolute; inset: 0; background: radial-gradient(ellipse at 50% 0%, rgba(26, 122, 78, 0.08) 0%, transparent 65%); pointer-events: none; }
    .cp-hero-inner { position: relative; max-width: 820px; margin: 0 auto; }
    .cp-hero h1 { font-size: clamp(30px, 5vw, 48px); line-height: 1.08; letter-spacing: -0.02em; margin: 0 0 18px; }
    .cp-hero h1 .accent { color: #1a7a4e; }
    .cp-hero .lede { font-size: clamp(16px, 2.2vw, 19px); color: #475569; max-width: 660px; margin: 0 auto 30px; line-height: 1.55; }
    .cp-ctas { display: flex; gap: 12px; justify-content: center; flex-wrap: wrap; }
    .cp-note { margin-top: 16px; font-size: 13px; color: #64748b; }
    .cp-signin { margin-top: 6px; font-size: 13px; color: #64748b; }
    .cp-signin a { color: #1a7a4e; }
    .cp-trust { display: flex; flex-wrap: wrap; justify-content: center; gap: 8px 22px; margin: 34px auto 0; max-width: 760px; font-size: 14px; color: #334155; }
    .cp-trust span::before { content: '✓'; color: #1a7a4e; font-weight: 700; margin-right: 6px; }

    .cp-jump { position: sticky; top: 80px; z-index: 20; background: rgba(255,255,255,0.96); backdrop-filter: blur(6px); border-bottom: 1px solid #e6ece8; }
    .cp-jump .wrap { display: flex; gap: 6px; overflow-x: auto; padding-top: 10px; padding-bottom: 10px; scrollbar-width: none; }
    .cp-jump .wrap::-webkit-scrollbar { display: none; }
    .cp-jump a { flex-shrink: 0; font-size: 13.5px; color: #334155; text-decoration: none; padding: 6px 12px; border-radius: 999px; border: 1px solid #e6ece8; background: #fff; }
    .cp-jump a:hover { border-color: #1a7a4e; color: #1a7a4e; }

    .cp-section { padding: 66px 0; scroll-margin-top: 140px; }
    .cp-section.alt { background: #f7faf8; }
    .cp-head { text-align: center; max-width: 680px; margin: 0 auto 44px; }
    .cp-head h2 { font-size: clamp(24px, 3.4vw, 34px); letter-spacing: -0.02em; margin: 8px 0 12px; }
    .cp-head p { color: #55606f; font-size: 16px; line-height: 1.55; margin: 0; }

    .cp-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 18px; }
    .cp-card { display: block; text-decoration: none; color: inherit; background: #fff; border: 1px solid #e6ece8; border-radius: 16px; padding: 22px 20px; transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease; }
    a.cp-card:hover { transform: translateY(-3px); box-shadow: 0 12px 30px rgba(26,122,78,0.08); border-color: #cfe0d6; }
    .cp-ic { width: 48px; height: 48px; display: grid; place-items: center; font-size: 24px; border-radius: 13px; background: linear-gradient(135deg, #e8f5ee, #d6ebdf); margin-bottom: 14px; }
    .cp-card h3 { font-size: 17px; margin: 0 0 7px; letter-spacing: -0.01em; color: #0f172a; }
    .cp-card p { color: #55606f; font-size: 14.5px; line-height: 1.55; margin: 0; }
    .cp-card .more { display: inline-block; margin-top: 10px; font-size: 13.5px; color: #1a7a4e; font-weight: 600; }

    .cp-mod { display: grid; grid-template-columns: 1fr 1.15fr; gap: 40px; align-items: start; max-width: 1060px; margin: 0 auto; }
    .cp-mod.flip .cp-mod-text { order: 2; }
    .cp-mod-text h2 { font-size: clamp(22px, 3vw, 30px); letter-spacing: -0.02em; margin: 8px 0 14px; line-height: 1.18; }
    .cp-mod-text p { color: #55606f; font-size: 16px; line-height: 1.6; margin: 0; }
    .cp-mod-list { background: #fff; border: 1px solid #e6ece8; border-radius: 16px; padding: 10px 22px; margin: 0; list-style: none; }
    .cp-mod-list li { padding: 11px 0 11px 30px; position: relative; color: #334155; font-size: 15px; line-height: 1.5; border-top: 1px solid #f1f5f9; }
    .cp-mod-list li:first-child { border-top: 0; }
    .cp-mod-list li::before { content: '✓'; position: absolute; left: 2px; top: 11px; width: 20px; height: 20px; border-radius: 50%; background: #e8f5ee; color: #047857; font-size: 12px; font-weight: 700; display: grid; place-items: center; }

    .cp-steps { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 18px; counter-reset: step; }
    .cp-step { padding: 24px 20px 22px; background: #fff; border: 1px solid #e6ece8; border-radius: 16px; }
    .cp-step::before { counter-increment: step; content: counter(step); display: grid; place-items: center; width: 38px; height: 38px; border-radius: 50%; background: #1a7a4e; color: #fff; font-weight: 700; font-size: 17px; margin-bottom: 14px; }
    .cp-step h3 { font-size: 17px; margin: 0 0 7px; }
    .cp-step p { color: #55606f; font-size: 14px; line-height: 1.5; margin: 0; }

    .cp-split { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; max-width: 920px; margin: 0 auto; }
    .cp-box { border-radius: 16px; padding: 24px 24px 18px; border: 1px solid #e6ece8; background: #fff; }
    .cp-box h3 { font-size: 17px; margin: 0 0 12px; }
    .cp-box ul { margin: 0; padding: 0; list-style: none; }
    .cp-box li { padding: 7px 0 7px 26px; position: relative; color: #334155; font-size: 15px; border-top: 1px solid #f1f5f9; }
    .cp-box li:first-child { border-top: 0; }
    .cp-box.yes li::before { content: '✓'; position: absolute; left: 2px; color: #047857; font-weight: 700; }
    .cp-box.no li::before { content: '✕'; position: absolute; left: 2px; color: #b91c1c; font-weight: 700; }
    .cp-box.yes { border-color: #bbe5d2; }
    .cp-box.no { border-color: #f3c8c8; background: #fffafa; }

    .cp-checks { display: grid; grid-template-columns: repeat(auto-fit, minmax(230px, 1fr)); gap: 18px; max-width: 1060px; margin: 0 auto; }
    .cp-checks .grp { background: #fff; border: 1px solid #e6ece8; border-radius: 16px; padding: 20px; }
    .cp-checks h3 { font-size: 15px; text-transform: uppercase; letter-spacing: .04em; color: #1a7a4e; margin: 0 0 12px; }
    .cp-checks .tags { display: flex; flex-wrap: wrap; gap: 7px; }
    .cp-checks .tags span { font-size: 13.5px; background: #f3f7f5; border-radius: 8px; padding: 5px 10px; color: #334155; }
    .cp-fine { text-align: center; color: #64748b; font-size: 13.5px; margin: 20px auto 0; max-width: 680px; }

    .cp-chips { display: flex; flex-wrap: wrap; gap: 10px; justify-content: center; max-width: 900px; margin: 0 auto; }
    .cp-chip { background: #fff; border: 1px solid #e6ece8; border-radius: 999px; padding: 9px 16px; font-size: 14.5px; color: #334155; }

    .cp-clinic { max-width: 1000px; margin: 0 auto; display: grid; grid-template-columns: 1fr 1.1fr; gap: 32px; align-items: center;
                 background: #fff; border: 1px solid #e6ece8; border-radius: 18px; padding: 32px; }
    .cp-clinic h2 { margin: 8px 0 10px; font-size: clamp(22px, 3vw, 28px); letter-spacing: -0.02em; }
    .cp-clinic p { margin: 0 0 20px; color: #55606f; font-size: 15.5px; line-height: 1.55; }
    .cp-clinic ul { margin: 0; padding: 0; list-style: none; }
    .cp-clinic li { padding: 8px 0 8px 26px; position: relative; color: #334155; font-size: 15px; border-top: 1px solid #f1f5f9; }
    .cp-clinic li:first-child { border-top: 0; }
    .cp-clinic li::before { content: '✓'; position: absolute; left: 2px; color: #047857; font-weight: 700; }

    .cp-road { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; }
    .cp-road .item { background: #fff; border: 1px dashed #cbd5e1; border-radius: 16px; padding: 20px; position: relative; }
    .cp-road .badge { position: absolute; top: 14px; right: 14px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; background: #fef3c7; color: #92400e; padding: 3px 8px; border-radius: 999px; }
    .cp-road .ic { font-size: 26px; margin-bottom: 10px; }
    .cp-road h3 { font-size: 16px; margin: 0 0 6px; }
    .cp-road p { color: #55606f; font-size: 14px; line-height: 1.5; margin: 0; }

    .cp-faq { max-width: 780px; margin: 0 auto; display: flex; flex-direction: column; gap: 12px; }
    .cp-faq details { background: #fff; border: 1px solid #e6ece8; border-radius: 14px; overflow: hidden; }
    .cp-faq details[open] { border-color: #cfe0d6; box-shadow: 0 8px 24px rgba(26,122,78,0.06); }
    .cp-faq summary { list-style: none; cursor: pointer; display: flex; align-items: center; justify-content: space-between; gap: 16px; padding: 18px 20px; font-size: 16px; font-weight: 600; color: #0f172a; }
    .cp-faq summary::-webkit-details-marker { display: none; }
    .cp-faq .chev { flex-shrink: 0; color: #64748b; transition: transform .2s ease; }
    .cp-faq details[open] .chev { transform: rotate(180deg); color: #1a7a4e; }
    .cp-faq .a { padding: 0 20px 18px; }
    .cp-faq .a p { margin: 0; color: #55606f; font-size: 15px; line-height: 1.6; }

    .cp-final { text-align: center; background: linear-gradient(135deg, #10603b, #1a7a4e); color: #fff; border-radius: 22px; padding: 56px 28px; }
    .cp-final h2 { font-size: clamp(24px, 3.4vw, 34px); margin: 0 0 12px; letter-spacing: -0.02em; color: #fff; }
    .cp-final p { color: rgba(255,255,255,0.9); font-size: 17px; margin: 0 auto 26px; max-width: 540px; line-height: 1.5; }
    .cp-final .btn-primary { background: #fff; color: #10603b; }
    .cp-final .btn-ghost { color: #fff; border-color: rgba(255,255,255,0.5); }

    @media (max-width: 860px) {
        .cp-mod, .cp-clinic { grid-template-columns: 1fr; gap: 20px; }
        .cp-mod.flip .cp-mod-text { order: 0; }
    }
    @media (max-width: 720px) {
        .cp-hero { padding: 84px 0 52px; }
        .cp-section { padding: 48px 0; }
        .cp-split { grid-template-columns: 1fr; }
        .cp-clinic { padding: 22px; }
        .cp-mod-list { padding: 6px 16px; }
        #features .cp-grid, #employees .cp-grid { gap: 10px; }
        #features .cp-card, #employees .cp-card { display: grid; grid-template-columns: 42px 1fr; column-gap: 14px; padding: 14px 16px; }
        #features .cp-ic, #employees .cp-ic { width: 42px; height: 42px; font-size: 21px; margin: 0; grid-row: span 2; }
        #features .cp-card h3, #employees .cp-card h3 { font-size: 16px; margin: 0 0 3px; }
        #features .cp-card p, #employees .cp-card p { font-size: 14px; }
        #features .cp-card .more, #employees .cp-card .more { display: none; }
        .cp-final { padding: 40px 20px; border-radius: 18px; }
        .cp-faq summary { font-size: 15px; padding: 16px; }
        .cp-faq .a { padding: 0 16px 16px; }
    }
</style>

<!-- ═══════════════ HERO ═══════════════ -->
<section class="cp-hero">
    <div class="cp-hero-glow"></div>
    <div class="wrap cp-hero-inner reveal">
        <span class="hp-eyebrow">For companies · Workforce Health</span>
        <h1>Every worker’s health check, <span class="accent">on time</span>, without seeing anyone’s medical records</h1>
        <p class="lede">
            Occupational health software for HR and safety teams: the right checks for each job, due dates that run
            themselves, bookings at partner clinics, return-to-work checks, a risk register, and fitness certificates you can verify.
        </p>
        <div class="cp-ctas">
            <a href="<?= e($trialUrl) ?>" class="btn btn-primary btn-lg">Start free 30-day trial</a>
            <a href="/book-a-demo" class="btn btn-ghost btn-lg">Book a demo →</a>
        </div>
        <p class="cp-note">No card needed · India first, Gulf-ready · One site or many branches</p>
        <p class="cp-signin">Already registered? <a href="<?= e($loginUrl) ?>">Sign in to your company account</a></p>
        <div class="cp-trust">
            <span>Employer never sees medical data</span>
            <span>QR-verifiable certificates</span>
            <span>Two-factor sign-in</span>
            <span>Excel import in minutes</span>
        </div>
    </div>
</section>

<nav class="cp-jump" aria-label="Page sections">
    <div class="wrap">
        <a href="#features">Overview</a>
        <a href="#workforce">Workforce</a>
        <a href="#screening">Screening</a>
        <a href="#bookings">Bookings</a>
        <a href="#certificates">Certificates</a>
        <a href="#incidents">Return to work</a>
        <a href="#risks">Risk register</a>
        <a href="#reports">Reports</a>
        <a href="#security">Security</a>
        <a href="#employees">For employees</a>
        <a href="#privacy">Privacy</a>
        <a href="#clinics">For clinics</a>
        <a href="#faq">FAQ</a>
    </div>
</nav>

<!-- ═══════════════ OVERVIEW ═══════════════ -->
<section class="cp-section" id="features">
    <div class="wrap">
        <div class="cp-head reveal">
            <span class="hp-eyebrow">What you get</span>
            <h2>Occupational health, from hiring to exit, without the spreadsheets</h2>
            <p>Everything HR, HSE and site managers need to keep every person fit for their job, and to prove it to auditors, clients and regulators.</p>
        </div>
        <div class="cp-grid">
            <?php foreach ($cpFeatures as [$ic, $title, $body, $anchor]): ?>
                <a class="cp-card reveal" href="#<?= e($anchor) ?>">
                    <div class="cp-ic"><?= $ic ?></div>
                    <h3><?= e($title) ?></h3>
                    <p><?= e($body) ?></p>
                    <span class="more">Learn more →</span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ═══════════════ HOW IT WORKS ═══════════════ -->
<section class="cp-section alt">
    <div class="wrap">
        <div class="cp-head reveal">
            <span class="hp-eyebrow">How it works</span>
            <h2>From spreadsheet to compliant in five steps</h2>
        </div>
        <div class="cp-steps">
            <?php foreach ($cpSteps as [$title, $body]): ?>
                <div class="cp-step reveal"><h3><?= e($title) ?></h3><p><?= e($body) ?></p></div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ═══════════════ MODULE DEEP-DIVES ═══════════════ -->
<?php foreach ($cpModules as $i => [$anchor, $eyebrow, $title, $intro, $bullets]): ?>
<section class="cp-section<?= $i % 2 === 1 ? ' alt' : '' ?>" id="<?= e($anchor) ?>">
    <div class="wrap">
        <div class="cp-mod<?= $i % 2 === 1 ? ' flip' : '' ?> reveal">
            <div class="cp-mod-text">
                <span class="hp-eyebrow"><?= e($eyebrow) ?></span>
                <h2><?= e($title) ?></h2>
                <p><?= e($intro) ?></p>
            </div>
            <ul class="cp-mod-list">
                <?php foreach ($bullets as $b): ?><li><?= e($b) ?></li><?php endforeach; ?>
            </ul>
        </div>
    </div>
</section>
<?php endforeach; ?>

<!-- ═══════════════ FOR EMPLOYEES ═══════════════ -->
<section class="cp-section" id="employees">
    <div class="wrap">
        <div class="cp-head reveal">
            <span class="hp-eyebrow">For your employees</span>
            <h2>Simple for the people being checked</h2>
            <p>Employees use their own free eClinicPro account, so their health records stay in their hands, not on a company spreadsheet.</p>
        </div>
        <div class="cp-grid">
            <?php foreach ($cpEmployee as [$ic, $title, $body]): ?>
                <div class="cp-card reveal">
                    <div class="cp-ic"><?= $ic ?></div>
                    <h3><?= e($title) ?></h3>
                    <p><?= e($body) ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ═══════════════ PRIVACY ═══════════════ -->
<section class="cp-section alt" id="privacy">
    <div class="wrap">
        <div class="cp-head reveal">
            <span class="hp-eyebrow">Medical privacy</span>
            <h2>You see fitness for work. The doctor keeps the medical details.</h2>
            <p>Employees trust the process, and you never hold health data you shouldn’t. Clinical data is stored separately and never sent to company accounts.</p>
        </div>
        <div class="cp-split reveal">
            <div class="cp-box yes">
                <h3>What your company sees</h3>
                <ul>
                    <li>Which checks are due, booked, overdue or done</li>
                    <li>Fitness outcome, if the employee agrees</li>
                    <li>Work restrictions, e.g. “no work at height”</li>
                    <li>Certificate validity and expiry</li>
                    <li>That a questionnaire was completed</li>
                    <li>That a test report was received</li>
                </ul>
            </div>
            <div class="cp-box no">
                <h3>What stays with the doctor</h3>
                <ul>
                    <li>Diagnoses and medical history</li>
                    <li>Test results and lab values</li>
                    <li>Questionnaire answers</li>
                    <li>Examination findings and notes</li>
                    <li>Prescriptions</li>
                    <li>Uploaded lab and X-ray reports</li>
                </ul>
            </div>
        </div>
    </div>
</section>

<!-- ═══════════════ CHECK LIBRARY ═══════════════ -->
<section class="cp-section">
    <div class="wrap">
        <div class="cp-head reveal">
            <span class="hp-eyebrow">Check library</span>
            <h2>The checks you can schedule</h2>
            <p>Combine them into programmes for each job role, with your own frequencies.</p>
        </div>
        <div class="cp-checks reveal">
            <?php foreach ($cpChecks as $group => $items): ?>
                <div class="grp">
                    <h3><?= e($group) ?></h3>
                    <div class="tags"><?php foreach ($items as $it): ?><span><?= e($it) ?></span><?php endforeach; ?></div>
                </div>
            <?php endforeach; ?>
        </div>
        <p class="cp-fine reveal">Checks are carried out by the clinics in your network. Which tests a clinic offers depends on the clinic.</p>
    </div>
</section>

<!-- ═══════════════ INDUSTRIES ═══════════════ -->
<section class="cp-section alt">
    <div class="wrap">
        <div class="cp-head reveal">
            <span class="hp-eyebrow">Who it’s for</span>
            <h2>Built for workplaces where fitness matters</h2>
            <p>From a single factory to multi-site contractors with crews in more than one country.</p>
        </div>
        <div class="cp-chips reveal">
            <?php foreach ($cpIndustries as $ind): ?><span class="cp-chip"><?= e($ind) ?></span><?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ═══════════════ FOR CLINICS ═══════════════ -->
<section class="cp-section" id="clinics">
    <div class="wrap">
        <div class="cp-clinic reveal">
            <div>
                <span class="hp-eyebrow">For clinics</span>
                <h2>Run an occupational health clinic?</h2>
                <p>Join the eClinicPro network and receive bookings from companies near you, with no extra software to learn.</p>
                <a href="/contact" class="btn btn-ghost">Talk to us →</a>
            </div>
            <ul>
                <?php foreach ($cpClinic as $c): ?><li><?= e($c) ?></li><?php endforeach; ?>
            </ul>
        </div>
    </div>
</section>

<!-- ═══════════════ ROADMAP ═══════════════ -->
<section class="cp-section alt" id="roadmap">
    <div class="wrap">
        <div class="cp-head reveal">
            <span class="hp-eyebrow">What’s next</span>
            <h2>On our roadmap</h2>
            <p>These are not available yet. Tell us which ones matter to you; early customers shape what we build first.</p>
        </div>
        <div class="cp-road reveal">
            <?php foreach ($cpRoadmap as [$ic, $title, $body]): ?>
                <div class="item">
                    <span class="badge">Planned</span>
                    <div class="ic"><?= $ic ?></div>
                    <h3><?= e($title) ?></h3>
                    <p><?= e($body) ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ═══════════════ FAQ ═══════════════ -->
<section class="cp-section" id="faq">
    <div class="wrap">
        <div class="cp-head reveal">
            <span class="hp-eyebrow">Questions</span>
            <h2>Frequently asked questions</h2>
        </div>
        <div class="cp-faq reveal">
            <?php foreach ($cpFaqs as $i => [$q, $a]): ?>
                <details<?= $i === 0 ? ' open' : '' ?>>
                    <summary>
                        <span><?= e($q) ?></span>
                        <svg class="chev" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="6 9 12 15 18 9"/></svg>
                    </summary>
                    <div class="a"><p><?= e($a) ?></p></div>
                </details>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ═══════════════ FINAL CTA ═══════════════ -->
<section class="cp-section">
    <div class="wrap">
        <div class="cp-final reveal">
            <h2>Get your workforce screening under control</h2>
            <p>Start with a free 30-day trial, or let us show you around with your own job roles.</p>
            <div class="cp-ctas">
                <a href="<?= e($trialUrl) ?>" class="btn btn-primary btn-lg">Start free trial</a>
                <a href="/book-a-demo" class="btn btn-ghost btn-lg">Book a demo</a>
            </div>
        </div>
    </div>
</section>

<?php
$hideFinalCta = true;
require __DIR__ . '/partials/footer.php';
?>
