<?php
$title = 'Register — ManageClinic';
ob_start();
?>
<div class="rounded-xl border border-slate-200 bg-white p-8 shadow-sm">
    <h1 class="text-xl font-semibold text-slate-900">Start your clinic</h1>
    <p class="mt-1 text-sm text-slate-500">14-day free trial · No credit card required</p>

    <?php if (!empty($google)): ?>
        <p class="mt-4 rounded-lg bg-blue-50 px-3 py-2 text-sm text-blue-800">
            Completing sign-up for <?= htmlspecialchars($google['email']) ?> via Google.
        </p>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
        <div class="mt-4 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post" action="/register" class="mt-6 space-y-4" x-data="registerForm()">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">

        <div>
            <label class="block text-xs font-medium text-slate-600">Clinic name</label>
            <input name="clinic_name" type="text" required value="<?= htmlspecialchars($old['clinicName'] ?? '') ?>"
                   @input="suggestSlug($event.target.value)"
                   class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
        </div>

        <!-- Slug is auto-generated from the clinic name, hidden from the user.
             Backend uniqueness handler resolves collisions by appending a number. -->
        <input name="slug" type="hidden" x-model="slug">

        <div>
            <label class="block text-xs font-medium text-slate-600">Email</label>
            <input name="email" type="email" required value="<?= htmlspecialchars($old['email'] ?? ($google['email'] ?? '')) ?>"
                   <?= !empty($google) ? 'readonly class="mt-1 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm"' : 'class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none"' ?>>
        </div>

        <?php if (empty($google)): ?>
        <div>
            <label class="block text-xs font-medium text-slate-600">Password</label>
            <input name="password" type="password" required minlength="8"
                   @input="checkStrength($event.target.value)"
                   class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none">
            <div class="mt-1 h-1 rounded bg-slate-200"><div class="h-1 rounded bg-emerald-500 transition-all" :style="'width:' + strength + '%'"></div></div>
            <p class="mt-1 text-xs text-slate-400">8+ chars, 1 uppercase, 1 number</p>
        </div>

        <div>
            <label class="block text-xs font-medium text-slate-600">Confirm password</label>
            <input name="password_confirm" type="password" required
                   class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none">
        </div>
        <?php endif; ?>

        <button type="submit" class="w-full rounded-lg bg-emerald-600 py-2.5 text-sm font-medium text-white hover:bg-emerald-700">
            Create clinic account
        </button>
    </form>

    <p class="mt-4 text-center text-sm text-slate-500">
        Already have an account? <a href="/login" class="text-emerald-600 hover:underline">Log in</a>
    </p>
</div>
<script>
function registerForm() {
    return {
        slug: <?= json_encode($old['slug'] ?? '') ?>,
        strength: 0,
        suggestSlug(name) {
            // Slug is auto-derived from clinic name. Backend will resolve any
            // collisions by appending a number.
            this.slug = name.toLowerCase()
                .replace(/[^a-z0-9]+/g, '-')
                .replace(/^-|-$/g, '')
                .slice(0, 60);
        },
        checkStrength(pw) {
            let s = 0;
            if (pw.length >= 8) s += 33;
            if (/[A-Z]/.test(pw)) s += 33;
            if (/[0-9]/.test(pw)) s += 34;
            this.strength = s;
        }
    };
}
</script>
<?php
$content = ob_get_clean();
require dirname(__DIR__) . '/layouts/guest.php';
