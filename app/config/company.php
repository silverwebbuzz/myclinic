<?php

declare(strict_types=1);

/**
 * Legal entity that issues SaaS subscription invoices (the eClinicPro vendor).
 * This is the SELLER on every plan-purchase invoice — distinct from a clinic's
 * own patient invoices. Used by SaasInvoicePdfService.
 */
return [
    'legal_name' => 'SILVER WEBBUZZ PRIVATE LIMITED',
    'brand' => 'eClinicPro',
    'address_lines' => [
        '1109, Satyamev Eminence, Near Shukan Mall',
        'Science City Rd, Sola',
        'Ahmedabad, Gujarat 380060, India',
    ],
    'gstin' => '24ABHCS6317H1ZB',
    // GST place of supply state code (24 = Gujarat) — drives CGST+SGST vs IGST
    // if tax is ever itemized. Plans are currently priced tax-inclusive.
    'state_code' => '24',
    'support_email' => 'wecare@eclinicpro.com',
    'website' => 'https://eclinicpro.com',
];
