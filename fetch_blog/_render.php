<?php
// =====================================================================
// fetch_blog/_render.php — turns Claude's block JSON into post HTML
// matching the live dental-implants post structure EXACTLY:
// class-based markup only, no inline CSS.
//
// Theme classes used (already styled by eclinicpro-blog/style.css):
//   section.implant-section > .implant-content > .implant-text /
//   .implant-image, .implant-content.full, ul.implant-list,
//   section.comparison-section > .table-responsive > table.comparison-table,
//   .faq-item (h4 + p), .benefit-item (h4 + p), p.isSelectedEnd
//
// New classes (no theme rule yet — paste fetch_blog/blog-blocks.css
// into the child theme once): .ecp-quick-answer, .ecp-cta-banner,
// .ecp-cta-btn, .ecp-note, .ecp-disclaimer
//
// Images: NO generation. Each image slot gets a visible placeholder
// (placehold.co) with proper alt text — replace src manually in
// wp-admin with your real image.
// =====================================================================

declare(strict_types=1);

require_once __DIR__ . '/_env.php';

const FB_IMG_PLACEHOLDER = 'https://placehold.co/800x600/e6f4f0/00a884?text=Replace+with+real+image';

// Blog specialty → canonical /find-a-doctor/{slug} from partials/seo_slugs.php
// (main-site directory pages; the workbook's SEO plan wants every blog to
// link to its specialty directory page). Keep in sync with seo_slugs.php.
const FB_SPEC_SLUGS = [
    'Ayurveda'              => 'ayurveda',
    'Cardiology'            => 'cardiologist',
    'Cosmetic Surgery'      => 'plastic-surgeon',
    'Dental'                => 'dentist',
    'Dermatology'           => 'dermatologist',
    'Diabetology'           => 'diabetologist',
    'ENT'                   => 'ent-specialist',
    'Endocrinology'         => 'endocrinologist',
    'Fertility & IVF'       => 'fertility-specialist',
    'Gastroenterology'      => 'gastroenterologist',
    'General Physician'     => 'general-physician',
    'Gynecology'            => 'gynecologist',
    'Homeopathy'            => 'homeopathy',
    'Nephrology'            => 'nephrologist',
    'Neurology'             => 'neurologist',
    'Nutrition & Dietetics' => 'dietitian',
    'Oncology'              => 'oncologist',
    'Ophthalmology'         => 'ophthalmologist',
    'Orthopedics'           => 'orthopedic',
    'Pediatrics'            => 'pediatrician',
    'Physiotherapy'         => 'physiotherapist',
    'Psychiatry'            => 'psychiatrist',
    'Psychology'            => 'psychologist',
    'Pulmonology'           => 'pulmonologist',
    'Urology'               => 'urologist',
];

/** WordPress-style slug (mirrors sanitize_title for our simple names). */
function fb_slugify(string $s): string {
    $s = strtolower(trim($s));
    $s = str_replace('&', ' ', $s);
    $s = (string) preg_replace('/[^a-z0-9]+/', '-', $s);
    return trim($s, '-');
}

/**
 * Heading id in WordPress "sanitize_title" style used by the live blog:
 * lowercase, apostrophes become "-039-", other non-alphanumerics become
 * "-". e.g. "Scared of a Root Canal? Let's Clear the Air"
 *        -> "scared-of-a-root-canal-let-039-s-clear-the-air"
 */
function fb_heading_id(string $s): string {
    $s = strtolower(trim($s));
    $s = str_replace("'", '-039-', $s);
    $s = str_replace('&', '-038-', $s);
    $s = (string) preg_replace('/[^a-z0-9]+/', '-', $s);
    $s = (string) preg_replace('/-+/', '-', $s);
    return trim($s, '-');
}

/**
 * Prose block in the blog's classic-editor style: the FIRST paragraph is
 * <p class="isSelectedEnd">...</p>, every following paragraph is RAW text
 * separated by blank lines (WordPress auto-wraps these in <p> on save).
 * @param string[] $paras
 */
function fb_prose(array $paras, bool $firstIsLead = true): string {
    $html = '';
    $first = true;
    foreach ($paras as $p) {
        if (!is_string($p) || $p === '') continue;
        if ($first && $firstIsLead) {
            $html .= '<p class="isSelectedEnd">' . fb_md($p) . '</p>' . "\n";
        } else {
            $html .= fb_md($p) . "\n\n";
        }
        $first = false;
    }
    return $html;
}

/** Heading with WP-style id. */
function fb_h2(string $text): string {
    return '<h2 id="' . fb_heading_id($text) . '">' . fb_e($text) . '</h2>';
}

/**
 * The best real directory URL for this blog:
 *   city + mapped specialty → /find-a-doctor/{spec}-in-{city}
 *   mapped specialty only   → /find-a-doctor/{spec}
 *   otherwise               → /find-a-doctor
 */
function fb_directory_url(string $mainSite, string $specialty, string $city): string {
    $slug = FB_SPEC_SLUGS[$specialty] ?? '';
    if ($slug === '') return $mainSite . '/find-a-doctor';
    if ($city !== '') return $mainSite . '/find-a-doctor/' . $slug . '-in-' . fb_slugify($city);
    return $mainSite . '/find-a-doctor/' . $slug;
}

/**
 * Escape HTML, then convert the ONE markdown feature the prompt allows
 * (**bold**) into <strong>. Everything else stays literal text.
 */
function fb_md(?string $s): string {
    $safe = fb_e($s);
    return preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $safe) ?? $safe;
}

/**
 * @param array<string,mixed> $row  calendar row
 * @param array<string,mixed> $b    Claude's block JSON
 */
function fb_render_html(array $row, array $b): string {
    $city = (string) ($row['city'] ?? '');
    $specialty = (string) ($row['cta_specialty'] ?? 'Doctor');
    $specGroup = (string) ($row['specialty'] ?? $row['campaign'] ?? '');
    $mainSite = rtrim(fb_env('MAIN_SITE_URL', 'https://eclinicpro.com'), '/');
    $wpBase = rtrim(fb_env('WP_BASE_URL', 'https://eclinicpro.com/blog'), '/');
    // SEO: deep-link to the real city×specialty directory page, not the generic finder
    $findUrl = fb_directory_url($mainSite, $specGroup, $city);

    $out = [];

    // --- Intro: hook heading + prose (first para is the lead) ----------
    $introHeading = (string) ($b['intro_heading'] ?? '');
    if ($introHeading !== '') {
        $out[] = fb_h2($introHeading);
    }
    $out[] = fb_prose((array) ($b['intro'] ?? []));

    // --- Quick-answer box (content is raw text, no <p>) ----------------
    if (!empty($b['quick_answer'])) {
        $out[] = '<div class="ecp-quick-answer">' . "\n"
            . '<h4>Quick answer</h4>' . "\n"
            . fb_md((string) $b['quick_answer']) . "\n\n"
            . '</div>';
    }

    // --- Symptom checklist ----------------------------------------------
    if (!empty($b['symptom_checklist'])) {
        $items = '';
        foreach ((array) $b['symptom_checklist'] as $s) {
            $items .= "\n \t" . '<li>' . fb_e((string) $s) . '</li>';
        }
        $out[] = '<h2>Do you have these signs?</h2>'
            . '<ul class="implant-list">' . $items . "\n" . '</ul>'
            . '<p class="ecp-note">Ticking a few of these does not confirm a diagnosis — it means it is worth talking to a doctor.</p>';
    }

    // --- Causes -----------------------------------------------------------
    if (!empty($b['causes'])) {
        $items = '';
        foreach ((array) $b['causes'] as $c) {
            $items .= "\n \t" . '<li><strong>' . fb_e((string) ($c['title'] ?? '')) . ':</strong> '
                . fb_e((string) ($c['line'] ?? '')) . '</li>';
        }
        $out[] = '<h2>What causes it?</h2><ul class="implant-list">' . $items . "\n" . '</ul>';
    }

    // --- Deep-dive sections -----------------------------------------------
    // Structure per the live blog:
    //   <section class="implant-section">
    //     <div class="implant-content full">
    //       <div class="implant-text">
    //         <h2 id="...">Heading</h2>
    //         (lead <p class="isSelectedEnd"> + raw prose paragraphs)
    //         [if bullets and/or an image:]
    //         <div class="eclinicpro-quick-answer">
    //           <div class="ecp-quick-answer-title"> lead-in + <ul class="implant-list"> </div>
    //           <div class="implant-image"><img ...></div>
    //         </div>
    //       </div></div></section>
    foreach ((array) ($b['sections'] ?? []) as $sec) {
        if (!is_array($sec)) continue;
        $heading = (string) ($sec['heading'] ?? '');
        $prose   = fb_prose((array) ($sec['paragraphs'] ?? []));

        // bullets (implant-list) with optional lead-in
        $bulletsIntro = (string) ($sec['bullets_intro'] ?? '');
        $bullets = array_values(array_filter((array) ($sec['bullets'] ?? []), 'is_string'));
        $bulletsHtml = '';
        if ($bullets !== []) {
            $lis = '';
            foreach ($bullets as $bl) { $lis .= "\n \t" . '<li>' . fb_md($bl) . '</li>'; }
            $bulletsHtml = ($bulletsIntro !== '' ? fb_md($bulletsIntro) . "\n" : '')
                . '<ul class="implant-list">' . $lis . "\n" . '</ul>' . "\n";
        }

        // titled point cards (benefit-item) — content raw, no <p>
        $itemsHtml = '';
        foreach ((array) ($sec['items'] ?? []) as $it) {
            if (!is_array($it)) continue;
            $t = (string) ($it['title'] ?? '');
            if ($t === '') continue;
            $itemsHtml .= '<div class="benefit-item">' . "\n"
                . '<h4>' . fb_e($t) . '</h4>' . "\n"
                . fb_md((string) ($it['text'] ?? '')) . "\n\n"
                . '</div>' . "\n";
        }

        if ($prose === '' && $bulletsHtml === '' && $itemsHtml === '') continue;

        $alt = (string) ($sec['image_alt'] ?? '');
        $hasImg = $alt !== '';
        $img = $hasImg
            ? '<div class="implant-image"><img src="' . FB_IMG_PLACEHOLDER . '" alt="' . fb_e($alt) . '" loading="lazy" /></div>'
            : '';

        // Build the inner text column.
        $inner = '<div class="implant-text">' . "\n"
            . ($heading !== '' ? fb_h2($heading) . "\n" : '')
            . $prose;

        if ($bulletsHtml !== '' && $hasImg) {
            // bullets + image share the eclinicpro-quick-answer flex row
            $inner .= '<div class="eclinicpro-quick-answer">' . "\n"
                . '<div class="ecp-quick-answer-title">' . "\n" . $bulletsHtml . '</div>' . "\n"
                . $img . "\n"
                . '</div>' . "\n";
        } elseif ($bulletsHtml !== '') {
            $inner .= $bulletsHtml;
        } elseif ($hasImg) {
            // image only, still inside the flex row so it sits beside the prose
            $inner .= '<div class="eclinicpro-quick-answer">' . "\n" . $img . "\n" . '</div>' . "\n";
        }
        $inner .= $itemsHtml . '</div>';

        $out[] = '<section class="implant-section">' . "\n"
            . '<div class="implant-content full">' . "\n"
            . $inner . "\n"
            . '</div>' . "\n" . '</section>';
    }

    // --- Tests you may need ------------------------------------------------
    if (!empty($b['lab_tests'])) {
        $items = '';
        foreach ((array) $b['lab_tests'] as $t) {
            $items .= "\n \t" . '<li><strong>' . fb_e((string) ($t['name'] ?? '')) . '</strong> — '
                . fb_e((string) ($t['what_it_shows'] ?? '')) . '</li>';
        }
        $out[] = fb_h2('Tests Your Doctor May Suggest')
            . '<ul class="implant-list">' . $items . "\n" . '</ul>'
            . '<p class="ecp-note">Your doctor decides which tests you actually need — many people need none of these.</p>';
    }

    // --- Treatment options: comparison-section table -------------------------
    if (!empty($b['treatment_options'])) {
        $rows = '';
        foreach ((array) $b['treatment_options'] as $t) {
            $rows .= '<tr>' . "\n"
                . '<td><strong>' . fb_e((string) ($t['option'] ?? '')) . '</strong></td>' . "\n"
                . '<td>' . fb_e((string) ($t['what_it_is'] ?? '')) . '</td>' . "\n"
                . '<td>' . fb_e((string) ($t['sessions_time'] ?? '')) . '</td>' . "\n"
                . '<td>' . fb_e((string) ($t['suits_whom'] ?? '')) . '</td>' . "\n"
                . '<td>' . fb_e((string) ($t['indicative_cost_inr'] ?? '')) . '</td>' . "\n"
                . '</tr>' . "\n";
        }
        $out[] = '<section class="comparison-section">' . "\n"
            . fb_h2('Treatment Options Compared') . "\n"
            . '<div class="table-responsive">' . "\n"
            . '<table class="comparison-table">' . "\n"
            . '<thead>' . "\n" . '<tr>' . "\n"
            . '<th>Option</th>' . "\n" . '<th>What it is</th>' . "\n" . '<th>Sessions / time</th>' . "\n"
            . '<th>Suits whom</th>' . "\n" . '<th>Indicative cost (INR)</th>' . "\n"
            . '</tr>' . "\n" . '</thead>' . "\n"
            . '<tbody>' . "\n" . $rows . '</tbody>' . "\n"
            . '</table>' . "\n" . '</div>' . "\n"
            . '<p class="ecp-note">Costs are broad indicative ranges for Indian metro cities — please confirm with your clinic.</p>' . "\n"
            . '</section>';
    }

    // --- Step-by-step procedure: h2 + numbered h4 blocks (live-post style) ---
    if (!empty($b['procedure_steps'])) {
        $steps = '';
        $n = 0;
        foreach ((array) $b['procedure_steps'] as $s) {
            $n++;
            $steps .= "\n" . '<h4>' . $n . '. ' . fb_e((string) ($s['title'] ?? '')) . '</h4>' . "\n"
                . '<p class="isSelectedEnd">' . fb_e((string) ($s['description'] ?? '')) . '</p>' . "\n";
        }
        $out[] = fb_h2('Step-by-Step: What Happens') . $steps;
    }

    // --- Recovery timeline ----------------------------------------------------
    if (!empty($b['recovery_timeline'])) {
        $items = '';
        foreach ((array) $b['recovery_timeline'] as $p) {
            $items .= "\n \t" . '<li><strong>' . fb_e((string) ($p['phase'] ?? '')) . '</strong> — '
                . fb_e((string) ($p['what_to_expect'] ?? '')) . '</li>';
        }
        $out[] = fb_h2('Recovery: What to Expect') . '<ul class="implant-list">' . $items . "\n" . '</ul>';
    }

    // --- Key takeaways box --------------------------------------------------------
    $takeaways = array_values(array_filter((array) ($b['key_takeaways'] ?? []), 'is_string'));
    if ($takeaways !== []) {
        $lis = '';
        foreach ($takeaways as $t) { $lis .= "\n \t" . '<li>' . fb_md($t) . '</li>'; }
        $out[] = '<div class="ecp-quick-answer">' . "\n"
            . '<h4>Key takeaways</h4>' . "\n"
            . '<ul class="implant-list">' . $lis . "\n" . '</ul>' . "\n"
            . '</div>';
    }

    // --- Doctor CTA banner (content raw, no <p>) --------------------------------
    $ctaWhere = $city !== '' ? ' in ' . fb_e($city) : ' near you';
    $out[] = '<div class="ecp-cta-banner">' . "\n"
        . '<h3>Talk to a verified ' . fb_e($specialty) . $ctaWhere . '</h3>' . "\n"
        . 'Book an appointment in 30 seconds on eClinicPro.' . "\n\n"
        . '<a class="ecp-cta-btn" href="' . fb_e($findUrl) . '">Find a doctor</a>' . "\n\n"
        . '</div>';

    // --- Myth vs Fact: faq-item cards (answer raw, no <p>) -----------------------
    if (!empty($b['myths'])) {
        $items = '';
        foreach ((array) $b['myths'] as $m) {
            $items .= '<div class="faq-item">' . "\n"
                . '<h4>Myth: ' . fb_e((string) ($m['myth'] ?? '')) . '</h4>' . "\n"
                . '<strong>Fact:</strong> ' . fb_md((string) ($m['fact'] ?? '')) . "\n\n"
                . '</div>' . "\n";
        }
        $out[] = fb_h2('Myths vs Facts') . "\n" . $items;
    }

    // --- FAQ section (live-post structure) + FAQPage schema ------------------------
    if (!empty($b['faq'])) {
        $items = '';
        $schema = [];
        foreach ((array) $b['faq'] as $f) {
            $q = (string) ($f['q'] ?? '');
            $a = (string) ($f['a'] ?? '');
            if ($q === '') continue;
            $items .= '<div class="faq-item">' . "\n"
                . '<h4>' . fb_e($q) . '</h4>' . "\n"
                . fb_md($a) . "\n\n"
                . '</div>' . "\n";
            $schema[] = [
                '@type' => 'Question',
                'name'  => $q,
                // schema text is plain — strip the **bold** markers
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => str_replace('**', '', $a)],
            ];
        }
        $jsonLd = json_encode([
            '@context'   => 'https://schema.org',
            '@type'      => 'FAQPage',
            'mainEntity' => $schema,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $out[] = '<section>' . "\n\n"
            . '<h2 id="' . fb_heading_id('Frequently Asked Questions') . '" class="text-center mb-5">Frequently Asked Questions</h2>' . "\n"
            . $items
            . '</section>' . "\n"
            . '<script type="application/ld+json">' . $jsonLd . '</script>';
    }

    // --- Related reading: internal link to this specialty's blog archive ---------------
    if ($specGroup !== '') {
        $catUrl = $wpBase . '/category/' . fb_slugify($specGroup) . '/';
        $out[] = '<p class="ecp-related">Want to read more? Browse all our '
            . '<a href="' . fb_e($catUrl) . '">' . fb_e($specGroup) . ' health guides</a>.</p>';
    }

    // --- Closing CTA + mandatory disclaimer ------------------------------------------
    $out[] = '<p class="ecp-cta-center"><a class="ecp-cta-btn" href="' . fb_e($findUrl) . '">'
        . 'Consult a verified ' . fb_e($specialty) . ' — book in 30 seconds</a></p>';

    $out[] = '<p class="ecp-disclaimer">'
        . 'This article is general health information, not medical advice. It does not replace a consultation, '
        . 'diagnosis, or treatment by a qualified doctor. If you have severe or worsening symptoms, see a doctor immediately.</p>';

    $html = implode("\n", $out);

    // --- SEO post-pass: MedicalWebPage JSON-LD (workbook: "MedicalWebPage-
    //     appropriate schema"; Yoast's Article schema sits alongside this)
    $meta = is_array($b['meta'] ?? null) ? $b['meta'] : [];
    $medSchema = json_encode([
        '@context'    => 'https://schema.org',
        '@type'       => 'MedicalWebPage',
        'name'        => (string) $row['title'],
        'description' => (string) ($meta['meta_description'] ?? ''),
        'about'       => ['@type' => 'MedicalCondition', 'name' => (string) $row['keyword']],
        'audience'    => 'https://schema.org/Patient',
        'inLanguage'  => 'en-IN',
        'lastReviewed' => date('Y-m-d'),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $html .= "\n" . '<script type="application/ld+json">' . $medSchema . '</script>';

    return $html;
}

/**
 * Standalone local preview page (saved to output/) pulling the live
 * child-theme stylesheet + the new block styles so it renders like the
 * blog will.
 * @param array<string,mixed> $row
 */
function fb_render_preview_page(array $row, string $contentHtml): string {
    $wpBase = rtrim(fb_env('WP_BASE_URL', 'https://eclinicpro.com/blog'), '/');
    $css = $wpBase . '/wp-content/themes/eclinicpro-blog/style.css';
    $blocksCss = (string) @file_get_contents(__DIR__ . '/blog-blocks.css');
    return '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<meta name="robots" content="noindex,nofollow">'
        . '<title>' . fb_e((string) $row['title']) . ' — preview</title>'
        . '<link rel="stylesheet" href="' . fb_e($css) . '">'
        . '<style>body{font-family:-apple-system,Segoe UI,Roboto,sans-serif;margin:0;background:#fafafa;color:#222;}'
        . '.preview-note{background:#fff8e6;border-bottom:1px solid #f1e2b0;padding:10px 16px;font-size:13px;text-align:center;}'
        . '.wrap{max-width:900px;margin:0 auto;padding:30px 18px 60px;background:#fff;}h1{line-height:1.3;}'
        . $blocksCss . '</style></head>'
        . '<body><div class="preview-note">Local preview — the real draft is in WordPress. Grey boxes are image placeholders you replace in wp-admin.</div>'
        . '<div class="wrap ecp-single-content ecp-post-body"><h1>' . fb_e((string) $row['title']) . '</h1>'
        . $contentHtml . '</div></body></html>';
}
