<?php
// =====================================================================
// lab_order_mail.php — the two emails a lab booking sends.
//
//   1. Patient confirmation  → the address on the booking form
//   2. Ops copy              → ECP_LAB_OPS_EMAIL (the team actioning it)
//
// The archive copy to eclinicpro.com@gmail.com is NOT sent from here: every
// ecp_send_mail() call already BCCs MAIL_ARCHIVE_BCC (partials/mailer.php),
// which defaults to that address. Adding it again here would double-send.
//
// WORDING RULE: a submitted booking is a REQUEST, not a confirmed appointment.
// No lab-partner API call and no payment step happens at submit time, so the
// patient email must never say "confirmed". See ecp_lab_status_label().
// =====================================================================

declare(strict_types=1);

require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/lab_orders.php';

/** Who actions new bookings. Also the Reply-To on the patient email. */
const ECP_LAB_OPS_EMAIL = 'wecare@eclinicpro.com';

/** Sender for booking mail — repliable on purpose (this is a real conversation). */
const ECP_LAB_FROM_EMAIL = 'wecare@eclinicpro.com';
const ECP_LAB_FROM_NAME  = 'eClinicPro Lab Team';

/** Shorthand for escaping into the HTML bodies below. */
function ecp_lab_mail_e(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

/**
 * The itemised bill as an HTML table — shared by both emails so the patient
 * and the ops team are always looking at exactly the same numbers.
 */
function ecp_lab_mail_items_table(array $order): string
{
    $rows = '';
    foreach ($order['items'] as $it) {
        $isDiscount = ($it['kind'] ?? '') === 'discount';
        $amount = ecp_lab_inr((int) $it['amount_paise']);
        $label  = ecp_lab_mail_e((string) $it['label']);
        $qty    = (int) ($it['qty'] ?? 1);
        // Only the per-person lines carry a meaningful multiplier.
        if (in_array($it['kind'] ?? '', ['package', 'addon'], true) && $qty > 1) {
            $label .= ' <span style="color:#6e6e73;">× ' . $qty . '</span>';
        }
        $rows .= '<tr>'
            . '<td style="padding:8px 0; border-bottom:1px solid rgba(0,0,0,0.05); font-size:14px;">' . $label . '</td>'
            . '<td style="padding:8px 0; border-bottom:1px solid rgba(0,0,0,0.05); font-size:14px; text-align:right; white-space:nowrap;'
            . ($isDiscount ? ' color:#0F9B6E;' : '') . '">'
            . ($isDiscount ? '− ' : '') . $amount
            . '</td></tr>';
    }

    $total = ecp_lab_inr((int) $order['total_paise']);

    // "You saved ₹X" — coupon savings plus the MRP markdown. Only shown when
    // there is something to show, so a no-discount order doesn't read "₹0".
    $savings = (int) ($order['savings_paise'] ?? 0);
    $savedRow = $savings > 0
        ? '<tr>'
            . '<td style="padding:6px 0 0; font-size:13px; color:#0F9B6E;">You saved</td>'
            . '<td style="padding:6px 0 0; font-size:13px; color:#0F9B6E; text-align:right; white-space:nowrap;">'
            . ecp_lab_inr($savings) . '</td>'
          . '</tr>'
        : '';

    return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:8px 0 4px;">'
        . $rows
        . '<tr>'
        . '<td style="padding:12px 0 0; font-size:15px; font-weight:600;">Total payable</td>'
        . '<td style="padding:12px 0 0; font-size:15px; font-weight:600; text-align:right; white-space:nowrap;">' . $total . '</td>'
        . '</tr>'
        . $savedRow
        . '</table>';
}

/** Beneficiaries as a compact list. */
function ecp_lab_mail_people(array $order): string
{
    $out = [];
    foreach ($order['beneficiaries'] as $b) {
        $bits = ecp_lab_mail_e((string) $b['name']);
        $meta = array_filter([
            !empty($b['age']) ? $b['age'] . ' yrs' : '',
            !empty($b['gender']) ? ecp_lab_mail_e((string) $b['gender']) : '',
        ]);
        if ($meta) {
            $bits .= ' <span style="color:#6e6e73;">(' . implode(', ', $meta) . ')</span>';
        }
        $out[] = '<li style="margin:0 0 4px;">' . $bits . '</li>';
    }
    return '<ul style="margin:6px 0 0; padding-left:18px; font-size:14px; line-height:1.6;">' . implode('', $out) . '</ul>';
}

/** A label/value row block used for the appointment + address details. */
function ecp_lab_mail_rows(array $pairs): string
{
    $html = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:14px; line-height:1.6;">';
    foreach ($pairs as $label => $value) {
        if ($value === null || $value === '') {
            continue;
        }
        $html .= '<tr>'
            . '<td style="padding:4px 12px 4px 0; color:#6e6e73; white-space:nowrap; vertical-align:top;">' . ecp_lab_mail_e($label) . '</td>'
            . '<td style="padding:4px 0; vertical-align:top;">' . nl2br(ecp_lab_mail_e((string) $value)) . '</td>'
            . '</tr>';
    }
    return $html . '</table>';
}

/**
 * Send the patient's "we've got your request" email.
 * Returns ecp_send_mail()'s result — mail failure never breaks the booking.
 */
function ecp_lab_mail_patient(array $order): bool
{
    $ref  = ecp_lab_mail_e($order['order_ref']);
    $name = ecp_lab_mail_e($order['contact_name']);
    $pkg  = ecp_lab_mail_e($order['product_name']);

    $when = ecp_lab_mail_e(date('D, d M Y', strtotime($order['appointment_date']) ?: time()))
          . ' · ' . ecp_lab_mail_e($order['time_slot']);

    $address = trim(
        $order['address'] . "\n"
        . implode(', ', array_filter([$order['city'] ?? '', $order['state'] ?? ''])) . ' '
        . $order['pincode']
    );

    $body = '<p style="margin:0 0 16px; font-size:15px; line-height:1.7;">Hi ' . $name . ',</p>'
        . '<p style="margin:0 0 20px; font-size:15px; line-height:1.7;">'
        . 'Thanks — we\'ve received your booking request for <strong>' . $pkg . '</strong>. '
        . 'Our team will confirm your collection slot and send you a payment link shortly. '
        . 'You do not need to pay anything now.</p>'

        . '<div style="background:#f5f5f7; border-radius:12px; padding:16px 18px; margin:0 0 20px;">'
        . '<div style="font-size:12px; color:#6e6e73; text-transform:uppercase; letter-spacing:0.4px; margin-bottom:4px;">Booking reference</div>'
        . '<div style="font-size:20px; font-weight:600; letter-spacing:0.5px;">' . $ref . '</div>'
        . '<div style="font-size:13px; color:#6e6e73; margin-top:6px;">Status: Request received — awaiting confirmation</div>'
        . '</div>'

        . '<h2 style="margin:24px 0 8px; font-size:16px; font-weight:600;">Appointment</h2>'
        . ecp_lab_mail_rows([
            'Preferred slot' => strip_tags($when),
            'Address'        => $address,
            'Phone'          => $order['phone'] ?? ($order['contact_phone'] ?? ''),
            'Notes'          => $order['notes'] ?? '',
        ])

        . '<h2 style="margin:24px 0 8px; font-size:16px; font-weight:600;">'
        . (count($order['beneficiaries']) > 1 ? 'People' : 'Person') . ' being tested</h2>'
        . ecp_lab_mail_people($order)

        . '<h2 style="margin:24px 0 8px; font-size:16px; font-weight:600;">Order summary</h2>'
        . ecp_lab_mail_items_table($order)
        . '<p style="margin:12px 0 0; font-size:12px; color:#6e6e73; line-height:1.6;">'
        . 'Home collection and courier charges are service fees — discounts and coupons do not apply to them. '
        . 'Payment is due before or at the time of sample collection.</p>'

        . '<h2 style="margin:24px 0 8px; font-size:16px; font-weight:600;">What happens next</h2>'
        . '<ol style="margin:0; padding-left:18px; font-size:14px; line-height:1.8;">'
        . '<li>We confirm your slot and send a payment link.</li>'
        . '<li>A trained phlebotomist collects your sample at home.</li>'
        . '<li>Your digital report is emailed and saved to your eClinicPro account.</li>'
        . '</ol>'
        . '<p style="margin:20px 0 0; font-size:13px; color:#6e6e73; line-height:1.7;">'
        . 'Please allow the technician about 30 minutes either side of your slot for traffic. '
        . 'The date and time may change if a technician is not available at your requested time — '
        . 'we\'ll always call you first.</p>'
        . '<p style="margin:16px 0 0; font-size:14px; line-height:1.7;">'
        . 'Need to change or cancel? Just reply to this email and quote <strong>' . $ref . '</strong>.</p>';

    return ecp_send_mail(
        (string) $order['email'],
        'Lab booking request received — ' . $order['order_ref'],
        ecp_email_template('We\'ve received your booking request', $body),
        (string) $order['contact_name'],
        ECP_LAB_OPS_EMAIL,          // Reply-To: replies reach the team, not a void
        ECP_LAB_FROM_EMAIL,
        ECP_LAB_FROM_NAME
    );
}

/**
 * Send the ops copy — the actionable version, with everything needed to place
 * the order with the lab partner and call the patient back.
 */
function ecp_lab_mail_ops(array $order): bool
{
    $ref = ecp_lab_mail_e($order['order_ref']);

    $address = trim(
        $order['address'] . "\n"
        . implode(', ', array_filter([$order['city'] ?? '', $order['state'] ?? ''])) . ' '
        . $order['pincode']
    );

    $phone = (string) ($order['phone'] ?? ($order['contact_phone'] ?? ''));

    $body = '<p style="margin:0 0 16px; font-size:15px; line-height:1.7;">'
        . 'New lab booking request — <strong>' . $ref . '</strong>. Needs slot confirmation and a payment link.</p>'

        . '<h2 style="margin:20px 0 8px; font-size:16px; font-weight:600;">Patient</h2>'
        . ecp_lab_mail_rows([
            'Name'    => $order['contact_name'],
            'Phone'   => $phone,
            'Email'   => $order['email'],
            'Address' => $address,
        ])
        // Tapping the number on a phone is how ops actually calls back.
        . '<p style="margin:8px 0 0; font-size:14px;">'
        . '<a href="tel:+91' . ecp_lab_mail_e($phone) . '" style="color:#0F9B6E; text-decoration:none;">Call +91 ' . ecp_lab_mail_e($phone) . '</a></p>'

        . '<h2 style="margin:24px 0 8px; font-size:16px; font-weight:600;">Requested slot</h2>'
        . ecp_lab_mail_rows([
            'Date'    => date('D, d M Y', strtotime($order['appointment_date']) ?: time()),
            'Time'    => $order['time_slot'],
            'Persons' => (string) $order['persons'],
            'Pincode' => $order['pincode'],
            'Notes'   => $order['notes'] ?? '',
            'Hard copy' => !empty($order['hard_copy']) ? 'Yes — courier ₹' . ECP_LAB_HARDCOPY_FEE : 'No',
        ])

        . '<h2 style="margin:24px 0 8px; font-size:16px; font-weight:600;">Beneficiaries</h2>'
        . ecp_lab_mail_people($order)

        . '<h2 style="margin:24px 0 8px; font-size:16px; font-weight:600;">Order</h2>'
        . ecp_lab_mail_rows([
            'Package' => $order['product_name'],
            'Coupon'  => $order['coupon_code'] ? $order['coupon_code'] . ' (' . $order['coupon_pct'] . '%)' : 'None',
        ])
        . ecp_lab_mail_items_table($order);

    return ecp_send_mail(
        ECP_LAB_OPS_EMAIL,
        '[Lab booking] ' . $order['order_ref'] . ' — ' . $order['product_name'] . ' · ' . $order['pincode'],
        ecp_email_template('New lab booking request', $body),
        'eClinicPro Lab Team',
        (string) $order['email'],   // Reply-To the patient: ops can reply directly
        ECP_LAB_FROM_EMAIL,
        ECP_LAB_FROM_NAME
    );
}
