<?php
// =====================================================================
// 404.php — branded site-wide "page not found". Wired via .htaccess
// ErrorDocument so any unknown URL gets a clean, on-brand page.
// =====================================================================
require_once __DIR__ . '/partials/helpers.php';

http_response_code(404);
header('X-Robots-Tag: noindex, nofollow');

$pageTitle = 'Page not found — eClinicPro';
$metaDesc  = 'The page you were looking for could not be found.';
$activePage = '';
$noindex   = true;

require __DIR__ . '/partials/header.php';
?>
<main class="err-404">
    <div class="wrap" style="text-align:center; max-width:560px;">
        <div class="err-404-code">404</div>
        <h1 class="err-404-title">This page took a sick day.</h1>
        <p class="err-404-lede">
            The page you're looking for doesn't exist or has moved.
            Let's get you back on track.
        </p>
        <div class="err-404-ctas">
            <a href="/" class="btn btn-primary btn-lg">Back to home</a>
            <a href="/find-a-doctor" class="btn btn-ghost-dark btn-lg">Find a doctor →</a>
        </div>
    </div>
</main>

<style>
.err-404 { padding: 120px 0 140px; }
.err-404-code {
    font-size: clamp(72px, 16vw, 140px); font-weight: 300; line-height: 1;
    letter-spacing: -4px;
    background: linear-gradient(120deg, var(--teal-600), var(--teal-400));
    -webkit-background-clip: text; background-clip: text; -webkit-text-fill-color: transparent;
}
.err-404-title { font-size: clamp(24px, 4vw, 34px); font-weight: 600; margin: 18px 0 10px; }
.err-404-lede { font-size: 17px; color: var(--mute); line-height: 1.6; margin: 0 auto 32px; max-width: 420px; }
.err-404-ctas { display: flex; gap: 12px; justify-content: center; flex-wrap: wrap; }
</style>

<?php require __DIR__ . '/partials/footer.php'; ?>
