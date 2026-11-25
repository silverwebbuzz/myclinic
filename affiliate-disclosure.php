<?php
// =====================================================================
// affiliate-disclosure.php — eClinicPro Affiliate Disclosure
// Brand of Silver Webbuzz Pvt Ltd. Explains affiliate links to Ayurvedic
// and wellness products, commissions, third-party purchases and the
// health-information disclaimer. Written with the Consumer Protection
// Act 2019, ASCI digital-advertising guidelines, AYUSH/FSSAI claim rules
// and the DPDP Act 2023 in mind.
// =====================================================================
require_once __DIR__ . '/partials/helpers.php';

$pageTitle  = 'Affiliate Disclosure — eClinicPro';
$metaDesc   = 'eClinicPro affiliate disclosure: how we earn commissions on Ayurvedic and wellness product links, at no extra cost to you, and how purchases work.';
$activePage = 'legal';
$lastUpdated = 'November 25, 2026';

require __DIR__ . '/partials/header.php';
?>

<section class="legal-hero">
    <div class="wrap-narrow">
        <span class="eyebrow">Legal</span>
        <h1 class="h-display">Affiliate Disclosure</h1>
        <p class="legal-meta">Last updated: <?= e($lastUpdated) ?></p>
        <p class="lede">
            eClinicPro is operated by <strong>Silver Webbuzz Pvt Ltd</strong>. Some pages on
            eclinicpro.com recommend Ayurvedic, herbal, skincare and wellness products and contain
            affiliate links. This page explains what that means for you. It forms part of our
            <a href="/terms">Terms of Service</a>.
        </p>
    </div>
</section>

<section class="legal-body">
    <div class="wrap-narrow legal-prose">

        <div class="legal-callout">
            <strong>The short version.</strong> If you click a product link on eClinicPro and buy
            something on the brand's or seller's website, we may earn a commission. You don't pay
            anything extra. You buy directly from that seller, who handles the price, delivery,
            payment and returns. Our product content is general information, not medical advice.
        </div>

        <h2>1. What is an affiliate disclosure?</h2>
        <p>
            An affiliate disclosure is a clear statement that a website may earn money when its
            visitors click certain links and buy products. We publish this page so you know about
            our commercial relationships with brands and sellers before you decide to buy.
        </p>

        <h2>2. How eClinicPro earns affiliate commissions</h2>
        <p>
            We publish information about Ayurvedic, herbal, healthcare, skincare and wellness
            products. Some product listings include special tracking links, called affiliate links.
        </p>
        <ul>
            <li>When you click an affiliate link, you leave eclinicpro.com and go to the brand's or seller's website.</li>
            <li>If you buy something there, the seller or affiliate network may pay us a commission.</li>
            <li>The commission is usually a percentage of the sale value or a fixed fee. It is paid only for qualifying purchases, under the terms of each programme.</li>
            <li>We may also earn a commission on other products you buy during the same visit to that website.</li>
        </ul>
        <p>
            Under this model, eClinicPro does <strong>not</strong> manufacture, stock, package,
            ship or directly sell affiliate products.
        </p>

        <h2>3. Affiliate links and third-party websites</h2>
        <p>
            Affiliate links take you to websites that we do not own or control. These may include
            brand websites, online marketplaces and other online sellers.
        </p>
        <ul>
            <li>Each third-party website has its own terms of sale, privacy policy and cookie policy.</li>
            <li>We are not responsible for the content, security or practices of those websites.</li>
            <li>Please read the seller's terms before you buy.</li>
        </ul>
        <p>
            Where practical, we label affiliate links or show a short notice near them, such as
            "Affiliate link" or "We may earn a commission", so the relationship is clear at the
            point where you make your choice.
        </p>

        <h2>4. No additional cost to you</h2>
        <p>
            You do not pay anything extra because you used our affiliate link. The seller pays our
            commission from its own revenue. The price you pay is set by the seller and is the same
            price you would see if you visited that website directly at the same time.
        </p>
        <p>
            Sellers may run their own offers, coupons or member prices. These are controlled by the
            seller, and we cannot promise that any offer will apply to you.
        </p>

        <h2>5. Our recommendations and editorial independence</h2>
        <p>
            Affiliate commissions do not change our duty to give you honest and transparent
            information. We aim to describe products fairly, based on information available to us,
            and to avoid exaggerated or misleading statements.
        </p>
        <p>To be fully open with you:</p>
        <ul>
            <li>We may choose to feature products from brands and sellers with whom we have affiliate arrangements.</li>
            <li>We do not cover every product available in the market, so our listings are not a complete comparison.</li>
            <li>The order in which products appear may depend on relevance, popularity, availability and, in some cases, our commercial arrangements.</li>
            <li>A product appearing on eClinicPro is not always a personal endorsement or a statement that it is right for you.</li>
            <li>We have <strong>not</strong> independently tested or medically verified every product we list.</li>
        </ul>

        <h2>6. Product prices and availability</h2>
        <p>
            Prices, discounts, stock and delivery areas change often and are set by each seller.
            Any price or availability shown on eClinicPro is for general reference only and may be
            out of date. The price and terms shown on the seller's website when you place your
            order are the ones that apply.
        </p>

        <h2>7. Purchases, payments, shipping and returns</h2>
        <p>
            When you buy through an affiliate link, your contract of sale is with the seller, not
            with eClinicPro or Silver Webbuzz Pvt Ltd. The seller is responsible for:
        </p>
        <ul>
            <li>accepting and processing your order and payment;</li>
            <li>invoices and applicable taxes;</li>
            <li>packaging, shipping and delivery;</li>
            <li>cancellations, returns, replacements and refunds;</li>
            <li>product warranties, quality and safety; and</li>
            <li>customer support for your order.</li>
        </ul>
        <p>
            For any problem with an order, please contact the seller directly using the details on
            their website or your order confirmation. If you need help identifying the seller, you
            can write to us (see <a href="#contact">Contact</a>), and we will try to help where we
            reasonably can.
        </p>
        <p>
            Affiliate purchases are not covered by our <a href="/refund-policy">Refund &amp;
            Cancellation Policy</a>, which applies only to the eClinicPro software subscription.
        </p>

        <h2>8. Health, Ayurvedic and wellness information disclaimer</h2>
        <p>
            Product content on eClinicPro is general information for awareness only. It is not
            intended to diagnose, treat, cure or prevent any disease.
        </p>
        <ul>
            <li>Ayurvedic, herbal and wellness products can affect people differently. Results are not guaranteed.</li>
            <li>Some products may not be suitable during pregnancy or breastfeeding, for children, or for people with certain health conditions or allergies.</li>
            <li>Herbal products and supplements can interact with prescription or other medicines.</li>
            <li>Always read the label, ingredients, dosage directions and warnings provided by the manufacturer.</li>
            <li>Do not stop or change any prescribed treatment because of information you read on our website.</li>
        </ul>
        <p>
            Before using any Ayurvedic, herbal or wellness product, especially for a health
            condition, please talk to a qualified doctor or registered Ayurvedic practitioner. In a
            medical emergency, contact your nearest hospital or emergency service at once.
        </p>

        <h2>9. No medical advice or doctor endorsement</h2>
        <p>
            Nothing in our product content is medical advice, a prescription or a substitute for a
            consultation with a qualified healthcare professional.
        </p>
        <p>
            eClinicPro also provides clinic software and a directory of doctors and clinics. A
            product appearing on our website does <strong>not</strong> mean that any doctor, clinic
            or healthcare professional listed on eClinicPro has reviewed, recommended or endorsed
            it, unless that is clearly and specifically stated with their consent.
        </p>
        <p>
            eClinicPro is not certified, approved or endorsed by any government authority or
            medical body in connection with its product recommendations.
        </p>

        <h2>10. Accuracy of product descriptions and manufacturer information</h2>
        <p>
            Product names, images, ingredients, descriptions and claims are usually based on
            information published by the manufacturer, brand or seller. We try to keep this
            information accurate and up to date, but we cannot guarantee that it is complete,
            current or free from errors.
        </p>
        <ul>
            <li>Manufacturers may change formulations, packaging or labels without notice.</li>
            <li>Any claim about a product is the responsibility of its manufacturer or seller.</li>
            <li>The label on the product you receive is the most reliable source of product information.</li>
        </ul>
        <p>
            If you spot an error or a claim that seems misleading, please tell us (see
            <a href="#contact">Contact</a>) and we will review it.
        </p>

        <h2>11. Affiliate programmes we may participate in</h2>
        <p>
            eClinicPro may participate in affiliate programmes run by affiliate networks, online
            marketplaces and brands directly. These may include, for example:
        </p>
        <ul>
            <li>affiliate networks such as Cuelinks and Admitad;</li>
            <li>the Amazon Associates programme in India; and</li>
            <li>direct partnerships with Ayurvedic and wellness brands.</li>
        </ul>
        <p>
            We may recommend products from established Indian brands such as Dabur, Himalaya
            Wellness, Kapiva, Baidyanath, Patanjali, Organic India, Zandu, Kama Ayurveda and other
            Ayurvedic and wellness brands. Mentioning a brand does not mean that the brand sponsors,
            endorses or is otherwise associated with eClinicPro beyond any affiliate arrangement
            that may apply.
        </p>
        <p>
            <strong>Amazon Associates:</strong> eClinicPro is a participant in the Amazon Associates
            programme. As an Amazon Associate, we earn from qualifying purchases.
        </p>
        <p>
            All brand names, product names, logos and trademarks belong to their respective owners
            and are used only to identify the products.
        </p>

        <h2>12. Advertising and sponsored content</h2>
        <p>
            Besides affiliate links, eClinicPro may sometimes publish paid advertising or content
            created in partnership with a brand. When a brand has paid us, given us free products
            or otherwise has a material connection with content we publish, we will label it
            clearly, for example as "Ad", "Sponsored" or "In partnership with [brand]".
        </p>
        <p>
            We place these labels where you can easily see them, close to the content itself, and
            not only on this page. Sponsored content must still meet the standards in this
            disclosure, including the health-information rules in Sections 8 to 10.
        </p>

        <h2>13. Third-party privacy and cookie policies</h2>
        <p>
            When you click an affiliate link, the affiliate network or seller may place cookies or
            similar tracking technology on your device. This lets them record that you came from
            eClinicPro so that any commission can be credited to us.
        </p>
        <ul>
            <li>Personal data you share with a seller, such as your name, address and payment details, is collected and processed by that seller under its own privacy policy.</li>
            <li>We generally receive only limited, aggregated reports from affiliate networks, such as the number of clicks and sales. We do not receive your payment details.</li>
            <li>You can control or delete cookies through your browser settings. Blocking cookies may affect how some websites work.</li>
        </ul>
        <p>
            For how eClinicPro handles your personal data, and your rights under the Digital
            Personal Data Protection Act, 2023 and other applicable laws, please read our
            <a href="/privacy-policy">Privacy Policy</a>.
        </p>

        <h2>14. Applicable laws</h2>
        <p>
            We aim to follow the Indian laws and guidelines that apply to our affiliate and
            advertising activities, including, where applicable:
        </p>
        <ul>
            <li>the Consumer Protection Act, 2019 and the Consumer Protection (E-Commerce) Rules, 2020;</li>
            <li>guidelines on misleading advertisements and endorsements issued under consumer protection law;</li>
            <li>the Advertising Standards Council of India (ASCI) Code and its guidelines for influencer advertising in digital media;</li>
            <li>laws and rules on the advertising of Ayurvedic, Siddha, Unani and Homoeopathy (AYUSH) products;</li>
            <li>Food Safety and Standards Authority of India (FSSAI) requirements on advertising and claims for foods and health supplements;</li>
            <li>the Information Technology Act, 2000 and its rules; and</li>
            <li>the Digital Personal Data Protection Act, 2023 and its rules, to the extent in force.</li>
        </ul>
        <p>
            This page is a transparency statement. It does not limit any rights you have as a
            consumer under Indian law.
        </p>

        <h2>15. Changes to this disclosure</h2>
        <p>
            We may update this disclosure when our affiliate partners, business practices or legal
            requirements change. Updates are posted on this page with a new "Last updated" date.
        </p>

        <h2 id="contact">16. Contact</h2>
        <p>
            If you have a question about our affiliate links, a product listing or this disclosure,
            please contact us. For complaints, you can also reach our Grievance Officer as described
            in our <a href="/privacy-policy#grievance">Privacy Policy</a>, or use our
            <a href="/contact">Contact page</a>.
        </p>
        <div class="legal-contact">
            <p><strong>Silver Webbuzz Pvt Ltd</strong> (operating eClinicPro)</p>
            <p>Website: <a href="https://eclinicpro.com">https://eclinicpro.com</a></p>
            <p>Affiliate questions &amp; grievances: <a href="mailto:hello@eclinicpro.com">hello@eclinicpro.com</a></p>
            <p>Registered office: Ahmedabad, Gujarat, India</p>
        </div>
    </div>
</section>

<?php require __DIR__ . '/partials/footer.php'; ?>
