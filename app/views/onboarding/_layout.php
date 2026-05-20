<?php
$title = $title ?? 'Setup — ManageClinic';
ob_start();
echo $innerContent ?? '';
$content = ob_get_clean();
require dirname(__DIR__) . '/layouts/onboarding.php';
