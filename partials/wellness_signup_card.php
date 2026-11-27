<?php
// =====================================================================
// partials/wellness_signup_card.php — free-account card for logged-out
// visitors on Wellness Hub pages (plan §8A). Opens the site's OTP modal;
// the modal records signup_source = "wellness_cta" for new accounts.
// Styles live in assets/css/health-tips.css (.wh-signup).
// =====================================================================
?>
<aside class="wh-signup" aria-label="Free eClinicPro account">
  <div>
    <h2>Free eClinicPro account</h2>
    <ul>
      <li>♥ Save recipes, exercises, camps and events</li>
      <li>📞 See organiser numbers and joining links for health camps</li>
      <li>📁 Keep prescriptions, lab reports and family health records in one place</li>
    </ul>
  </div>
  <button type="button" class="btn btn-primary" onclick="window.ecpAuth ? window.ecpAuth.require('wellness_cta', () => location.reload()) : (location.href = '/patient')">Sign up free</button>
</aside>
