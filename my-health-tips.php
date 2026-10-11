<?php
// =====================================================================
// my-health-tips.php — contributor studio for Health Tips.
//
// Invite-only: the visitor must be a logged-in patient identity that the
// admin has granted contributor access (health_tip_contributors). Views:
//   (default)          my posts + "new" buttons
//   ?view=profile      public profile + contributor terms
//   ?new=recipe|exercise|tip, ?edit={id}   editor
//
// Plain form POSTs (post/redirect/get). CSRF token = HMAC of the session
// cookie, on top of the SameSite=Lax patient cookie.
// =====================================================================
require_once __DIR__ . '/partials/helpers.php';
require_once __DIR__ . '/partials/patient_auth.php';
require_once __DIR__ . '/partials/health_tips.php';

header('Cache-Control: private, no-store, max-age=0');
header('Vary: Cookie');

$pageTitle  = 'My health tips — eClinicPro';
$metaDesc   = 'Write and manage your recipes, exercises and health tips on eClinicPro.';
$activePage = '';
$noindex    = true;

$me = ecp_patient_current();
$c  = $me ? ecp_ht_contributor_for_identity((int) $me['id']) : null;
$csrf = $me ? hash_hmac('sha256', 'ht-studio', (string) ($_COOKIE[ECP_PAT_COOKIE] ?? '')) : '';

$error = null;
$view  = 'list';
$form  = null;   // editor state when re-rendering after a failed save
$profileForm = null;

// ---------------------------------------------------------------------
// POST actions
// ---------------------------------------------------------------------
if ($me && $c && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrf, (string) ($_POST['_t'] ?? ''))) {
        header('Location: /my-health-tips?msg=expired');
        exit;
    }
    $action = (string) ($_POST['action'] ?? '');
    $cid = (int) $c['id'];

    if ($action === 'profile') {
        $res = ecp_ht_save_profile($c, $_POST, $_FILES['avatar'] ?? null);
        if ($res['ok']) {
            header('Location: /my-health-tips?msg=profile_saved');
            exit;
        }
        $error = $res['error'] ?? 'Could not save.';
        $view = 'profile';
        $profileForm = $_POST;
    } elseif ($action === 'save') {
        $id = (int) ($_POST['id'] ?? 0) ?: null;
        $submit = ($_POST['intent'] ?? '') === 'submit';
        $res = ecp_ht_save($c, $id, $_POST, $_FILES['photos'] ?? [], $submit);
        if ($res['ok']) {
            if ($submit) {
                ecp_ht_notify_admin_submitted($c, (int) $res['id']);
            }
            header('Location: ' . ($submit ? '/my-health-tips?msg=submitted' : '/my-health-tips?edit=' . (int) $res['id'] . '&msg=saved'));
            exit;
        }
        $error = ($res['error'] ?? 'Could not save.')
            . (!empty($_FILES['photos']['name'][0]) ? ' (Please choose your new photos again.)' : '');
        $view = 'editor';
        $form = ht_form_from_post($_POST, $id ? ecp_ht_find_owned($cid, $id) : null);
    } elseif ($action === 'withdraw') {
        $res = ecp_ht_withdraw($cid, (int) ($_POST['id'] ?? 0));
        header('Location: ' . ($res['ok'] ? '/my-health-tips?edit=' . (int) $_POST['id'] . '&msg=withdrawn' : '/my-health-tips?msg=failed'));
        exit;
    } elseif ($action === 'delete') {
        $res = ecp_ht_delete($cid, (int) ($_POST['id'] ?? 0));
        header('Location: /my-health-tips?msg=' . ($res['ok'] ? 'deleted' : 'failed'));
        exit;
    }
}

// ---------------------------------------------------------------------
// GET routing
// ---------------------------------------------------------------------
if ($me && $c && $error === null) {
    if (($_GET['view'] ?? '') === 'profile') {
        $view = 'profile';
    } elseif (isset($_GET['new']) && isset(ECP_HT_TYPES[(string) $_GET['new']])) {
        $view = 'editor';
        $form = ht_form_blank((string) $_GET['new']);
    } elseif (isset($_GET['edit'])) {
        $tip = ecp_ht_find_owned((int) $c['id'], (int) $_GET['edit']);
        if ($tip) {
            $view = 'editor';
            $form = ht_form_from_tip($tip);
        }
    }
}
if ($me && $c && $view === 'profile' && $profileForm === null) {
    $profileForm = $c;
}

$messages = [
    'profile_saved' => 'Profile saved.',
    'saved'         => 'Draft saved. Send it for review when you’re ready.',
    'submitted'     => 'Sent for review. We’ll check it and publish it, or let you know what to change.',
    'withdrawn'     => 'Moved back to draft — it is no longer public or in review. Edit and send it again.',
    'deleted'       => 'Post deleted.',
    'expired'       => 'Your session changed — please try again.',
    'failed'        => 'That didn’t work. Please try again.',
];
$flash = $messages[(string) ($_GET['msg'] ?? '')] ?? null;

$bodyClass = 'ht-page';
require __DIR__ . '/partials/header.php';
?>
<link rel="stylesheet" href="/assets/css/health-tips.css?v=<?= @filemtime(__DIR__ . '/assets/css/health-tips.css') ?: 1 ?>">

<main class="ht-studio">
  <div class="wrap ht-studio-wrap">

<?php if (!$me): ?>
    <section class="ht-gate">
      <div class="ht-gate-ic">🥗</div>
      <h1>My health tips</h1>
      <p>Sign in with your phone number to write and manage your recipes, exercises and tips.</p>
      <a class="btn btn-primary" href="/patient">Sign in</a>
    </section>

<?php elseif (!$c): ?>
    <section class="ht-gate">
      <div class="ht-gate-ic">✨</div>
      <h1>Health Tips is invite-only</h1>
      <p>Our recipes, exercises and tips come from a small group of contributors we know and trust. If you’d like to contribute, write to us at <a href="mailto:hello@eclinicpro.com">hello@eclinicpro.com</a>.</p>
      <a class="btn btn-ghost-dark" href="/health-tips">Browse health tips</a>
    </section>

<?php elseif ($c['status'] !== 'active'): ?>
    <section class="ht-gate">
      <div class="ht-gate-ic">⏸️</div>
      <h1>Your contributor access is paused</h1>
      <p>Your posts are hidden for now. Please write to <a href="mailto:hello@eclinicpro.com">hello@eclinicpro.com</a> if you have questions.</p>
    </section>

<?php else: ?>
    <header class="ht-studio-head">
      <div>
        <a class="ht-crumb" href="/patient">← My Health</a>
        <h1>My health tips</h1>
        <p class="ht-mute">Hi <?= e($c['display_name']) ?> — everything you post is reviewed by our team before it goes live.</p>
      </div>
      <nav class="ht-studio-tabs">
        <a href="/my-health-tips" class="<?= $view !== 'profile' ? 'is-active' : '' ?>">My posts</a>
        <a href="/my-health-tips?view=profile" class="<?= $view === 'profile' ? 'is-active' : '' ?>">Profile &amp; terms</a>
        <a href="/health-tips/by/<?= e($c['slug']) ?>" target="_blank" rel="noopener">Public page ↗</a>
      </nav>
    </header>

    <?php if ($flash): ?><div class="ht-flash"><?= e($flash) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="ht-flash is-error"><?= e($error) ?></div><?php endif; ?>

    <?php if (!ecp_ht_can_submit($c) && $view !== 'profile'): ?>
      <div class="ht-flash is-warn">
        Before your first post can go for review, please <a href="/my-health-tips?view=profile">complete your profile and accept the contributor terms</a>.
      </div>
    <?php endif; ?>

<?php if ($view === 'list'): ?>
    <?php $tips = ecp_ht_my_tips((int) $c['id']); ?>
    <?php if (trim((string) ($me['email'] ?? '')) === ''): ?>
      <div class="ht-flash is-warn">Want an email when your post goes live or needs changes? <a href="/patient?tab=profile">Add your email in My Profile</a>.</div>
    <?php endif; ?>
    <section class="ht-new">
      <?php foreach (ECP_HT_TYPES as $t => $meta): ?>
        <a class="ht-new-card" href="/my-health-tips?new=<?= $t ?>">
          <span class="ht-new-ic"><?= $meta['icon'] ?></span>
          <span><strong>New <?= e(strtolower($meta['label'])) ?></strong>
            <small><?= $t === 'recipe' ? 'Ingredients, method, photos' : ($t === 'exercise' ? 'Steps, duration, safety notes' : 'A short, useful habit') ?></small></span>
        </a>
      <?php endforeach; ?>
    </section>

    <?php if (!$tips): ?>
      <p class="ht-empty">No posts yet. Start with your favourite recipe!</p>
    <?php else: ?>
      <ul class="ht-mylist">
        <?php foreach ($tips as $t): ?>
          <li class="ht-myrow">
            <div class="ht-mythumb">
              <?php if ($t['cover_id']): ?>
                <img src="<?= e(ecp_ht_image_url((int) $t['cover_id'])) ?>" alt="" loading="lazy">
              <?php else: ?>
                <span><?= ECP_HT_TYPES[$t['type']]['icon'] ?></span>
              <?php endif; ?>
            </div>
            <div class="ht-mybody">
              <div class="ht-mymeta">
                <span class="ht-status is-<?= e($t['status']) ?>"><?= e(ECP_HT_STATUS_LABELS[$t['status']] ?? $t['status']) ?></span>
                <span><?= e(ECP_HT_TYPES[$t['type']]['label']) ?></span>
                <?php if ($t['status'] === 'published'): ?><span><?= (int) $t['view_count'] ?> views</span><span>♥ <?= (int) $t['save_count'] ?> saves</span><?php endif; ?>
              </div>
              <a class="ht-mytitle" href="/my-health-tips?edit=<?= (int) $t['id'] ?>"><?= e($t['title']) ?></a>
              <?php if ($t['review_note'] && in_array($t['status'], ['changes_requested', 'rejected'], true)): ?>
                <p class="ht-note"><strong>From our team:</strong> <?= e($t['review_note']) ?></p>
              <?php endif; ?>
            </div>
            <div class="ht-myact">
              <a class="btn btn-ghost-dark ht-btn-sm" href="/my-health-tips?edit=<?= (int) $t['id'] ?>"><?= ecp_ht_is_editable($t) ? 'Edit' : 'Open' ?></a>
              <?php if ($t['status'] === 'published'): ?>
                <a class="btn btn-ghost-dark ht-btn-sm" href="/health-tips/<?= e($t['slug']) ?>" target="_blank" rel="noopener">View ↗</a>
              <?php endif; ?>
            </div>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>

<?php elseif ($view === 'profile'): ?>
    <?php $p = $profileForm; ?>
    <form class="ht-card ht-form" method="post" action="/my-health-tips" enctype="multipart/form-data">
      <input type="hidden" name="_t" value="<?= e($csrf) ?>">
      <input type="hidden" name="action" value="profile">
      <h2>Your public profile</h2>
      <p class="ht-mute">Shown on every post you publish and on your page at <code>/health-tips/by/<?= e($c['slug']) ?></code>.</p>

      <div class="ht-avatar-row">
        <div class="ht-avatar">
          <?php if (!empty($c['avatar_path'])): ?>
            <img src="<?= e(ecp_ht_avatar_url($c['slug'])) ?>" alt="">
          <?php else: ?>
            <span><?= e(mb_strtoupper(mb_substr((string) $c['display_name'], 0, 1))) ?></span>
          <?php endif; ?>
        </div>
        <label class="ht-field">Profile photo <small>JPG/PNG/WebP, under 5 MB</small>
          <input type="file" name="avatar" accept="image/jpeg,image/png,image/webp"></label>
      </div>

      <div class="ht-grid2">
        <label class="ht-field">Display name *
          <input type="text" name="display_name" maxlength="120" required value="<?= e($p['display_name'] ?? '') ?>"></label>
        <label class="ht-field">Headline <small>e.g. Protein-rich vegetarian recipes</small>
          <input type="text" name="headline" maxlength="120" value="<?= e($p['headline'] ?? '') ?>"></label>
      </div>
      <label class="ht-field">About you
        <textarea name="bio" rows="4" maxlength="600" placeholder="Who you are, what you cook or teach, and why."><?= e($p['bio'] ?? '') ?></textarea></label>
      <div class="ht-grid3">
        <label class="ht-field">Instagram handle
          <input type="text" name="instagram_handle" maxlength="60" placeholder="yourhandle" value="<?= e($p['instagram_handle'] ?? '') ?>"></label>
        <label class="ht-field">YouTube channel link
          <input type="url" name="youtube_url" maxlength="255" placeholder="https://youtube.com/@…" value="<?= e($p['youtube_url'] ?? '') ?>"></label>
        <label class="ht-field">Website
          <input type="url" name="website_url" maxlength="255" placeholder="https://…" value="<?= e($p['website_url'] ?? '') ?>"></label>
      </div>

      <h2 id="terms">Contributor terms</h2>
      <?php require __DIR__ . '/partials/health_tips_terms.php'; ?>
      <?php if (!empty($c['terms_accepted_at'])): ?>
        <p class="ht-mute">✓ You accepted these terms on <?= e(date('d M Y', strtotime((string) $c['terms_accepted_at']))) ?>.</p>
      <?php else: ?>
        <label class="ht-check"><input type="checkbox" name="accept_terms" value="1" <?= !empty($p['accept_terms']) ? 'checked' : '' ?>>
          I have read and agree to the contributor terms above.</label>
      <?php endif; ?>

      <div class="ht-actions"><button class="btn btn-primary" type="submit">Save profile</button></div>
    </form>

<?php elseif ($view === 'editor'): ?>
    <?php
      $type = $form['type'];
      $locked = $form['id'] && !in_array($form['status'], ECP_HT_EDITABLE, true);
      $groups = $type === 'exercise' ? ['focus' => 'Focus'] : ($type === 'recipe' ? ['diet' => 'Diet', 'meal' => 'Meal', 'goal' => 'Good for'] : ['diet' => 'Diet', 'meal' => 'Meal', 'goal' => 'Good for', 'focus' => 'Focus']);
    ?>
    <a class="ht-crumb" href="/my-health-tips">← All my posts</a>
    <h2 class="ht-editor-title"><?= $form['id'] ? 'Edit' : 'New' ?> <?= e(strtolower(ECP_HT_TYPES[$type]['label'])) ?>
      <?php if ($form['id']): ?><span class="ht-status is-<?= e($form['status']) ?>"><?= e(ECP_HT_STATUS_LABELS[$form['status']]) ?></span><?php endif; ?></h2>

    <?php if ($form['review_note'] && in_array($form['status'], ['changes_requested', 'rejected'], true)): ?>
      <div class="ht-flash is-warn"><strong>From our team:</strong> <?= e($form['review_note']) ?></div>
    <?php endif; ?>

    <?php if ($locked): ?>
      <div class="ht-card ht-locked">
        <?php if ($form['status'] === 'submitted'): ?>
          <p>This post is <strong>waiting for review</strong>. To change something, move it back to draft first.</p>
        <?php elseif ($form['status'] === 'published'): ?>
          <p>This post is <strong>live</strong>. Editing takes it offline until we review it again.</p>
        <?php else: ?>
          <p>This post wasn’t accepted, so it can’t be edited. You can delete it, or start a new post.</p>
        <?php endif; ?>
        <div class="ht-actions">
          <?php if (in_array($form['status'], ['submitted', 'published'], true)): ?>
            <form method="post" action="/my-health-tips" onsubmit="return confirm('Move this post back to draft? It will leave review / go offline until you send it again.');">
              <input type="hidden" name="_t" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="withdraw"><input type="hidden" name="id" value="<?= (int) $form['id'] ?>">
              <button class="btn btn-ghost-dark" type="submit"><?= $form['status'] === 'published' ? 'Unpublish & edit' : 'Move back to draft' ?></button>
            </form>
          <?php endif; ?>
          <a class="btn btn-ghost-dark" href="/health-tips/<?= e($form['slug']) ?>" target="_blank" rel="noopener"><?= $form['status'] === 'published' ? 'View live ↗' : 'Preview ↗' ?></a>
        </div>
      </div>
    <?php else: ?>
    <form class="ht-form" method="post" action="/my-health-tips" enctype="multipart/form-data"
          x-data='htEditor(<?= json_encode(['ingredients' => $form['ingredients'], 'steps' => $form['steps']], JSON_HEX_APOS | JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>)'>
      <input type="hidden" name="_t" value="<?= e($csrf) ?>">
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="type" value="<?= e($type) ?>">
      <input type="hidden" name="id" value="<?= (int) $form['id'] ?>">

      <div class="ht-card">
        <label class="ht-field">Title *
          <input type="text" name="title" maxlength="160" required value="<?= e($form['title']) ?>"
            placeholder="<?= $type === 'recipe' ? 'e.g. Roasted chana protein chilla' : ($type === 'exercise' ? 'e.g. 10-minute desk stretch routine' : 'e.g. Drink a glass of water before every meal') ?>"></label>
        <label class="ht-field">Short summary * <small>1–2 lines, shown on the card (20–300 characters)</small>
          <textarea name="summary" rows="2" maxlength="300"><?= e($form['summary']) ?></textarea></label>
        <label class="ht-field"><?= $type === 'tip' ? 'The tip *' : 'Intro / notes' ?>
          <small><?= $type === 'tip' ? 'Explain the habit and why it helps — in plain words.' : 'Optional: the story, tips, swaps, storage.' ?></small>
          <textarea name="body" rows="<?= $type === 'tip' ? 8 : 4 ?>" maxlength="8000"><?= e($form['body']) ?></textarea></label>
      </div>

      <?php if ($type === 'recipe'): ?>
      <div class="ht-card">
        <h3>Recipe details</h3>
        <div class="ht-grid5">
          <label class="ht-field">Servings<input type="number" name="servings" min="1" max="50" value="<?= e((string) $form['servings']) ?>"></label>
          <label class="ht-field">Prep (min)<input type="number" name="prep_minutes" min="0" max="1440" value="<?= e((string) $form['prep_minutes']) ?>"></label>
          <label class="ht-field">Cook (min)<input type="number" name="cook_minutes" min="0" max="1440" value="<?= e((string) $form['cook_minutes']) ?>"></label>
          <label class="ht-field">Protein / serving (g)<input type="number" name="protein_g" min="0" max="999" step="0.1" value="<?= e((string) $form['protein_g']) ?>"></label>
          <label class="ht-field">Calories / serving<input type="number" name="calories_kcal" min="0" max="5000" value="<?= e((string) $form['calories_kcal']) ?>"></label>
        </div>
        <p class="ht-mute ht-small">Nutrition is optional — give your honest estimate; it is shown as approximate.</p>

        <h3>Ingredients *</h3>
        <template x-for="(row, i) in ingredients" :key="row.k">
          <div class="ht-ingrow">
            <input type="text" name="ing_qty[]" maxlength="40" placeholder="1 cup" x-model="row.quantity">
            <input type="text" name="ing_item[]" maxlength="160" placeholder="roasted chana, ground" x-model="row.item">
            <button type="button" class="ht-x" @click="ingredients.splice(i, 1)" aria-label="Remove ingredient">×</button>
          </div>
        </template>
        <button type="button" class="ht-add" @click="addIngredient()">+ Add ingredient</button>
      </div>
      <?php endif; ?>

      <?php if ($type === 'exercise'): ?>
      <div class="ht-card">
        <h3>Exercise details</h3>
        <div class="ht-grid3">
          <label class="ht-field">Duration (min)<input type="number" name="duration_minutes" min="1" max="600" value="<?= e((string) $form['duration_minutes']) ?>"></label>
          <label class="ht-field">Difficulty
            <select name="difficulty">
              <option value="">—</option>
              <?php foreach (['easy' => 'Easy', 'moderate' => 'Moderate', 'hard' => 'Hard'] as $k => $v): ?>
                <option value="<?= $k ?>" <?= $form['difficulty'] === $k ? 'selected' : '' ?>><?= $v ?></option>
              <?php endforeach; ?>
            </select></label>
          <label class="ht-field">Reps / sets<input type="text" name="reps_sets" maxlength="80" placeholder="3 sets × 12 reps" value="<?= e((string) $form['reps_sets']) ?>"></label>
          <label class="ht-field">Equipment<input type="text" name="equipment" maxlength="160" placeholder="Yoga mat, none…" value="<?= e((string) $form['equipment']) ?>"></label>
          <label class="ht-field">Body area<input type="text" name="body_area" maxlength="80" placeholder="Back, core, legs…" value="<?= e((string) $form['body_area']) ?>"></label>
        </div>
        <label class="ht-field">Who should avoid it / be careful <small>e.g. recent surgery, pregnancy, knee pain — shown prominently</small>
          <textarea name="precautions" rows="2" maxlength="600"><?= e((string) $form['precautions']) ?></textarea></label>
      </div>
      <?php endif; ?>

      <?php if ($type !== 'tip'): ?>
      <div class="ht-card">
        <h3><?= $type === 'recipe' ? 'Method' : 'How to do it' ?> *</h3>
        <template x-for="(row, i) in steps" :key="row.k">
          <div class="ht-steprow">
            <span class="ht-stepno" x-text="i + 1"></span>
            <textarea name="step[]" rows="2" maxlength="1000" x-model="row.body" placeholder="Describe this step"></textarea>
            <button type="button" class="ht-x" @click="steps.splice(i, 1)" aria-label="Remove step">×</button>
          </div>
        </template>
        <button type="button" class="ht-add" @click="addStep()">+ Add step</button>
      </div>
      <?php endif; ?>

      <div class="ht-card">
        <h3>Photos &amp; video</h3>
        <?php if ($form['media']): ?>
          <div class="ht-media-grid">
            <?php foreach ($form['media'] as $i => $m): ?>
              <label class="ht-media">
                <img src="<?= e(ecp_ht_image_url((int) $m['id'])) ?>" alt="">
                <?php if ($i === 0): ?><span class="ht-cover-badge">Cover</span><?php endif; ?>
                <span class="ht-media-rm"><input type="checkbox" name="remove_media[]" value="<?= (int) $m['id'] ?>"> Remove</span>
              </label>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
        <label class="ht-field">Add photos <small>Up to <?= ECP_HT_PHOTOS_PER_TIP ?> per post, JPG/PNG/WebP under 5 MB each. The first photo is the cover.<?= $type === 'recipe' ? ' At least one is required.' : '' ?></small>
          <input type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple></label>
        <label class="ht-field">Instagram reel or YouTube link <small>Optional — we embed your video (with credit), we never re-upload it.</small>
          <input type="url" name="video_url" maxlength="255" placeholder="https://www.instagram.com/reel/…" value="<?= e((string) $form['video_url']) ?>"></label>
      </div>

      <div class="ht-card">
        <h3>Tags</h3>
        <?php foreach ($groups as $g => $label): ?>
          <?php $gTags = ecp_ht_tags($g); if (!$gTags) continue; ?>
          <div class="ht-taggroup">
            <span class="ht-taglabel"><?= e($label) ?></span>
            <?php foreach ($gTags as $tg): ?>
              <label class="ht-tagpick"><input type="checkbox" name="tags[]" value="<?= (int) $tg['id'] ?>" <?= in_array((int) $tg['id'], $form['tag_ids'], true) ? 'checked' : '' ?>><span><?= e($tg['name']) ?></span></label>
            <?php endforeach; ?>
          </div>
        <?php endforeach; ?>
      </div>

      <div class="ht-card ht-rules">
        <strong>Before you send:</strong> no claims that a food or exercise cures, treats or prevents a disease, and no medicine or supplement doses. Say “rich in protein”, not “reverses thyroid”. Posts with medical claims are sent back.
      </div>

      <div class="ht-actions ht-actions-sticky">
        <?php if ($form['id']): ?>
          <a class="btn btn-ghost-dark" href="/health-tips/<?= e($form['slug']) ?>" target="_blank" rel="noopener">Preview ↗</a>
        <?php endif; ?>
        <button class="btn btn-ghost-dark" type="submit" name="intent" value="draft">Save draft</button>
        <button class="btn btn-primary" type="submit" name="intent" value="submit" <?= ecp_ht_can_submit($c) ? '' : 'disabled title="Complete your profile and accept the terms first"' ?>>Send for review</button>
      </div>
    </form>
    <?php endif; ?>

    <?php if ($form['id']): ?>
      <form class="ht-delete" method="post" action="/my-health-tips" onsubmit="return confirm('Delete this post permanently? Its photos are removed too.');">
        <input type="hidden" name="_t" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $form['id'] ?>">
        <button type="submit">Delete this post</button>
      </form>
    <?php endif; ?>
<?php endif; ?>
<?php endif; ?>

  </div>
</main>

<script>
  function htEditor(init) {
    let k = 0;
    const withKeys = (rows) => rows.map((r) => ({ ...r, k: ++k }));
    return {
      ingredients: withKeys(init.ingredients.length ? init.ingredients : [{ quantity: '', item: '' }, { quantity: '', item: '' }, { quantity: '', item: '' }]),
      steps: withKeys((init.steps.length ? init.steps : ['', '']).map((body) => ({ body }))),
      addIngredient() { this.ingredients.push({ quantity: '', item: '', k: ++k }); },
      addStep() { this.steps.push({ body: '', k: ++k }); },
    };
  }
</script>

<?php
require __DIR__ . '/partials/footer.php';

// ---------------------------------------------------------------------
// Editor state helpers
// ---------------------------------------------------------------------

/** @return array<string,mixed> */
function ht_form_blank(string $type): array
{
    return [
        'id' => 0, 'type' => $type, 'status' => 'draft', 'slug' => '', 'review_note' => null,
        'title' => '', 'summary' => '', 'body' => '', 'video_url' => '',
        'servings' => '', 'prep_minutes' => '', 'cook_minutes' => '', 'protein_g' => '', 'calories_kcal' => '',
        'duration_minutes' => '', 'difficulty' => '', 'reps_sets' => '', 'equipment' => '', 'body_area' => '', 'precautions' => '',
        'ingredients' => [], 'steps' => [], 'media' => [], 'tag_ids' => [],
    ];
}

/** @return array<string,mixed> */
function ht_form_from_tip(array $tip): array
{
    $f = ht_form_blank((string) $tip['type']);
    foreach ($f as $k => $_) {
        if (array_key_exists($k, $tip) && !is_array($tip[$k])) {
            $f[$k] = $tip[$k] ?? '';
        }
    }
    $f['id'] = (int) $tip['id'];
    $f['review_note'] = $tip['review_note'];
    $f['ingredients'] = array_map(static fn ($r) => ['quantity' => (string) $r['quantity'], 'item' => (string) $r['item']], $tip['ingredients']);
    $f['steps'] = array_map('strval', $tip['steps']);
    $f['media'] = $tip['media'];
    $f['tag_ids'] = array_map(static fn ($t) => (int) $t['id'], $tip['tags']);
    return $f;
}

/** Re-fill the editor from a rejected POST so nothing typed is lost. */
function ht_form_from_post(array $post, ?array $tip): array
{
    $type = $tip ? (string) $tip['type'] : (string) ($post['type'] ?? 'tip');
    $f = $tip ? ht_form_from_tip($tip) : ht_form_blank(isset(ECP_HT_TYPES[$type]) ? $type : 'tip');
    foreach ($f as $k => $_) {
        if (isset($post[$k]) && !is_array($post[$k]) && !in_array($k, ['id', 'type', 'status', 'slug', 'review_note'], true)) {
            $f[$k] = (string) $post[$k];
        }
    }
    $f['ingredients'] = [];
    foreach ((array) ($post['ing_item'] ?? []) as $i => $item) {
        $f['ingredients'][] = ['quantity' => (string) ($post['ing_qty'][$i] ?? ''), 'item' => (string) $item];
    }
    $f['steps'] = array_map('strval', (array) ($post['step'] ?? []));
    $f['tag_ids'] = array_map('intval', (array) ($post['tags'] ?? []));
    return $f;
}
