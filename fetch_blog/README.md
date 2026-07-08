# fetch_blog — internal 90-day blog builder

Turns the **Blog Calendar (90)** tab of `document/eClinicPro_90Day_7City_Workbook.xlsx`
into WordPress drafts, one click per blog:

```
Create button → Claude writes the guide (structured JSON blocks)
             → Gemini draws the branded hero image
             → hero uploaded to WP media library
             → blocks rendered to HTML → WordPress DRAFT created
             → you review + publish in wp-admin
```

## Files

| File | What it does |
|---|---|
| `index.php` | Dashboard listing all 90 blogs with Create/Redo buttons |
| `generate.php` | AJAX endpoint that runs the pipeline for one day |
| `blogs.json` | The 90 calendar rows exported from the workbook |
| `_claude.php` | Claude Messages API call + medical-compliance system prompt |
| `_gemini.php` | Gemini image generation (fixed brand-style prompt) |
| `_wordpress.php` | WP REST API: media upload + draft post creation |
| `_render.php` | Block JSON → HTML (quick answer, checklist, tests box, treatment table, myths, FAQ + schema, CTA, disclaimer) |
| `_env.php` | .env loader, TOOL_KEY gate, state file helpers |
| `state/state.json` | Per-day status (created / error, WP links, review flags) — gitignored |
| `.env` | Credentials — **server only, gitignored**. See comments inside for where to get each key |

## Setup (on the server)

1. Upload the `fetch_blog/` folder.
2. Fill `fetch_blog/.env` (every key has instructions in its comment).
3. `chmod 755 fetch_blog/state` (created automatically if writable).
4. Open `https://yoursite.com/fetch_blog/?key=YOUR_TOOL_KEY`.

## Safety rules baked in

- Never claims a treatment "cures" anything (Drugs & Magic Remedies Act; strictest wording for homeopathy).
- Only mainstream medical consensus; Claude must push anything it is unsure of
  (and **every cost figure**) into `review_flags`, shown on the dashboard row.
- Simple 8th-grade English for Indian readers; INR indicative cost ranges.
- Every post ends with a "general information, not medical advice" disclaimer.
- Everything lands as **draft** — a human publishes.

## If a blog is regenerated ("Redo")

A **new** draft is created in WordPress; the old draft is not deleted — remove
it manually in wp-admin to avoid duplicates.
