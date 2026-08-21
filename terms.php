<?php
// =====================================================================
// terms.php — eClinicPro Terms of Service
// Brand of Silver Webbuzz Pvt Ltd. Covers the SaaS subscription for
// clinics, the public directory + booking layer, medical disclaimer,
// liability, and India governing law (Ahmedabad jurisdiction).
// =====================================================================
require_once __DIR__ . '/partials/helpers.php';

$pageTitle  = 'Terms of Service — eClinicPro';
$metaDesc   = 'The terms governing your use of eClinicPro (a brand of Silver Webbuzz Pvt Ltd) — subscription, acceptable use, medical disclaimer, liability and governing law.';
$activePage = 'legal';
$lastUpdated = 'June 8, 2026';

require __DIR__ . '/partials/header.php';
?>

<section class="legal-hero">
    <div class="wrap-narrow">
        <span class="eyebrow">Legal</span>
        <h1 class="h-display">Terms of Service</h1>
        <p class="legal-meta">Last updated: <?= e($lastUpdated) ?></p>
        <p class="lede">
            These Terms are a binding agreement between you and <strong>Silver Webbuzz Pvt Ltd</strong>,
            which operates the eClinicPro brand and platform ("eClinicPro", "we", "us"). By accessing
            or using <a href="https://eclinicpro.com">eclinicpro.com</a>, the clinic platform, the
            doctor directory, or booking a doctor (the "Services"), you agree to these Terms.
        </p>
    </div>
</section>

<section class="legal-body">
    <div class="wrap-narrow legal-prose">

        <h2>1. Acceptance</h2>
        <p>
            By using the Services you confirm you have read, understood and accept these Terms, our
            <a href="/privacy-policy">Privacy Policy</a> and our
            <a href="/refund-policy">Refund &amp; Cancellation Policy</a>, which are incorporated by
            reference. This document is an electronic record under the Information Technology Act, 2000.
        </p>

        <h2>2. Eligibility</h2>
        <p>
            You must be capable of forming a legally binding contract under the Indian Contract Act,
            1872. Clinic accounts must be opened by a registered medical practitioner or an authorised
            representative of a clinic. Minors may not register; a minor's records may only be stored
            by a clinic with parent/guardian consent.
        </p>

        <h2>3. The Services</h2>
        <p>eClinicPro provides, on a subscription basis:</p>
        <ul>
            <li>a clinic operating system — patient records, appointments and walk-in queue, prescriptions, vitals and diagnoses, billing &amp; GST invoicing, reports and analytics;</li>
            <li>specialty-aware clinical forms, teleconsultation, and optional WhatsApp/SMS messaging;</li>
            <li>a public doctor directory on eclinicpro.com and an online appointment-booking layer connecting patients with clinics.</li>
        </ul>
        <p>
            We may add, modify or discontinue features at our discretion. We are a technology provider:
            <strong>we do not practise medicine</strong> and are not a party to the
            clinician–patient relationship.
        </p>

        <h2>4. Accounts &amp; security</h2>
        <p>
            You are responsible for the accuracy of the information you provide, for keeping your
            login credentials confidential, and for all activity under your account. Notify us
            promptly of any unauthorised use. You are responsible for assigning staff roles and
            permissions appropriately within your clinic.
        </p>

        <h2>5. Clinic responsibilities &amp; data</h2>
        <ul>
            <li>The clinic is the data fiduciary/controller of its patient data; eClinicPro processes it on the clinic's instructions (see the <a href="/privacy-policy">Privacy Policy</a>).</li>
            <li>The clinic is responsible for the accuracy, legality and consent basis of the patient data it enters, and for obtaining patient consent where required (including for messaging and teleconsultation).</li>
            <li>The clinic must comply with all applicable medical, data-protection and professional-conduct laws, and is responsible for clinical decisions and the content of prescriptions and records.</li>
            <li>You retain ownership of your data and can export it at any time.</li>
        </ul>

        <h2>6. Acceptable use</h2>
        <p>You agree not to:</p>
        <ul>
            <li>upload false, misleading or unlawful information, or infringe anyone's rights;</li>
            <li>access the Services to build a competing product or to benchmark for a competitor;</li>
            <li>reverse engineer, decompile, scrape, or attempt to gain unauthorised access;</li>
            <li>transmit malware, or disrupt or overload the Services;</li>
            <li>use the Services for any illegal, fraudulent or harmful purpose.</li>
        </ul>
        <p>We may suspend or terminate access for violations of this section.</p>

        <h2>7. Subscription, fees &amp; taxes</h2>
        <p>
            eClinicPro is offered as a single annual plan (currently ₹16,000/year), plus optional
            add-ons, as described on the <a href="/clinic-management-software#pricing">pricing section</a>. New clinics
            start with a 30-day free trial; no card is required to begin the trial. Fees are exclusive
            of taxes — GST (currently 18%) is added at checkout. Payments are processed by our payment
            gateway. Refunds and cancellations are governed by our
            <a href="/refund-policy">Refund &amp; Cancellation Policy</a>.
        </p>

        <h2>8. Intellectual property</h2>
        <p>
            The Services, including software, design, text, graphics and logos, are owned by Silver
            Webbuzz Pvt Ltd or its licensors and are protected by law. We grant you a limited,
            non-exclusive, non-transferable, revocable right to use the Services for your clinic during
            your subscription. You may not copy, resell, sublicense or commercially exploit the
            Services except as expressly permitted.
        </p>

        <h2>9. Directory &amp; booking</h2>
        <p>
            Doctor listings in the directory are provided for discovery. Appointment booking is a
            facilitation between a patient and a clinic; eClinicPro is not responsible for a clinic's
            availability, fees, conduct, advice or the outcome of any consultation. Fees and
            cancellation rules for a consultation are set by the clinic.
        </p>

        <h2>10. Medical disclaimer</h2>
        <div class="legal-callout">
            <p>
                eClinicPro is software for managing a clinic and for connecting patients with
                clinicians. Any content, assessment or tool in the Services is for informational and
                workflow purposes only and is <strong>not medical advice</strong> and not a substitute
                for professional diagnosis or treatment. The treating clinician is solely responsible
                for clinical decisions. In an emergency, contact local emergency services.
            </p>
        </div>

        <h2>11. Third-party services</h2>
        <p>
            The Services integrate third parties (e.g., payment, messaging and hosting providers).
            Your use of those is also subject to their terms; we are not responsible for their acts or
            omissions beyond our reasonable control.
        </p>

        <h2>12. Warranties &amp; disclaimers</h2>
        <p>
            The Services are provided on an "as is" and "as available" basis. To the maximum extent
            permitted by law, we disclaim all implied warranties, including merchantability, fitness
            for a particular purpose, and non-infringement. We do not warrant that the Services will be
            uninterrupted or error-free, and we are not liable for downtime or delay caused by events
            beyond our reasonable control (including cyber-attacks, acts of God, government action,
            epidemics/pandemics, or denial-of-service attacks).
        </p>

        <h2>13. Limitation of liability</h2>
        <p>
            To the maximum extent permitted by law, eClinicPro and Silver Webbuzz Pvt Ltd will not be
            liable for any indirect, incidental, special, punitive or consequential damages, or for
            loss of profits, business, goodwill or data. Our total aggregate liability arising out of
            or relating to the Services will not exceed the fees you paid to us for the Services in the
            12 months preceding the event giving rise to the claim.
        </p>

        <h2>14. Indemnity</h2>
        <p>
            You agree to indemnify and hold harmless eClinicPro, Silver Webbuzz Pvt Ltd and its
            officers, employees and partners from claims, losses and expenses (including reasonable
            legal fees) arising from your use of the Services, the data you enter, your violation of
            these Terms, or your violation of any law or third-party right. This survives termination.
        </p>

        <h2>15. Term &amp; termination</h2>
        <p>
            You may cancel as described in the <a href="/refund-policy">Refund &amp; Cancellation
            Policy</a>. We may suspend or terminate access for breach of these Terms, non-payment, or
            unlawful/abusive activity. On termination you may export your data for a reasonable period,
            after which it is deleted per our Privacy Policy and applicable law.
        </p>

        <h2>16. Governing law &amp; dispute resolution</h2>
        <p>
            These Terms are governed by the laws of India. Subject to applicable law, the courts at
            <strong>Ahmedabad, Gujarat, India</strong> will have exclusive jurisdiction over any
            dispute arising out of or relating to the Services or these Terms.
        </p>

        <h2>17. Changes to these Terms</h2>
        <p>
            We may update these Terms from time to time. Material changes will be posted here with a
            new "Last updated" date; continued use after they take effect means you accept them.
        </p>

        <h2 id="contact">18. Contact</h2>
        <div class="legal-contact">
            <p><strong>Silver Webbuzz Pvt Ltd</strong> (operating eClinicPro)</p>
            <p>Support &amp; grievances: <a href="mailto:hello@eclinicpro.com">hello@eclinicpro.com</a></p>
            <p>Registered office: Ahmedabad, Gujarat, India</p>
        </div>
    </div>
</section>

<?php require __DIR__ . '/partials/footer.php'; ?>
