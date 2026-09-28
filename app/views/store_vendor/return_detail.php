<?php
/** @var array<string,mixed> $r */
$pageTitle = 'Return ' . $r['return_no'];
ob_start();
?>
<a href="/vendor/returns" class="text-sm text-[#17774f] hover:underline">← Returns</a>
<div class="mt-2">
<?php
$base = '/vendor/returns';
$isAdmin = false;
require dirname(__DIR__) . '/components/store_return_body.php';
?>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/_layout.php';
