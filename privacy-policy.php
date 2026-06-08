<?php
// =====================================================================
// privacy-policy.php — eClinicPro Privacy Policy
// Brand of Silver Webbuzz Pvt Ltd. Aligned to India DPDP Act 2023,
// IT Act 2000 / SPDI Rules 2011, and IT Rules 2021 (grievance officer).
// eClinicPro is a DATA PROCESSOR for clinics (who are the data
// fiduciaries of their patients) and a controller for the public
// directory + its own account/billing data.
// =====================================================================
require_once __DIR__ . '/partials/helpers.php';

$pageTitle  = 'Privacy Policy — eClinicPro';
$metaDesc   = 'How eClinicPro (a brand of Silver Webbuzz Pvt Ltd) collects, uses, stores and protects personal and health data — DPDP, GDPR and HIPAA aligned.';
$activePage = 'legal';
$lastUpdated = 'June 8, 2026';

require __DIR__ . '/partials/header.php';
?>

<section class="legal-hero">
    <div class="wrap-narrow">
        <span class="eyebrow">Legal</span>
        <h1 class="h-display">Privacy Policy</h1>
        <p class="legal-meta">Last updated: <?= e($lastUpdated) ?></p>
        <p class="lede">
            eClinicPro is a brand operated by <strong>Silver Webbuzz Pvt Ltd</strong> ("eClinicPro",
            "we", "us", "our"). This policy explains what information we collect through
            <a href="https://eclinicpro.com">eclinicpro.com</a> and the eClinicPro clinic platform
            (the "Services"), how we use it, and the choices and rights you have.
        </p>
    </div>
</section>

<section class="legal-body">
    <div class="wrap-narrow legal-prose">

        <div class="legal-callout">
            <strong>Two roles, briefly.</strong> When a clinic uses eClinicPro to manage its
            patients, <em>the clinic</em> is the data fiduciary/controller of those patient records
            and eClinicPro acts as its <strong>data processor</strong> — we process patient data only
            on the clinic's instructions. For our public doctor directory, your account and our
            billing data, eClinicPro is the controller. This policy covers both.
        </div>

        <h2>1. Scope &amp; consent</h2>
        <p>
            By creating an account, browsing the directory, booking an appointment, or otherwise
            using the Services, you agree to this Privacy Policy and, where required, provide your
            consent to the processing described here. If you do not agree, please do not use the
            Services. Sensitive personal data (including health information) is collected and
            processed only with the relevant consent or a lawful basis.
        </p>

        <h2>2. Information we collect</h2>
        <h3>2.1 Information you provide</h3>
        <ul>
            <li><strong>Account &amp; clinic data:</strong> name, clinic name, email, phone, password (stored only as a hash), specialty, registration/qualification details, and clinic address.</li>
            <li><strong>Billing data:</strong> billing name, GSTIN, and payment confirmations. Card/UPI details are handled by our payment gateway (Razorpay) and are not stored on our servers.</li>
            <li><strong>Patient &amp; health data (entered by clinics):</strong> patient demographics, contact details, visit notes, vitals, diagnoses, prescriptions, lab/test results, invoices and uploaded documents. This is entered and controlled by the clinic.</li>
            <li><strong>Directory &amp; booking data:</strong> doctor profile details and, when a patient books, the patient's name, phone and reason for visit.</li>
            <li><strong>Communications:</strong> support requests, demo enquiries and feedback you send us.</li>
        </ul>
        <h3>2.2 Information collected automatically</h3>
        <ul>
            <li><strong>Technical data:</strong> IP address, device and browser type, and pages viewed, used for security, diagnostics and analytics.</li>
            <li><strong>Cookies:</strong> see Section 8.</li>
        </ul>
        <p>
            We do not knowingly collect more than is necessary, and we exclude information that is
            publicly available or that you are not required to provide.
        </p>

        <h2>3. How we use information</h2>
        <ul>
            <li>To provide, operate, secure and improve the Services;</li>
            <li>To let clinics manage patients, appointments, prescriptions and billing;</li>
            <li>To power the public directory and appointment booking;</li>
            <li>To process subscriptions, send invoices and apply GST;</li>
            <li>To send service, security and (with consent) product communications;</li>
            <li>To detect, prevent and investigate fraud, abuse and security incidents;</li>
            <li>To comply with legal, tax and healthcare record-keeping obligations.</li>
        </ul>
        <p>
            We do <strong>not</strong> sell personal data, and we do <strong>not</strong> use patient
            data to train AI models — ours or anyone else's.
        </p>

        <h2>4. How we share information</h2>
        <p>We share personal data only as needed and with appropriate safeguards:</p>
        <ul>
            <li><strong>With the clinic:</strong> patient data is accessible to the authorised staff of the clinic that created it, based on their role-based permissions.</li>
            <li><strong>With sub-processors:</strong> vetted vendors who help us run the Services (hosting, messaging via Meta WhatsApp / MSG91, email, and payments via Razorpay), under contract and only on a need-to-know basis.</li>
            <li><strong>For legal reasons:</strong> where required by law, court order, or a valid request from a competent authority, or to protect rights, safety and the integrity of the Services.</li>
            <li><strong>Business transfers:</strong> in connection with a merger, acquisition or reorganisation, subject to this policy.</li>
        </ul>

        <h2>5. Data storage, residency &amp; security</h2>
        <p>
            You choose your data residency region at signup (India, EU, US, UAE or Singapore) and
            your data — including backups and analytics — stays in that region. We protect data with
            encryption in transit (TLS) and at rest (AES-256), role-based access controls,
            audit logging, and regular security testing. No system is perfectly secure, and we cannot
            guarantee absolute security against hacking, phishing or unauthorised access beyond our
            reasonable control. Please keep your password confidential and log out of shared devices.
        </p>

        <h2>6. Data retention</h2>
        <p>
            We retain personal data only for as long as necessary for the purposes above, or as
            required by applicable law (including medical record-retention rules). When no longer
            needed, data is deleted or anonymised securely. Clinics control their patient data and
            can export or delete it; on account closure we erase production data within 30 days and
            from backups within the backup-rotation window, except where law requires retention.
        </p>

        <h2>7. Your rights</h2>
        <p>
            Subject to applicable law (including the Digital Personal Data Protection Act, 2023 in
            India, and GDPR where it applies), you may:
        </p>
        <ul>
            <li>access, correct, or update your personal data;</li>
            <li>request deletion / erasure of your personal data;</li>
            <li>withdraw consent (this won't affect prior lawful processing);</li>
            <li>request a portable copy of your data;</li>
            <li>nominate, where DPDP allows, another person to exercise your rights; and</li>
            <li>raise a grievance with our Grievance Officer (Section 11).</li>
        </ul>
        <p>
            If you are a <em>patient</em> whose data sits with a clinic, please contact that clinic
            (the fiduciary) first; we will assist the clinic in honouring your request.
            To exercise rights over data we control, email
            <a href="mailto:hello@eclinicpro.com">hello@eclinicpro.com</a> with the subject
            "Data request" and your registered email/phone. We respond within 30 days.
        </p>

        <h2>8. Cookies</h2>
        <p>
            We use cookies and similar technologies to keep you signed in, remember preferences,
            keep the Services secure, and understand usage. You can control cookies through your
            browser settings; disabling some cookies may affect functionality. We do not control
            third-party cookies that those third parties may set.
        </p>

        <h2>9. Children</h2>
        <p>
            The Services are intended for clinics and adults. We do not knowingly create accounts for
            minors. Children's health records may be stored <em>by a clinic</em> as part of treatment,
            under the clinic's responsibility and the consent of a parent or guardian.
        </p>

        <h2>10. Changes to this policy</h2>
        <p>
            We may update this policy from time to time. Material changes will be posted here with a
            new "Last updated" date. Your continued use of the Services after changes take effect
            means you accept the updated policy.
        </p>

        <h2 id="grievance">11. Grievance Officer &amp; contact</h2>
        <p>
            In accordance with the Information Technology Act, 2000, the IT (Intermediary Guidelines
            and Digital Media Ethics Code) Rules, 2021, and the DPDP Act, 2023, the contact details of
            our Grievance Officer are:
        </p>
        <div class="legal-contact">
            <p><strong>Grievance Officer — eClinicPro (Silver Webbuzz Pvt Ltd)</strong></p>
            <p>Email (privacy, grievances &amp; support): <a href="mailto:hello@eclinicpro.com">hello@eclinicpro.com</a></p>
            <p>Registered office: Ahmedabad, Gujarat, India</p>
        </div>
        <p class="legal-fine">
            We acknowledge grievances within 24 hours and aim to resolve them within 15 days as
            required under applicable Indian law.
        </p>

        <p class="legal-fine">
            See also our <a href="/terms">Terms of Service</a> and
            <a href="/refund-policy">Refund &amp; Cancellation Policy</a>.
        </p>
    </div>
</section>

<?php require __DIR__ . '/partials/footer.php'; ?>
