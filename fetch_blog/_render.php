<?php
// =====================================================================
// fetch_blog/_render.php — turns Claude's block JSON into post HTML
// using the SAME markup vocabulary as the live dental-implants post
// (eclinicpro-blog child theme on Neve):
//
//   <section class="implant-section">          section wrapper
//     <div class="implant-content full">       .full = single column
//       <div class="implant-text"> …           text column
//   ul.implant-list                            styled lists
//   .comparison-section > .table-responsive > table.comparison-table
//   .faq-item (h4 + p)                         FAQ cards
//
// Those classes are styled by the child theme's style.css (teal
// #00a884 headers etc.), so drafts look native on the blog. Blocks the
// theme has no class for (quick answer, checklist, myth/fact, CTA)
// use small inline styles in the same #00a884 palette.
// =====================================================================

declare(strict_types=1);

require_once __DIR__ . '/_env.php';

const FB_TEAL = '#00a884';

/**
 * @param array<string,mixed> $row  calendar row
 * @param array<string,mixed> $b    Claude's block JSON
 */
function fb_render_html(array $row, array $b): string {
    $teal = FB_TEAL;
    $city = (string) ($row['city'] ?? '');
    $specialty = (string) ($row['cta_specialty'] ?? 'Doctor');
    $mainSite = rtrim(fb_env('MAIN_SITE_URL', 'https://eclinicpro.com'), '/');
    $findUrl = $mainSite . '/find-a-doctor';

    $out = [];

    // --- Quick-answer box -------------------------------------------
    if (!empty($b['quick_answer'])) {
        $out[] = '<div style="background:#f0faf7;border-left:5px solid ' . $teal . ';border-radius:10px;padding:18px 22px;margin:0 0 30px;">'
            . '<strong style="color:' . $teal . ';">Quick answer</strong>'
            . '<p style="margin:8px 0 0;line-height:1.5;">' . fb_e($b['quick_answer']) . '</p></div>';
    }

    // --- Intro paragraphs ---------------------------------------------
    if (!empty($b['intro'])) {
        $paras = '';
        foreach ((array) $b['intro'] as $p) {
            if (is_string($p) && $p !== '') $paras .= '<p>' . fb_e($p) . '</p>';
        }
        if ($paras !== '') $out[] = fb_section_full($paras);
    }

    // --- Symptom checklist ------------------------------------------
    if (!empty($b['symptom_checklist'])) {
        $items = '';
        foreach ((array) $b['symptom_checklist'] as $s) {
            $items .= '<li>&#9744;&nbsp; ' . fb_e((string) $s) . '</li>';
        }
        $out[] = fb_section_full(
            '<h2>Do you have these signs?</h2>'
            . '<ul class="implant-list" style="list-style:none;">' . $items . '</ul>'
            . '<p><em>Ticking a few of these does not confirm a diagnosis — it means it is worth talking to a doctor.</em></p>'
        );
    }

    // --- Causes ------------------------------------------------------
    if (!empty($b['causes'])) {
        $items = '';
        foreach ((array) $b['causes'] as $c) {
            $items .= '<li><strong>' . fb_e((string) ($c['title'] ?? '')) . ':</strong> '
                . fb_e((string) ($c['line'] ?? '')) . '</li>';
        }
        $out[] = fb_section_full('<h2>What causes it?</h2><ul class="implant-list">' . $items . '</ul>');
    }

    // --- Deep-dive prose sections (bring the article to 2000+ words) --
    if (!empty($b['sections'])) {
        foreach ((array) $b['sections'] as $sec) {
            if (!is_array($sec)) continue;
            $heading = (string) ($sec['heading'] ?? '');
            $paras = '';
            foreach ((array) ($sec['paragraphs'] ?? []) as $p) {
                if (is_string($p) && $p !== '') $paras .= '<p>' . fb_e($p) . '</p>';
            }
            if ($paras === '') continue;
            $out[] = fb_section_full(($heading !== '' ? '<h2>' . fb_e($heading) . '</h2>' : '') . $paras);
        }
    }

    // --- Tests you may need (differentiator) -------------------------
    if (!empty($b['lab_tests'])) {
        $items = '';
        foreach ((array) $b['lab_tests'] as $t) {
            $items .= '<li>&#128300;&nbsp; <strong>' . fb_e((string) ($t['name'] ?? '')) . '</strong> — '
                . fb_e((string) ($t['what_it_shows'] ?? '')) . '</li>';
        }
        $out[] = fb_section_full(
            '<h2>Tests your doctor may suggest</h2>'
            . '<ul class="implant-list" style="list-style:none;">' . $items . '</ul>'
            . '<p><em>Your doctor decides which tests you actually need — many people need none of these.</em></p>'
        );
    }

    // --- Treatment options table (theme comparison-table) ------------
    if (!empty($b['treatment_options'])) {
        $rows = '';
        foreach ((array) $b['treatment_options'] as $t) {
            $rows .= '<tr>'
                . '<td><strong>' . fb_e((string) ($t['option'] ?? '')) . '</strong></td>'
                . '<td>' . fb_e((string) ($t['what_it_is'] ?? '')) . '</td>'
                . '<td>' . fb_e((string) ($t['sessions_time'] ?? '')) . '</td>'
                . '<td>' . fb_e((string) ($t['suits_whom'] ?? '')) . '</td>'
                . '<td>' . fb_e((string) ($t['indicative_cost_inr'] ?? '')) . '</td>'
                . '</tr>';
        }
        $out[] = '<div class="comparison-section">'
            . '<h2>Treatment options compared</h2>'
            . '<div class="table-responsive"><table class="comparison-table"><thead>'
            . '<tr><th>Option</th><th>What it is</th><th>Sessions / time</th><th>Suits whom</th><th>Indicative cost (INR)</th></tr>'
            . '</thead><tbody>' . $rows . '</tbody></table></div>'
            . '<p style="font-size:14px;color:#777;margin-top:10px;">Costs are broad indicative ranges for Indian metro cities — please confirm with your clinic.</p>'
            . '</div>';
    }

    // --- Step-by-step procedure --------------------------------------
    if (!empty($b['procedure_steps'])) {
        $items = '';
        foreach ((array) $b['procedure_steps'] as $s) {
            $items .= '<li><strong>' . fb_e((string) ($s['title'] ?? '')) . ':</strong> '
                . fb_e((string) ($s['description'] ?? '')) . '</li>';
        }
        $out[] = fb_section_full('<h2>What happens, step by step</h2><ol class="implant-list">' . $items . '</ol>');
    }

    // --- Recovery timeline -------------------------------------------
    if (!empty($b['recovery_timeline'])) {
        $items = '';
        foreach ((array) $b['recovery_timeline'] as $p) {
            $items .= '<li><strong style="color:' . $teal . ';">' . fb_e((string) ($p['phase'] ?? '')) . '</strong> — '
                . fb_e((string) ($p['what_to_expect'] ?? '')) . '</li>';
        }
        $out[] = fb_section_full('<h2>Recovery: what to expect</h2><ul class="implant-list" style="list-style:none;">' . $items . '</ul>');
    }

    // --- Doctor CTA (stand-in for the live doctor-card widget) -------
    $out[] = '<div style="background:' . $teal . ';color:#fff;border-radius:12px;padding:26px 24px;margin:40px 0;text-align:center;">'
        . '<p style="margin:0 0 6px;font-size:22px;font-weight:700;color:#fff;">Talk to a verified ' . fb_e($specialty) . ' in ' . fb_e($city) . '</p>'
        . '<p style="margin:0 0 16px;font-size:15px;color:#eafaf5;">Book an appointment in 30 seconds on eClinicPro.</p>'
        . '<a href="' . fb_e($findUrl) . '" style="display:inline-block;background:#fff;color:' . $teal . ';font-weight:700;padding:11px 28px;border-radius:999px;text-decoration:none;">Find a doctor</a>'
        . '</div>';

    // --- Myth vs Fact -------------------------------------------------
    if (!empty($b['myths'])) {
        $items = '';
        foreach ((array) $b['myths'] as $m) {
            $items .= '<div class="faq-item">'
                . '<h4 style="color:#b91c1c;">Myth: ' . fb_e((string) ($m['myth'] ?? '')) . '</h4>'
                . '<p><strong style="color:' . $teal . ';">Fact:</strong> ' . fb_e((string) ($m['fact'] ?? '')) . '</p>'
                . '</div>';
        }
        $out[] = fb_section_full('<h2>Myths vs facts</h2>' . $items);
    }

    // --- FAQ (theme faq-item cards) + FAQPage schema -------------------
    if (!empty($b['faq'])) {
        $items = '';
        $schema = [];
        foreach ((array) $b['faq'] as $f) {
            $q = (string) ($f['q'] ?? '');
            $a = (string) ($f['a'] ?? '');
            if ($q === '') continue;
            $items .= '<div class="faq-item"><h4>' . fb_e($q) . '</h4><p>' . fb_e($a) . '</p></div>';
            $schema[] = [
                '@type' => 'Question',
                'name'  => $q,
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => $a],
            ];
        }
        $jsonLd = json_encode([
            '@context'   => 'https://schema.org',
            '@type'      => 'FAQPage',
            'mainEntity' => $schema,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $out[] = fb_section_full('<h2>Frequently asked questions</h2>' . $items)
            . '<script type="application/ld+json">' . $jsonLd . '</script>';
    }

    // --- Closing CTA + mandatory disclaimer ----------------------------
    $out[] = '<p style="text-align:center;margin:36px 0;"><a href="' . fb_e($findUrl) . '" '
        . 'style="display:inline-block;background:' . $teal . ';color:#fff;font-weight:700;padding:13px 32px;border-radius:999px;text-decoration:none;">'
        . 'Consult a verified ' . fb_e($specialty) . ' — book in 30 seconds</a></p>';

    $out[] = '<p style="font-size:13px;color:#777;border-top:1px solid #e5e5e5;padding-top:14px;">'
        . 'This article is general health information, not medical advice. It does not replace a consultation, '
        . 'diagnosis, or treatment by a qualified doctor. If you have severe or worsening symptoms, see a doctor immediately.</p>';

    return implode("\n", $out);
}

/** Theme section wrapper: single-column implant-section. */
function fb_section_full(string $inner): string {
    return '<section class="implant-section"><div class="implant-content full"><div class="implant-text">'
        . $inner . '</div></div></section>';
}

/**
 * Standalone local preview page (saved to output/) that pulls the live
 * child-theme stylesheet so the blocks render exactly like the blog.
 * @param array<string,mixed> $row
 */
function fb_render_preview_page(array $row, string $contentHtml, string $heroFile): string {
    $wpBase = rtrim(fb_env('WP_BASE_URL', 'https://eclinicpro.com/blog'), '/');
    $css = $wpBase . '/wp-content/themes/eclinicpro-blog/style.css';
    $hero = $heroFile !== ''
        ? '<div class="implant-image" style="margin:0 0 26px;"><img src="' . fb_e($heroFile) . '" alt="" style="width:100%;border-radius:12px;"></div>'
        : '';
    return '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<meta name="robots" content="noindex,nofollow">'
        . '<title>' . fb_e((string) $row['title']) . ' — preview</title>'
        . '<link rel="stylesheet" href="' . fb_e($css) . '">'
        . '<style>body{font-family:-apple-system,Segoe UI,Roboto,sans-serif;margin:0;background:#fafafa;color:#222;}'
        . '.preview-note{background:#fff8e6;border-bottom:1px solid #f1e2b0;padding:10px 16px;font-size:13px;text-align:center;}'
        . '.wrap{max-width:840px;margin:0 auto;padding:30px 18px 60px;background:#fff;}h1{line-height:1.3;}</style></head>'
        . '<body><div class="preview-note">Local preview — the real draft is in WordPress. Fonts/menu differ slightly from the live theme.</div>'
        . '<div class="wrap ecp-single-content ecp-post-body"><h1>' . fb_e((string) $row['title']) . '</h1>'
        . $hero . $contentHtml . '</div></body></html>';
}
