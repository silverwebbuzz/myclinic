<?php
$raw = $_COOKIE['mc_impersonate'] ?? null;
$info = $raw ? json_decode($raw, true) : null;
if (!is_array($info) || empty($info['by'])) {
    return;
}
$until = (int) ($info['until'] ?? 0);
if ($until > 0 && $until < time()) {
    return;
}
?>
<div class="bg-amber-500 px-4 py-2 text-center text-sm font-medium text-amber-950">
    Support impersonation active (by <?= htmlspecialchars($info['by']) ?>) — expires in ~30 min.
    <a href="/impersonate/exit" class="ml-2 underline">Exit</a>
</div>
