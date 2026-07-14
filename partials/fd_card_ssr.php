<?php
// =====================================================================
// fd_card_ssr.php — server-rendered doctor card for /find-a-doctor.
//
// WHY: the interactive card is drawn by Alpine's `x-for` only AFTER the JS
// boots, which pushed LCP "element render delay" to ~4.5s (the card address
// <span class="fd-wrap"> is the LCP element). This partial emits the SAME card
// as real HTML for the first page so it paints at TTFB (~240ms). Once Alpine
// initialises it hides this SSR block (#fd-ssr) and renders the live x-for grid,
// so there is never a double list.
//
// Keep the markup here visually in sync with the Alpine template in
// find-a-doctor.php (the `<template x-for="d in pageItems()">` block).
//
// Expects: $d  — one row from $firstPage (see ecp_shape_directory_row()).
// =====================================================================

/** @var array $d */
$fdName      = (string) ($d['name'] ?? 'Doctor');
$fdProfile   = (string) ($d['profile_url'] ?? '#');
$fdSpecLabel = (string) ($d['specLabel'] ?? '');
$fdHospital  = (string) ($d['hospital'] ?? '');
$fdRating    = (float) ($d['rating'] ?? 0);
$fdReviews   = (int) ($d['reviews'] ?? 0);
$fdGradient  = (int) ($d['avatar_gradient'] ?? 1);
$fdPhotoUrl  = $d['photo_url'] ?? null;
$fdInitials  = trim(((string) ($d['firstInitial'] ?? '')) . ((string) ($d['lastInitial'] ?? '')));
$fdVerified  = !empty($d['verified']);
$fdIsClaimed = !empty($d['is_claimed']);
$fdPhone     = $d['phone'] ?? null;
$fdGmaps     = $d['gmaps_url'] ?? null;
$fdWebsite   = $d['website'] ?? null;

// Address line (the LCP element). Falls back to area/city/state, same as JS.
$fdAddress = trim((string) ($d['address'] ?? ''));
if ($fdAddress === '') {
    $fdAddress = implode(', ', array_filter([
        $d['area'] ?? '', $d['city'] ?? '', $d['state'] ?? '',
    ], static fn ($v) => (string) $v !== ''));
}

// Fee label (mirror formatFee()).
$fdFee = (float) ($d['fee'] ?? 0);
$fdCurrency = (string) ($d['currency'] ?? '₹');

// Avatar style: photo as background, else gradient class shows initials.
$fdAvatarStyle = $fdPhotoUrl
    ? 'background-image:url(' . e((string) $fdPhotoUrl) . ');background-size:cover;background-position:center;color:transparent'
    : '';
?>
<div class="fd-card">
    <div class="fd-avatar g<?= $fdGradient ?>"<?= $fdAvatarStyle !== '' ? ' style="' . $fdAvatarStyle . '"' : '' ?>>
        <?php if (!$fdPhotoUrl): ?>
            <span><?= e($fdInitials !== '' ? $fdInitials : 'D') ?></span>
        <?php endif; ?>
    </div>

    <div class="fd-identity">
        <div class="fd-name-row">
            <a class="fd-name" href="<?= e($fdProfile) ?>" style="color:inherit;text-decoration:none"><?= e($fdName) ?></a>
            <?php if ($fdVerified): ?>
                <span class="fd-verified">✓ Verified</span>
            <?php endif; ?>
        </div>
        <?php if ($fdHospital !== ''): ?>
            <div class="fd-qual"><?= e($fdHospital) ?></div>
        <?php endif; ?>
        <div class="fd-spec-row">
            <span class="fd-pill"><?= e($fdSpecLabel) ?></span>
            <?php if ($fdRating > 0): ?>
                <span class="fd-rating">
                    <span class="star">★</span>
                    <span style="font-weight:600;color:var(--ink);"><?= e(number_format($fdRating, 1)) ?></span>
                    <span class="rv">(<?= $fdReviews ?>)</span>
                </span>
            <?php endif; ?>
        </div>
        <div class="fd-meta">
            <div class="fd-meta-row">
                <span class="mi"><img src="/assets/img/icon/maps.png" alt="Map Pin" width="16" height="16"></span>
                <span class="fd-wrap"><span><?= e($fdAddress) ?></span></span>
            </div>
            <?php if ($fdPhone): ?>
                <div class="fd-meta-row">
                    <span class="mi"><img src="/assets/img/icon/phone-call.png" alt="Phone Call" width="16" height="16" loading="lazy"></span>
                    <a href="tel:<?= e((string) $fdPhone) ?>"><?= e((string) $fdPhone) ?></a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="fd-book">
        <span class="fd-slot later">
            <span class="dot"></span>
            <span>Contact clinic</span>
        </span>
        <?php if ($fdFee > 0): ?>
            <div class="fd-price">Consultation <strong><?= e($fdCurrency . number_format($fdFee, 0)) ?></strong></div>
        <?php endif; ?>
        <div class="fd-actions">
            <?php if ($fdGmaps): ?>
                <a href="<?= e((string) $fdGmaps) ?>" target="_blank" rel="noopener" class="fd-btn">View on map</a>
            <?php elseif ($fdWebsite): ?>
                <a href="<?= e((string) $fdWebsite) ?>" target="_blank" rel="noopener" class="fd-btn">Website</a>
            <?php else: ?>
                <a href="<?= e($fdProfile) ?>" class="fd-btn">View profile</a>
            <?php endif; ?>
            <a href="<?= e($fdProfile) ?>" class="fd-btn primary"><img src="/assets/img/icon/book-appointment.png" alt="Smart Scheduling" width="16" height="16"> Book</a>
            <?php if ($fdPhone): ?>
                <a href="tel:<?= e((string) $fdPhone) ?>" class="fd-btn"><img src="/assets/img/icon/phone-call.png" alt="phone" width="16" height="16"> Call</a>
            <?php endif; ?>
        </div>
        <?php if ($fdIsClaimed): ?>
            <div class="fd-claim-link verified">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12" /></svg>
                Verified by doctor
            </div>
        <?php endif; ?>
    </div>
</div>
