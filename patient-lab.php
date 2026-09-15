<?php
// =====================================================================
// patient-lab.php — full-width lab booking (partner portal embed).
//
// Why this is its own page rather than a tab inside patient.php:
// the partner's booking portal uses a wide desktop layout (hero left,
// booking form right) that needs roughly the full browser window. Inside
// the patient panel it only ever got ~1240px — the panel sits in a 1280px
// .wrap minus padding — and the booking form was clipped at that width.
// Here the frame spans the viewport, which is the condition it renders
// correctly in.
//
// Booking happens entirely inside the partner's session: nothing here
// writes to lab_orders. See the "Lab bookings" tab in patient.php, which
// lists only orders placed through our own (now legacy) storefront.
// =====================================================================
require_once __DIR__ . '/partials/helpers.php';
require_once __DIR__ . '/partials/patient_auth.php';

// Private page — never cache; cookie/session state must be fresh.
header('Cache-Control: private, no-store, max-age=0');
header('Vary: Cookie');

$me = ecp_patient_current();   // null when logged out

// Booking is a signed-in feature: bounce to the panel, which shows the
// inline OTP form when logged out.
if (!$me) {
    header('Location: /patient', true, 302);
    exit;
}

$pageTitle  = 'Book a lab test — eClinicPro';
$metaDesc   = 'Book diagnostic lab tests with home sample collection.';
$activePage = '';
$noindex    = true;                  // private, signed-in only
$hideFinalCta = true;                // no marketing CTA under a booking flow

// Partner booking portal. Kept in one place so the pageId (which identifies
// our account to the lab) is not duplicated across files.
$labBookUrl = 'https://booking.thyrocare.com/landing-page?pageId=52bfad4a63ca45569c449d2571789471178618152e8b59bbd8476b98df109713';

require __DIR__ . '/partials/header.php';
?>

<div class="plab-page">

  <!-- Slim bar: identity + a way back. Deliberately short — every pixel
       here is one the booking frame does not get. -->
  <div class="plab-bar">
    <a href="/patient" class="plab-back" aria-label="Back to My Health">
      <span aria-hidden="true">‹</span> My Health
    </a>
    <h1 class="plab-title">Book a lab test</h1>
    <span class="plab-note">Home collection · Reports in 24–48 hrs</span>
  </div>

  <!-- Full-bleed frame. No card, no wrap, no side padding: the partner
       page needs the whole window to lay out correctly. -->
  <div class="plab-frame-wrap">
    <div class="plab-skeleton" id="plab-skeleton">
      <div class="plab-spinner" aria-hidden="true"></div>
      <p>Loading the booking portal…</p>
    </div>
    <iframe
      class="plab-frame"
      src="<?= e($labBookUrl) ?>"
      title="Book a lab test"
      referrerpolicy="no-referrer-when-downgrade"
      allow="payment; clipboard-write; geolocation"
      onload="this.classList.add('is-ready'); var s=document.getElementById('plab-skeleton'); if(s) s.hidden=true;"></iframe>
  </div>
</div>

<style>
  /* The site header is fixed; this page starts below it and then gives
     everything else to the frame. */
  .plab-page {
    background: var(--bg-3, #fafafa);
    padding-top: 80px;
  }

  .plab-bar {
    display: flex;
    align-items: center;
    gap: 16px;
    flex-wrap: wrap;
    padding: 14px 20px;
    background: #fff;
    border-bottom: 1px solid var(--line);
  }

  .plab-back {
    font-size: 14px;
    font-weight: 600;
    color: var(--teal-700);
    white-space: nowrap;
  }

  .plab-back:hover {
    text-decoration: underline;
  }

  .plab-title {
    font-size: 16px;
    font-weight: 700;
    letter-spacing: -0.2px;
    margin: 0;
  }

  .plab-note {
    font-size: 12.5px;
    color: var(--mute);
    margin-left: auto;
  }

  /* Viewport-height frame: subtract the site header (80px) and this
     page's own bar (~50px) so the portal fills what is left without
     the page itself scrolling. */
  .plab-frame-wrap {
    position: relative;
    width: 100%;
    height: calc(100vh - 130px);
    min-height: 620px;
    background: var(--bg-3, #fafafa);
  }

  .plab-frame {
    width: 100%;
    height: 100%;
    border: 0;
    display: block;
    opacity: 0;
    transition: opacity .25s;
  }

  .plab-frame.is-ready {
    opacity: 1;
  }

  .plab-skeleton {
    position: absolute;
    inset: 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 10px;
    color: var(--mute);
    font-size: 14px;
  }

  .plab-spinner {
    width: 26px;
    height: 26px;
    border: 3px solid var(--teal-100);
    border-top-color: var(--teal-600);
    border-radius: 50%;
    animation: plab-spin .8s linear infinite;
  }

  @keyframes plab-spin {
    to {
      transform: rotate(360deg);
    }
  }

  @media (prefers-reduced-motion: reduce) {
    .plab-spinner {
      animation-duration: 2.4s;
    }
  }

  @media (max-width: 820px) {
    .plab-bar {
      padding: 12px 14px;
      gap: 10px;
    }

    .plab-note {
      display: none;
    }

    /* The partner flow stacks into one long column on a phone; give it
       as much height as we can. */
    .plab-frame-wrap {
      height: calc(100vh - 118px);
      min-height: 560px;
    }
  }
</style>

<?php require __DIR__ . '/partials/footer.php'; ?>
