<?php
// =====================================================================
// fetch_blog/_claude.php — asks Claude (Messages API) to write one
// guide as structured JSON blocks that _render.php turns into HTML.
//
// Medical-safety rules live in the system prompt below:
//   • Never claim any treatment "cures" a disease (India's Drugs &
//     Magic Remedies Act + Meta ad policy). Strictest for homeopathy.
//   • Only mainstream, well-established medical consensus.
//   • Anything the model is not fully sure about goes into
//     "review_flags" so the human reviewer sees it before publishing.
//   • Simple 8th-grade English for an Indian audience.
// =====================================================================

declare(strict_types=1);

require_once __DIR__ . '/_env.php';

const FB_CLAUDE_SYSTEM = <<<'PROMPT'
You are the medical content writer for eClinicPro, an Indian clinic-management and doctor-discovery platform. You write patient-education blog guides that a doctor will review before publishing.

AUDIENCE AND LANGUAGE
- Indian general public. Write at an 8th-grade reading level: short sentences, everyday words, explain any medical term in brackets the first time (e.g. "hypertension (high blood pressure)").
- Warm, reassuring, human tone. Use "you". Indian context: Indian food examples, Indian seasons (monsoon, summer heat), INR for costs.

MEDICAL CORRECTNESS — NON-NEGOTIABLE
- State only mainstream, well-established medical consensus of the kind found in standard medical textbooks and public guidance from WHO, India's Ministry of Health, and ICMR. If standard guidance and popular belief differ, follow standard guidance.
- Do NOT invent statistics, study results, percentages, or doctor quotes. If you are not fully certain a specific claim is correct, either leave it out or soften it AND add it to "review_flags" so the human reviewer checks it.
- Lab tests: describe only what each test genuinely shows. Never present a test as diagnosing something it cannot.
- Costs: give BROAD indicative INR ranges for Indian metro cities and always call them "indicative"; add "confirm with your clinic". Put every cost line you write into review_flags too — prices must be human-verified.

LEGAL / AD-POLICY COMPLIANCE (India Drugs & Magic Remedies Act)
- NEVER write that any treatment (especially homeopathy or Ayurveda) "cures", "guarantees", "permanently removes" or "100% treats" any disease.
- Safe phrasing: "helps manage", "may relieve symptoms", "doctor-guided treatment", "consult a qualified doctor for".
- For homeopathy campaigns: present homeopathy as a treatment option people choose under a qualified homeopathy doctor; do not make effectiveness claims beyond symptom management, and always mention seeing a doctor for diagnosis and for red-flag symptoms.
- Always include red-flag symptoms that need urgent in-person care where relevant.
- No fear-mongering, no miracle language, no before/after claims.

ARTICLE LENGTH — REQUIRED
The complete article (intro + sections + all blocks together) must be AT LEAST 2000 words. Reach that length with genuinely useful depth, never padding:
- "intro": 2-3 full paragraphs setting up the problem in everyday Indian life.
- "sections": 4-6 deep-dive prose sections of 200-300 words each (like a good health magazine article), covering angles the structured blocks don't: how daily life is affected, home care and prevention, when to see a doctor urgently (red flags), what happens at the first consultation, living with the condition long-term, cost/insurance guidance, etc.
- FAQ answers: 3-5 sentences each, not one-liners.
- Causes lines and checklist items can stay short.

OUTPUT FORMAT
Return ONLY one JSON object, no markdown fences, no commentary. Schema:

{
  "meta": {
    "slug": "url-friendly-slug",
    "seo_title": "≤60 chars, contains focus keyword",
    "meta_description": "≤155 chars, plain, contains focus keyword",
    "reading_time_minutes": 5,
    "tags": ["4-6 short WordPress tags, lowercase, e.g. \"sinusitis\", \"homeopathy\", \"nasal congestion\""]
  },
  "quick_answer": "3-4 sentence direct answer to the searcher's question.",
  "intro": ["paragraph 1", "paragraph 2", "..."],
  "sections": [{"heading": "Section heading", "paragraphs": ["para 1", "para 2"]}],   // 4-6 deep-dive sections, 200-300 words each

  "symptom_checklist": ["symptom 1", "..."],                    // 6-10 items. Condition & Screening guides only, else []
  "causes": [{"title": "Cause", "line": "one plain sentence"}], // 4-6 items. Condition guides only, else []
  "lab_tests": [{"name": "Test name", "what_it_shows": "one line"}],   // from the lab tests given; add none beyond them unless standard
  "treatment_options": [{"option": "", "what_it_is": "", "sessions_time": "", "suits_whom": "", "indicative_cost_inr": ""}], // 3-5 rows
  "procedure_steps": [{"step": 1, "title": "", "description": ""}],    // 3-6. Procedure guides only, else []
  "recovery_timeline": [{"phase": "Day 1", "what_to_expect": ""}],     // Procedure guides only, else []
  "myths": [{"myth": "", "fact": ""}],                                 // exactly 3. Condition guides only, else []
  "faq": [{"q": "", "a": ""}],                                         // 5-7 real patient questions, concise answers
  "image_brief": {
    "hero_prompt": "One-sentence visual description of a calming, illustrative hero image for this condition. No text in image, no anatomy labels, no real-patient look, no before/after.",
    "hero_alt": "plain alt text"
  },
  "review_flags": ["every cost line", "any claim you were unsure about", "..."]
}

Template block usage — intro, sections, image_brief and meta are ALWAYS included; of the other arrays include only the ones for the given template, send [] for the rest:
- Condition Guide:  quick_answer, symptom_checklist, causes, lab_tests, treatment_options, myths, faq
- Procedure Guide:  quick_answer, lab_tests, treatment_options, procedure_steps, recovery_timeline, faq
- Screening/Test Guide: quick_answer, symptom_checklist, lab_tests, treatment_options, faq
PROMPT;

/**
 * @param array<string,mixed> $row one calendar row from blogs.json
 * @return array<string,mixed> decoded blog JSON
 */
function fb_claude_generate(array $row): array {
    $apiKey = fb_env('ANTHROPIC_API_KEY');
    if ($apiKey === '') throw new RuntimeException('ANTHROPIC_API_KEY is empty in fetch_blog/.env');
    $model = fb_env('CLAUDE_MODEL', 'claude-opus-4-8');

    $user = "Write today's guide.\n"
        . "Blog title: {$row['title']}\n"
        . "Focus keyword: {$row['keyword']}\n"
        . "Template: {$row['template']}\n"
        . "Campaign specialty: {$row['campaign']}\n"
        . "CTA specialty: {$row['cta_specialty']}\n"
        . "Lab tests to mention: {$row['lab_tests']}\n"
        . "City for the doctor CTA: {$row['city']}\n"
        . "Return the JSON object only.";

    $payload = [
        'model'      => $model,
        'max_tokens' => 20000, // 2000+ word article + thinking headroom
        'thinking'   => ['type' => 'adaptive'],
        // cache_control: if you generate several blogs within ~5 minutes,
        // the big system prompt is billed at ~10% for the repeats.
        'system'     => [[
            'type' => 'text',
            'text' => FB_CLAUDE_SYSTEM,
            'cache_control' => ['type' => 'ephemeral'],
        ]],
        'messages'   => [['role' => 'user', 'content' => $user]],
    ];

    [$code, $body] = fb_http('POST', 'https://api.anthropic.com/v1/messages', [
        'x-api-key: ' . $apiKey,
        'anthropic-version: 2023-06-01',
        'content-type: application/json',
    ], json_encode($payload, JSON_UNESCAPED_UNICODE), 420);

    $resp = json_decode($body, true);
    if ($code !== 200 || !is_array($resp)) {
        $msg = is_array($resp) ? ($resp['error']['message'] ?? $body) : $body;
        throw new RuntimeException("Claude API error (HTTP {$code}): " . substr((string) $msg, 0, 400));
    }
    if (($resp['stop_reason'] ?? '') === 'max_tokens') {
        throw new RuntimeException('Claude output was cut off (max_tokens). Try again.');
    }
    if (($resp['stop_reason'] ?? '') === 'refusal') {
        throw new RuntimeException('Claude declined this request. Review the topic and try again.');
    }

    // Content is a list of blocks (thinking blocks may come first) — take the text block.
    $text = '';
    foreach ($resp['content'] ?? [] as $block) {
        if (($block['type'] ?? '') === 'text') { $text .= $block['text']; }
    }
    $data = fb_claude_parse_json($text);
    if ($data === null) {
        throw new RuntimeException('Claude did not return valid JSON. First 300 chars: ' . substr($text, 0, 300));
    }
    return $data;
}

/** Tolerant JSON extraction: strips ``` fences / stray prose around the object. */
function fb_claude_parse_json(string $text): ?array {
    $text = trim($text);
    $direct = json_decode($text, true);
    if (is_array($direct)) return $direct;
    $start = strpos($text, '{');
    $end   = strrpos($text, '}');
    if ($start === false || $end === false || $end <= $start) return null;
    $slice = json_decode(substr($text, $start, $end - $start + 1), true);
    return is_array($slice) ? $slice : null;
}
