<?php
/** @var array<string, mixed> $bookingCtx */
$b = $bookingCtx;
$clinic = $b['clinic'];
$doctors = $b['doctors'];
$doctorId = (int) $b['doctorId'];
$days = $b['days'];
$bookConfig = $b['bookConfig'];
$confirmation = $b['confirmation'];
$csrf = (string) $b['csrf'];
$brandColor = (string) ($b['brandColor'] ?? '#0F9B6E');
$embedMode = false;
$patientName = (string) $b['patientName'];
$patientPhone = (string) $b['patientPhone'];
$patientLoggedIn = !empty($b['patientLoggedIn']);
$bookingError = $b['bookingError'] ?? null;

$patientPhoneDisplay = preg_replace('/\D/', '', $patientPhone);
if (strlen($patientPhoneDisplay) >= 10) {
    $patientPhoneDisplay = substr($patientPhoneDisplay, -10);
}

$bookRoot = dirname(__DIR__, 2) . '/app/views/book';
require $bookRoot . '/_widget.php';
require $bookRoot . '/_wizard_script.php';
