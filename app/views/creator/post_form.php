<?php
/**
 * /creator/posts/new?type= and /creator/posts/{id} — recipe / exercise / tip /
 * video editor (moved here from eclinicpro.com/my-health-tips). Saving goes
 * through the same partials/health_tips.php functions as before.
 *
 * @var array<string,mixed> $creator
 * @var array<string,mixed> $form
 * @var array<string,mixed>|null $tip
 * @var array<string,array<string,string>> $types
 * @var array<string,string> $groups
 * @var array<string,list<array<string,mixed>>> $tagsByGroup
 * @var bool $locked
 * @var bool $canSubmit
 * @var array{limit:int,used:int,balance:int,counted:bool}|null $quota
 */
use App\Services\Wellness\Posts;

$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$v = static fn (string $k): string => htmlspecialchars((string) ($form[$k] ?? ''), ENT_QUOTES, 'UTF-8');
$type = (string) $form['type'];
$label = $types[$type]['label'] ?? 'Post';
$id = (int) $form['id'];
$status = (string) $form['status'];
$statusLabel = ['draft' => 'Draft', 'submitted' => 'In review', 'changes_requested' => 'Changes needed', 'rejected' => 'Not accepted', 'published' => 'Live', 'unpublished' => 'Unpublished'][$status] ?? $status;
$pageTitle = ($id ? 'Edit ' : 'New ') . strtolower($label);
$in = 'mt-1 h-9 w-full rounded-[7px] border border-ln bg-sf px-3 text-[13px] outline-none focus:border-ac';
$ta = 'mt-1 w-full rounded-[7px] border border-ln bg-sf px-3 py-2 text-[13px] outline-none focus:border-ac';
$lb = 'block text-[12.5px] font-medium text-tx2';
$hint = 'mt-0.5 block text-[11.5px] font-normal text-tx3';
$card = 'rounded-[10px] border border-ln bg-sf p-4 md:p-5';
$placeholder = ['recipe' => 'e.g. Roasted chana protein chilla', 'exercise' => 'e.g. 10-minute desk stretch routine', 'tip' => 'e.g. Drink a glass of water before every meal', 'video' => 'e.g. 5-minute morning mobility'][$type] ?? '';
$publicUrl = $id ? Posts::publicUrl(['type' => $type, 'slug' => $form['slug']]) : null;
ob_start();
?>
<div class="max-w-3xl">
    <a href="/creator/content" class="text-[12.5px] text-tx3 hover:underline">← Recipes, tips &amp; videos</a>
    <div class="mt-1 flex flex-wrap items-center gap-2">
        <h1 class="text-[17px] font-semibold"><?= $id ? 'Edit' : 'New' ?> <?= $e(strtolower($label)) ?></h1>
        <?php if ($id): ?><span class="rounded-full bg-sf2 px-2 py-0.5 text-[11.5px] font-medium text-tx2 ring-1 ring-ln"><?= $e($statusLabel) ?></span><?php endif; ?>
        <span class="flex-1"></span>
        <?php if ($publicUrl): ?><a href="<?= $e($publicUrl) ?>" target="_blank" rel="noopener" class="text-[12.5px] text-ac underline"><?= $status === 'published' ? 'View live ↗' : 'Preview ↗' ?></a><?php endif; ?>
    </div>

    <?php if ($form['review_note'] && in_array($status, ['changes_requested', 'rejected'], true)): ?>
        <p class="mt-3 rounded-[8px] bg-erb px-3 py-2.5 text-[13px] text-er"><strong>From our team:</strong> <?= $e($form['review_note']) ?></p>
    <?php endif; ?>

    <?php if ($locked): ?>
        <section class="<?= $card ?> mt-4">
            <p class="text-[13px] text-tx2">
                <?php if ($status === 'submitted'): ?>This post is <strong>waiting for review</strong>. To change something, move it back to draft first.
                <?php elseif ($status === 'published'): ?>This post is <strong>live</strong>. Editing takes it offline until we review it again (no extra charge).
                <?php else: ?>This post wasn’t accepted, so it can’t be edited. You can delete it, or start a new post.<?php endif; ?>
            </p>
            <?php if (in_array($status, ['submitted', 'published'], true)): ?>
                <form method="post" action="/creator/posts/<?= $id ?>/withdraw" class="mt-3" onsubmit="return confirm('Move this post back to draft? It will leave review / go offline until you send it again.');">
                    <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
                    <button class="h-8 rounded-[7px] border border-ln bg-sf px-3 text-[12.5px] font-medium hover:bg-sf2"><?= $status === 'published' ? 'Unpublish & edit' : 'Move back to draft' ?></button>
                </form>
            <?php endif; ?>
        </section>
    <?php else: ?>
    <form method="post" action="<?= $id ? '/creator/posts/' . $id : '/creator/posts' ?>" enctype="multipart/form-data" class="mt-4 space-y-4"
          x-data='htEditor(<?= json_encode(['ingredients' => $form['ingredients'], 'steps' => $form['steps']], JSON_HEX_APOS | JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>)'>
        <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
        <input type="hidden" name="type" value="<?= $e($type) ?>">

        <section class="<?= $card ?> space-y-3">
            <label class="<?= $lb ?>">Title *
                <input name="title" maxlength="160" required value="<?= $v('title') ?>" placeholder="<?= $e($placeholder) ?>" class="<?= $in ?>"></label>
            <label class="<?= $lb ?>">Short summary * <span class="<?= $hint ?>">1–2 lines, shown on the card (20–300 characters)</span>
                <textarea name="summary" rows="2" maxlength="300" class="<?= $ta ?>"><?= $v('summary') ?></textarea></label>
            <label class="<?= $lb ?>"><?= $type === 'tip' ? 'The tip *' : ($type === 'video' ? 'About this video' : 'Intro / notes') ?>
                <span class="<?= $hint ?>"><?= $type === 'tip' ? 'Explain the habit and why it helps — in plain words.' : ($type === 'video' ? 'Optional: what it shows, key points, who it is for.' : 'Optional: the story, tips, swaps, storage.') ?></span>
                <textarea name="body" rows="<?= $type === 'tip' ? 8 : 4 ?>" maxlength="8000" class="<?= $ta ?>"><?= $v('body') ?></textarea></label>
        </section>

        <?php if ($type === 'recipe'): ?>
            <section class="<?= $card ?>">
                <h2 class="text-[14px] font-semibold">Recipe details</h2>
                <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-5">
                    <label class="<?= $lb ?>">Servings<input type="number" name="servings" min="1" max="50" value="<?= $v('servings') ?>" class="<?= $in ?>"></label>
                    <label class="<?= $lb ?>">Prep (min)<input type="number" name="prep_minutes" min="0" max="1440" value="<?= $v('prep_minutes') ?>" class="<?= $in ?>"></label>
                    <label class="<?= $lb ?>">Cook (min)<input type="number" name="cook_minutes" min="0" max="1440" value="<?= $v('cook_minutes') ?>" class="<?= $in ?>"></label>
                    <label class="<?= $lb ?>">Protein / serving (g)<input type="number" name="protein_g" min="0" max="999" step="0.1" value="<?= $v('protein_g') ?>" class="<?= $in ?>"></label>
                    <label class="<?= $lb ?>">Calories / serving<input type="number" name="calories_kcal" min="0" max="5000" value="<?= $v('calories_kcal') ?>" class="<?= $in ?>"></label>
                </div>
                <p class="mt-1 text-[11.5px] text-tx3">Nutrition is optional — give your honest estimate; it is shown as approximate.</p>
                <h3 class="mt-4 text-[13px] font-semibold">Ingredients *</h3>
                <template x-for="(row, i) in ingredients" :key="row.k">
                    <div class="mt-2 flex gap-2">
                        <input name="ing_qty[]" maxlength="40" placeholder="1 cup" x-model="row.quantity" class="<?= str_replace('w-full', 'w-28', $in) ?> !mt-0">
                        <input name="ing_item[]" maxlength="160" placeholder="roasted chana, ground" x-model="row.item" class="<?= $in ?> !mt-0">
                        <button type="button" class="h-9 w-9 flex-none rounded-[7px] border border-ln text-tx3 hover:bg-sf2" @click="ingredients.splice(i, 1)" aria-label="Remove ingredient">×</button>
                    </div>
                </template>
                <button type="button" class="mt-2 text-[12.5px] font-medium text-ac underline" @click="addIngredient()">+ Add ingredient</button>
            </section>
        <?php endif; ?>

        <?php if ($type === 'exercise'): ?>
            <section class="<?= $card ?>">
                <h2 class="text-[14px] font-semibold">Exercise details</h2>
                <div class="mt-3 grid gap-3 sm:grid-cols-3">
                    <label class="<?= $lb ?>">Duration (min)<input type="number" name="duration_minutes" min="1" max="600" value="<?= $v('duration_minutes') ?>" class="<?= $in ?>"></label>
                    <label class="<?= $lb ?>">Difficulty
                        <select name="difficulty" class="<?= $in ?>">
                            <option value="">—</option>
                            <?php foreach (['easy' => 'Easy', 'moderate' => 'Moderate', 'hard' => 'Hard'] as $k => $l): ?><option value="<?= $k ?>" <?= ($form['difficulty'] ?? '') === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?>
                        </select></label>
                    <label class="<?= $lb ?>">Reps / sets<input name="reps_sets" maxlength="80" placeholder="3 sets × 12 reps" value="<?= $v('reps_sets') ?>" class="<?= $in ?>"></label>
                    <label class="<?= $lb ?>">Equipment<input name="equipment" maxlength="160" placeholder="Yoga mat, none…" value="<?= $v('equipment') ?>" class="<?= $in ?>"></label>
                    <label class="<?= $lb ?>">Body area<input name="body_area" maxlength="80" placeholder="Back, core, legs…" value="<?= $v('body_area') ?>" class="<?= $in ?>"></label>
                </div>
                <label class="<?= $lb ?> mt-3">Who should avoid it / be careful <span class="<?= $hint ?>">e.g. recent surgery, pregnancy, knee pain — shown prominently</span>
                    <textarea name="precautions" rows="2" maxlength="600" class="<?= $ta ?>"><?= $v('precautions') ?></textarea></label>
            </section>
        <?php endif; ?>

        <?php if ($type === 'recipe' || $type === 'exercise'): ?>
            <section class="<?= $card ?>">
                <h2 class="text-[14px] font-semibold"><?= $type === 'recipe' ? 'Method' : 'How to do it' ?> *</h2>
                <template x-for="(row, i) in steps" :key="row.k">
                    <div class="mt-2 flex items-start gap-2">
                        <span class="mt-2 grid h-6 w-6 flex-none place-items-center rounded-full bg-acs text-[11.5px] font-semibold text-act" x-text="i + 1"></span>
                        <textarea name="step[]" rows="2" maxlength="1000" x-model="row.body" placeholder="Describe this step" class="<?= $ta ?> !mt-0"></textarea>
                        <button type="button" class="h-9 w-9 flex-none rounded-[7px] border border-ln text-tx3 hover:bg-sf2" @click="steps.splice(i, 1)" aria-label="Remove step">×</button>
                    </div>
                </template>
                <button type="button" class="mt-2 text-[12.5px] font-medium text-ac underline" @click="addStep()">+ Add step</button>
            </section>
        <?php endif; ?>

        <section class="<?= $card ?>">
            <h2 class="text-[14px] font-semibold">Photos &amp; video</h2>
            <?php if ($form['media']): ?>
                <div class="mt-3 grid grid-cols-3 gap-2 sm:grid-cols-6">
                    <?php foreach ($form['media'] as $i => $m): ?>
                        <label class="relative block overflow-hidden rounded-[8px] border border-ln">
                            <img src="<?= $e(Posts::imageUrl((int) $m['id'])) ?>" alt="" class="aspect-square w-full object-cover">
                            <?php if ($i === 0): ?><span class="absolute left-1 top-1 rounded bg-ac px-1.5 text-[10px] font-semibold text-white">Cover</span><?php endif; ?>
                            <span class="flex items-center gap-1 bg-sf px-1.5 py-1 text-[11px]"><input type="checkbox" name="remove_media[]" value="<?= (int) $m['id'] ?>"> Remove</span>
                        </label>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <label class="<?= $lb ?> mt-3">Add photos
                <span class="<?= $hint ?>">Up to 6 per post, JPG / PNG / WebP under 5 MB each. The first photo is the cover.<?= $type === 'recipe' ? ' At least one is required.' : ($type === 'video' ? ' Needed for Instagram reels (Instagram doesn’t share a preview picture); optional for YouTube.' : '') ?></span>
                <input type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple class="mt-1 block w-full text-[12.5px]"></label>
            <label class="<?= $lb ?> mt-3">Instagram reel or YouTube link<?= $type === 'video' ? ' *' : '' ?>
                <span class="<?= $hint ?>"><?= $type === 'video' ? 'Only your own video, from your own channel / handle.' : 'Optional.' ?> We embed it with credit — we never re-upload it.</span>
                <input type="url" name="video_url" maxlength="255" placeholder="https://www.instagram.com/reel/…" value="<?= $v('video_url') ?>" class="<?= $in ?>"></label>
        </section>

        <?php if ($tagsByGroup): ?>
            <section class="<?= $card ?>">
                <h2 class="text-[14px] font-semibold">Tags</h2>
                <?php foreach ($groups as $g => $gl): if (empty($tagsByGroup[$g])) { continue; } ?>
                    <div class="mt-3">
                        <div class="text-[11.5px] font-semibold uppercase tracking-wide text-tx3"><?= $e($gl) ?></div>
                        <div class="mt-1.5 flex flex-wrap gap-2">
                            <?php foreach ($tagsByGroup[$g] as $tg): $on = in_array((int) $tg['id'], $form['tag_ids'], true); ?>
                                <label class="cursor-pointer">
                                    <input type="checkbox" name="tags[]" value="<?= (int) $tg['id'] ?>" class="peer sr-only" <?= $on ? 'checked' : '' ?>>
                                    <span class="inline-block rounded-full border border-ln px-3 py-1 text-[12.5px] peer-checked:border-ac peer-checked:bg-acs peer-checked:text-act"><?= $e($tg['name']) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </section>
        <?php endif; ?>

        <p class="rounded-[8px] bg-wnb px-3 py-2.5 text-[12.5px] text-wn"><strong>Before you send:</strong> no claims that a food or exercise cures, treats or prevents a disease, and no medicine or supplement doses. Say “rich in protein”, not “reverses thyroid”. Posts with medical claims are sent back.</p>

        <?php if ($quota): ?>
            <p class="text-[12px] text-tx3">
                <?php if ($quota['counted']): ?>This post is already counted — sending it again is free.
                <?php else: ?><?= $e($types[$type]['plural'] ?? '') ?> this month: <?= min($quota['used'], $quota['limit']) ?> of <?= $quota['limit'] ?> free used · <?= $quota['balance'] ?> credit<?= $quota['balance'] === 1 ? '' : 's' ?>.
                    <?= $quota['used'] >= $quota['limit'] ? ($quota['balance'] > 0 ? 'Sending this uses 1 credit (returned if we don’t accept it).' : 'You’ll need a credit to send this.') : '' ?><?php endif; ?>
                <a href="/creator/credits" class="underline">Credits &amp; payments</a>
            </p>
        <?php endif; ?>

        <div class="sticky bottom-0 -mx-3 flex flex-wrap gap-2 border-t border-ln bg-bg/95 px-3 py-3 backdrop-blur md:static md:mx-0 md:border-0 md:bg-transparent md:p-0">
            <button name="intent" value="draft" formnovalidate class="h-9 rounded-[8px] border border-ln bg-sf px-4 text-[13px] font-medium hover:bg-sf2">Save draft</button>
            <button name="intent" value="submit" class="h-9 rounded-[8px] bg-ac px-4 text-[13px] font-semibold text-white hover:bg-act disabled:opacity-50" <?= $canSubmit ? '' : 'disabled title="Accept the Health Creator terms first"' ?>>Send for review</button>
        </div>
    </form>
    <?php endif; ?>

    <?php if ($id): ?>
        <form method="post" action="/creator/posts/<?= $id ?>/delete" class="mt-6 border-t border-ln pt-4" onsubmit="return confirm('Delete this post permanently? Its photos are removed too.');">
            <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
            <button class="text-[12.5px] text-er underline">Delete this post</button>
        </form>
    <?php endif; ?>
</div>
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
$content = ob_get_clean();
require __DIR__ . '/_layout.php';
