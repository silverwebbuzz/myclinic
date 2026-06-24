<?php
/** @var array<string, mixed> $bookingCtx */
if (($bookingCtx['mode'] ?? '') === 'claimed') {
    require __DIR__ . '/profile-booking-claimed.php';
} else {
    require __DIR__ . '/profile-lead-widget.php';
}
