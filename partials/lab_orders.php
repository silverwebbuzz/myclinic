<?php
// =====================================================================
// lab_orders.php — lab booking orders: validate, price, persist, read back.
//
// The booking form on lab-detail.php used to be a client-side no-op. This is
// the server side of it. Everything money-related is RECOMPUTED here from the
// database — the browser's totals are stored only as a diagnostic
// (client_total_paise) and are never billed from.
//
//   ecp_lab_price_order()    — authoritative pricing (mirrors updateTotals())
//   ecp_lab_order_create()   — validate + price + INSERT (transactional)
//   ecp_lab_orders_for()     — list for the patient panel
//   ecp_lab_order_find()     — one order + items + beneficiaries (ownership-checked)
//
// Amounts are handled in PAISE end to end; ecp_lab_rupees() formats for display.
// =====================================================================

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/lab_catalog.php';

/** Max beneficiaries per order — matches the form's `max="10"` on persons. */
const ECP_LAB_MAX_PERSONS = 10;

// ECP_LAB_HARDCOPY_FEE / ECP_LAB_COLLECTION_* come from lab_catalog.php, so the
// form, the browser totals and this repricing all read the same numbers.

// The bookable date window (never same-day, 19:00 IST cut-off, 7-day span)
// comes from ecp_lab_booking_window() in lab_catalog.php, so the date picker
// and this validation always agree.

/** Rupees (int) from paise. Every amount is whole rupees today. */
function ecp_lab_rupees(int $paise): string
{
    return number_format((int) round($paise / 100));
}

/** "₹1,299" for emails and the panel. */
function ecp_lab_inr(int $paise): string
{
    return '₹' . ecp_lab_rupees($paise);
}

/**
 * A short, human-quotable order reference (ECPL-XXXXXXX).
 *
 * Crockford-ish alphabet: no I/O/0/1, so a patient reading it over the phone
 * to support can't turn it into a different valid ref.
 */
function ecp_lab_order_ref(): string
{
    $alphabet = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
    $out = '';
    for ($i = 0; $i < 7; $i++) {
        $out .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    }
    return 'ECPL-' . $out;
}

/**
 * Authoritative order pricing.
 *
 * This is the server-side mirror of updateTotals() in lab-detail.php. The rules
 * it encodes are business rules, documented in partials/lab_catalog.php:
 *
 *   - package and add-on lines both scale with the number of persons
 *   - the coupon is applied PER LINE, each line capped by its OWN
 *     max_discount_pct (see ecp_lab_line_discount_pct). A 25% coupon on a
 *     25%-capped package plus a 5%-capped add-on gives 25% and 5% respectively
 *   - the free-collection threshold is tested on the POST-discount order value
 *     (ECP_LAB_COLLECTION_ON_DISCOUNTED), because that's what Thyrocare bills on
 *   - fees (collection, courier) are NEVER discounted and are added after the
 *     coupon is subtracted
 *
 * If you change one side, change the other — a mismatch shows up as a price
 * that jumps between the summary panel and the confirmation email.
 *
 * @param array $pkg     package row from ecp_lab_db_find_package()
 * @param array $addons  [['id'=>slug,'label'=>…,'price'=>int,'max_pct'=>int], …] re-priced from DB
 * @param int   $persons 1..ECP_LAB_MAX_PERSONS
 * @param int   $couponPct  the coupon's headline %, BEFORE any per-product cap
 * @param bool  $hardCopy
 *
 * @return array{package_paise:int,addons_paise:int,discount_paise:int,collection_paise:int,courier_paise:int,total_paise:int,effective_discount_bp:int,savings_paise:int,items:array}
 */
function ecp_lab_price_order(array $pkg, array $addons, int $persons, int $couponPct, bool $hardCopy): array
{
    $persons = max(1, min(ECP_LAB_MAX_PERSONS, $persons));
    $couponPct = max(0, min(100, $couponPct));

    // Discount for one line, rounded to whole RUPEES.
    //
    // Rupees (not paise) because the browser's lineDiscount() does Math.round()
    // on a rupee figure. Flooring in paise instead would leave a sub-rupee
    // remainder and make the emailed total differ from the summary panel by ₹1
    // on any percentage that doesn't divide evenly — e.g. 25% of ₹325, or 15%
    // of ₹3747. See the price-parity harness.
    $lineOff = static function (int $linePaise, int $maxPct) use ($couponPct): int {
        $pct = ecp_lab_line_discount_pct($couponPct, $maxPct);
        return $pct <= 0 ? 0 : ((int) round($linePaise * $pct / 100 / 100)) * 100;
    };

    $unitPaise = (int) round(((float) $pkg['price']) * 100);
    $packagePaise = $unitPaise * $persons;

    // The package's own ceiling. ecp_lab_db_find_package() already exposes it
    // as login_pct = LEAST(best coupon, max_discount_pct), so it is a true cap.
    $pkgMaxPct = (int) ($pkg['login_pct'] ?? 0);
    $pkgOff = $lineOff($packagePaise, $pkgMaxPct);
    $discountPaise = $pkgOff;

    // Each add-on is discounted by ITS OWN cap, not the package's.
    $addonsPaise = 0;
    $addonLines = [];
    foreach ($addons as $a) {
        $unit = (int) round(((float) $a['price']) * 100);
        $line = $unit * $persons;
        $off  = $lineOff($line, (int) ($a['max_pct'] ?? 0));

        $addonsPaise   += $line;
        $discountPaise += $off;
        $addonLines[] = ['a' => $a, 'unit' => $unit, 'line' => $line, 'off' => $off];
    }

    // Free-collection threshold, tested after the discount (confirmed rule).
    // The courier fee is excluded: it's a service charge, not order value.
    $orderValue = $packagePaise + $addonsPaise;
    if (ECP_LAB_COLLECTION_ON_DISCOUNTED) {
        $orderValue -= $discountPaise;
    }
    $minPaise = ECP_LAB_COLLECTION_MIN_ORDER * 100;
    $collectionPaise = ($minPaise > 0 && $orderValue < $minPaise)
        ? ECP_LAB_COLLECTION_FEE * 100
        : 0;

    $courierPaise = $hardCopy ? ECP_LAB_HARDCOPY_FEE * 100 : 0;

    // Fees added AFTER the discount — they are always paid in full.
    $totalPaise = max(0, $packagePaise + $addonsPaise - $discountPaise + $collectionPaise + $courierPaise);

    // ── Derived reporting figures (stored on the order) ─────────────────
    // Blended rate across the whole order, in basis points (1833 = 18.33%).
    // Fees are excluded from the base: they are never discountable, so
    // including them would understate the rate we actually gave away.
    $discountBase = $packagePaise + $addonsPaise;
    $effectiveDiscountBp = $discountBase > 0
        ? (int) round($discountPaise * 10000 / $discountBase)
        : 0;

    // "You saved ₹X" = coupon savings + the MRP markdown already baked into
    // offer_rate. Only the package carries an MRP; add-ons are quoted at
    // offer_rate alone, so they contribute their coupon savings only.
    $mrpUnitPaise = (int) round(((float) ($pkg['mrp'] ?? 0)) * 100);
    $mrpMarkdownPaise = max(0, ($mrpUnitPaise - $unitPaise)) * $persons;
    $savingsPaise = $mrpMarkdownPaise + $discountPaise;

    // Itemised bill, stored verbatim so the email and the panel agree forever.
    $items = [];
    $sort = 0;
    $items[] = [
        'kind' => 'package', 'product_id' => (int) ($pkg['id'] ?? 0) ?: null,
        'label' => (string) $pkg['title'], 'unit_paise' => $unitPaise,
        'qty' => $persons, 'amount_paise' => $packagePaise, 'sort_order' => $sort++,
    ];
    foreach ($addonLines as $l) {
        $items[] = [
            'kind' => 'addon', 'product_id' => $l['a']['product_id'] ?? null,
            'label' => (string) $l['a']['label'], 'unit_paise' => $l['unit'],
            'qty' => $persons, 'amount_paise' => $l['line'], 'sort_order' => $sort++,
        ];
    }
    if ($discountPaise > 0) {
        // Label the discount by the rates actually granted, not the coupon's
        // headline %. When lines have different ceilings, claiming a single
        // flat "25%" against a total that mixes 25% and 5% would not add up on
        // the patient's own bill.
        $grantedPcts = [];
        if ($pkgOff > 0) {
            $grantedPcts[] = ecp_lab_line_discount_pct($couponPct, $pkgMaxPct);
        }
        foreach ($addonLines as $l) {
            if ($l['off'] > 0) {
                $grantedPcts[] = ecp_lab_line_discount_pct($couponPct, (int) ($l['a']['max_pct'] ?? 0));
            }
        }
        $grantedPcts = array_values(array_unique($grantedPcts));
        sort($grantedPcts);

        $label = count($grantedPcts) === 1
            ? 'Member discount (' . $grantedPcts[0] . '%)'
            : 'Member discount (up to ' . max($grantedPcts) . '%)';

        $items[] = [
            'kind' => 'discount', 'product_id' => null,
            'label' => $label, 'unit_paise' => $discountPaise,
            'qty' => 1, 'amount_paise' => $discountPaise, 'sort_order' => $sort++,
        ];
    }
    if ($collectionPaise > 0) {
        $items[] = [
            'kind' => 'collection', 'product_id' => null,
            'label' => 'Home collection charges', 'unit_paise' => $collectionPaise,
            'qty' => 1, 'amount_paise' => $collectionPaise, 'sort_order' => $sort++,
        ];
    }
    if ($courierPaise > 0) {
        $items[] = [
            'kind' => 'courier', 'product_id' => null,
            'label' => 'Hard copy courier', 'unit_paise' => $courierPaise,
            'qty' => 1, 'amount_paise' => $courierPaise, 'sort_order' => $sort++,
        ];
    }

    return [
        'package_paise'    => $packagePaise,
        'addons_paise'     => $addonsPaise,
        'discount_paise'   => $discountPaise,
        'collection_paise' => $collectionPaise,
        'courier_paise'    => $courierPaise,
        'total_paise'      => $totalPaise,
        'effective_discount_bp' => $effectiveDiscountBp,
        'savings_paise'    => $savingsPaise,
        'items'            => $items,
    ];
}

/**
 * Re-price the submitted add-on slugs from the database.
 *
 * The browser posts slugs only. Prices come from lab_products/lab_product_pricing
 * so a tampered form cannot introduce a ₹1 add-on, and unknown or inactive
 * slugs are silently dropped rather than trusted.
 *
 * @param string[] $slugs
 * @return array<int,array{id:string,label:string,price:int,product_id:int}>
 */
function ecp_lab_price_addons(PDO $db, array $slugs): array
{
    $slugs = array_values(array_unique(array_filter(array_map('strval', $slugs), static fn ($s) => $s !== '')));
    if (!$slugs) {
        return [];
    }
    $slugs = array_slice($slugs, 0, 30);

    $ph = implode(',', array_fill(0, count($slugs), '?'));
    $stmt = $db->prepare(
        "SELECT lp.id, lp.slug, lp.name, pr.offer_rate, pr.max_discount_pct
           FROM lab_products lp
           JOIN lab_product_pricing pr ON pr.id = (
               SELECT p2.id FROM lab_product_pricing p2
               WHERE p2.product_id = lp.id
               ORDER BY p2.effective_from DESC, p2.id DESC LIMIT 1
           )
          WHERE lp.is_active = 1
            AND lp.product_type = 'TEST'
            AND pr.offer_rate > 0
            AND lp.slug IN ($ph)"
    );
    $stmt->execute($slugs);

    $out = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
        $out[] = [
            'id'         => (string) $r['slug'],
            'label'      => ecp_lab_titlecase((string) $r['name']),
            'price'      => (int) round((float) $r['offer_rate']),
            // The line's own discount ceiling — never the parent package's.
            'max_pct'    => (int) ($r['max_discount_pct'] ?? 0),
            'product_id' => (int) $r['id'],
        ];
    }
    return $out;
}

/**
 * Validate + price + persist a booking request.
 *
 * @param int   $identityId logged-in patient_identities.id (booking requires login)
 * @param array $in         raw request body
 *
 * @return array{ok:bool,error?:string,message?:string,order?:array}
 */
function ecp_lab_order_create(int $identityId, array $in): array
{
    $db = ecp_db();
    if (!$db) {
        return ['ok' => false, 'error' => 'server_error', 'message' => 'Database unavailable.'];
    }

    $fail = static fn (string $msg, string $code = 'invalid'): array
        => ['ok' => false, 'error' => $code, 'message' => $msg];

    // ── Product ─────────────────────────────────────────────────────────
    $slug = trim((string) ($in['product_slug'] ?? ''));
    if ($slug === '') {
        return $fail('Missing package.');
    }
    $pkg = ecp_lab_db_find_package($slug);
    if (!$pkg || (float) $pkg['price'] <= 0) {
        return $fail('This package is no longer available. Please pick another.', 'product_unavailable');
    }

    // ── Contact + logistics ─────────────────────────────────────────────
    $email = trim((string) ($in['email'] ?? ''));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return $fail('Enter a valid email address.');
    }
    // Mirrors normalisePhone() in lab-detail.php: strip a +91/91 country code
    // or a leading 0, keep the 10 national digits. Only strips when what's left
    // is still plausible, so a real number starting "91…" survives untouched.
    $phone = preg_replace('/\D/', '', (string) ($in['phone'] ?? '')) ?? '';
    if (strlen($phone) === 12 && str_starts_with($phone, '91')) {
        $phone = substr($phone, 2);
    } elseif (strlen($phone) === 11 && str_starts_with($phone, '0')) {
        $phone = substr($phone, 1);
    }
    if (!preg_match('/^[6-9][0-9]{9}$/', $phone)) {
        return $fail('Enter a valid 10-digit mobile number (without +91).');
    }

    $pincode = preg_replace('/\D/', '', (string) ($in['pincode'] ?? '')) ?? '';
    if (!preg_match('/^[1-9][0-9]{5}$/', $pincode)) {
        return $fail('Enter a valid 6-digit pincode.');
    }

    $address = trim((string) ($in['address'] ?? ''));
    if (mb_strlen($address) < 10) {
        return $fail('Enter a complete address — incomplete addresses are rejected by the lab.');
    }

    // ── Beneficiaries ───────────────────────────────────────────────────
    $names   = (array) ($in['beneficiary_name'] ?? []);
    $ages    = (array) ($in['beneficiary_age'] ?? []);
    $genders = (array) ($in['beneficiary_gender'] ?? []);

    $beneficiaries = [];
    foreach ($names as $i => $n) {
        $n = trim((string) $n);
        if ($n === '') {
            continue;
        }
        $age = (int) ($ages[$i] ?? 0);
        if ($age < 1 || $age > 120) {
            return $fail('Enter a valid age (1–120) for ' . $n . '.');
        }
        $g = trim((string) ($genders[$i] ?? ''));
        if (!in_array($g, ['Male', 'Female', 'Other'], true)) {
            return $fail('Select a gender for ' . $n . '.');
        }
        $beneficiaries[] = ['name' => $n, 'age' => $age, 'gender' => $g, 'position' => count($beneficiaries) + 1];
        if (count($beneficiaries) >= ECP_LAB_MAX_PERSONS) {
            break;
        }
    }
    if (!$beneficiaries) {
        return $fail('Enter the full name of at least one person.');
    }

    // Persons is DERIVED from the beneficiary rows, never taken from the posted
    // field: billing for more people than were named would be a real overcharge.
    $persons = count($beneficiaries);

    // ── Appointment slot ────────────────────────────────────────────────
    $dateRaw = trim((string) ($in['appointment_date'] ?? ''));
    $d = DateTimeImmutable::createFromFormat('!Y-m-d', $dateRaw);
    if (!$d || $d->format('Y-m-d') !== $dateRaw) {
        return $fail('Select a valid appointment date.');
    }
    // Collection window: never same-day, and after the 19:00 IST cut-off
    // tomorrow closes too. Enforced here (not just in the date picker) because
    // a stale tab left open past 19:00 would otherwise post a slot the lab can
    // no longer staff.
    [$minDate, $maxDate] = ecp_lab_booking_window();
    if ($dateRaw < $minDate) {
        return $fail(
            $minDate === (new DateTimeImmutable('tomorrow', new DateTimeZone('Asia/Kolkata')))->format('Y-m-d')
                ? 'Home collection starts from tomorrow. Please pick a later date.'
                : 'Bookings after ' . ECP_LAB_CUTOFF_HOUR_IST . ':00 IST start from '
                  . date('D, d M Y', strtotime($minDate) ?: time()) . '. Please pick a later date.'
        );
    }
    if ($dateRaw > $maxDate) {
        return $fail('Please choose a date within the next ' . ECP_LAB_BOOKING_WINDOW_DAYS . ' days (up to '
            . date('D, d M Y', strtotime($maxDate) ?: time()) . ').');
    }

    $slot = trim((string) ($in['time_slot'] ?? ''));
    if ($slot === '' || mb_strlen($slot) > 60) {
        return $fail('Select a time slot.');
    }

    // ── Add-ons + coupon (both re-derived server-side) ───────────────────
    $addons = ecp_lab_price_addons($db, (array) ($in['addons'] ?? []));

    // The coupon is NOT taken from the request — a posted code only decides
    // whether the patient opted in at all. We re-derive the coupon's HEADLINE
    // rate here and let ecp_lab_price_order() cap each line by that product's
    // own max_discount_pct. Passing an already-capped figure would silently
    // limit the whole order to whatever ceiling the viewed package happens to
    // carry.
    $wantsCoupon = trim((string) ($in['coupon'] ?? '')) !== '';
    $couponPct = 0;
    if ($wantsCoupon) {
        // 100 = "no product ceiling", i.e. the coupon's own headline %.
        $couponPct = ecp_lab_best_login_discount($db, 100);
    }
    // The code shown on the page is derived from the package's capped rate, so
    // keep naming it that way for support lookups.
    $pkgRate = ecp_lab_line_discount_pct($couponPct, (int) ($pkg['login_pct'] ?? 0));
    $couponCode = $couponPct > 0 ? 'SAVE' . $pkgRate : null;

    $hardCopy = !empty($in['hard_copy']);

    $price = ecp_lab_price_order($pkg, $addons, $persons, $couponPct, $hardCopy);

    // The browser's figure, kept for diagnostics only. A persistent mismatch
    // means the JS and ecp_lab_price_order() have drifted apart.
    $clientTotal = isset($in['client_total'])
        ? (int) round(((float) $in['client_total']) * 100)
        : null;
    if ($clientTotal !== null && $clientTotal !== $price['total_paise']) {
        error_log(sprintf(
            '[lab_orders] total mismatch slug=%s client=%d server=%d',
            $slug, $clientTotal, $price['total_paise']
        ));
    }

    $contactName = trim((string) ($in['contact_name'] ?? '')) ?: $beneficiaries[0]['name'];
    $city   = trim((string) ($in['city'] ?? '')) ?: null;
    $state  = trim((string) ($in['state'] ?? '')) ?: null;
    $notes  = trim((string) ($in['notes'] ?? '')) ?: null;

    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $ipBin = $ip !== '' ? (@inet_pton($ip) ?: null) : null;

    // ── Persist ─────────────────────────────────────────────────────────
    $db->beginTransaction();
    try {
        // Retry on the (vanishingly unlikely) ref collision rather than 500.
        $orderId = 0;
        $ref = '';
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $ref = ecp_lab_order_ref();
            try {
                $stmt = $db->prepare(
                    "INSERT INTO lab_orders
                        (order_ref, patient_identity_id, product_id, product_type, product_slug, product_name,
                         contact_name, contact_email, contact_phone, address, pincode, city, state,
                         persons, appointment_date, time_slot, notes, hard_copy,
                         package_paise, addons_paise, discount_paise, collection_paise, courier_paise,
                         total_paise, client_total_paise, coupon_code, coupon_pct,
                         effective_discount_pct, savings_paise, source, created_ip)
                     VALUES
                        (:ref, :identity, :product_id, 'package', :slug, :pname,
                         :cname, :cemail, :cphone, :address, :pincode, :city, :state,
                         :persons, :adate, :slot, :notes, :hard,
                         :pkg_p, :add_p, :disc_p, :coll_p, :cour_p,
                         :total_p, :client_p, :coupon, :coupon_pct,
                         :eff_bp, :savings_p, :source, :ip)"
                );
                $stmt->execute([
                    'ref'        => $ref,
                    'identity'   => $identityId,
                    'product_id' => (int) ($pkg['id'] ?? 0) ?: null,
                    'slug'       => $slug,
                    'pname'      => (string) $pkg['title'],
                    'cname'      => $contactName,
                    'cemail'     => $email,
                    'cphone'     => $phone,
                    'address'    => $address,
                    'pincode'    => $pincode,
                    'city'       => $city,
                    'state'      => $state,
                    'persons'    => $persons,
                    'adate'      => $d->format('Y-m-d'),
                    'slot'       => $slot,
                    'notes'      => $notes,
                    'hard'       => $hardCopy ? 1 : 0,
                    'pkg_p'      => $price['package_paise'],
                    'add_p'      => $price['addons_paise'],
                    'disc_p'     => $price['discount_paise'],
                    'coll_p'     => $price['collection_paise'],
                    'cour_p'     => $price['courier_paise'],
                    'total_p'    => $price['total_paise'],
                    'client_p'   => $clientTotal,
                    'coupon'     => $couponCode,
                    // The rate actually GRANTED on the package line, so this
                    // agrees with the SAVEnn code stored beside it. Add-on
                    // lines may have been discounted at their own lower rates —
                    // lab_order_items is the per-line record.
                    'coupon_pct' => $pkgRate,
                    'eff_bp'     => $price['effective_discount_bp'],
                    'savings_p'  => $price['savings_paise'],
                    'source'     => (string) ($in['source'] ?? 'web'),
                    'ip'         => $ipBin,
                ]);
                $orderId = (int) $db->lastInsertId();
                break;
            } catch (PDOException $e) {
                // 23000 + uq_lo_ref = ref collision; anything else is real.
                if ($e->getCode() !== '23000' || !str_contains($e->getMessage(), 'uq_lo_ref')) {
                    throw $e;
                }
            }
        }
        if ($orderId <= 0) {
            throw new RuntimeException('could not allocate a unique order_ref');
        }

        $itemStmt = $db->prepare(
            "INSERT INTO lab_order_items
                (order_id, kind, product_id, label, unit_paise, qty, amount_paise, sort_order)
             VALUES (:oid, :kind, :pid, :label, :unit, :qty, :amount, :sort)"
        );
        foreach ($price['items'] as $it) {
            $itemStmt->execute([
                'oid'    => $orderId,
                'kind'   => $it['kind'],
                'pid'    => $it['product_id'],
                'label'  => $it['label'],
                'unit'   => $it['unit_paise'],
                'qty'    => $it['qty'],
                'amount' => $it['amount_paise'],
                'sort'   => $it['sort_order'],
            ]);
        }

        $benStmt = $db->prepare(
            "INSERT INTO lab_order_beneficiaries (order_id, name, age, gender, position)
             VALUES (:oid, :name, :age, :gender, :pos)"
        );
        foreach ($beneficiaries as $b) {
            $benStmt->execute([
                'oid'    => $orderId,
                'name'   => $b['name'],
                'age'    => $b['age'],
                'gender' => $b['gender'],
                'pos'    => $b['position'],
            ]);
        }

        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        error_log('[lab_orders] create failed: ' . $e->getMessage());
        return ['ok' => false, 'error' => 'server_error', 'message' => 'Could not save your booking. Please try again.'];
    }

    return [
        'ok'    => true,
        'order' => [
            'id'             => $orderId,
            'order_ref'      => $ref,
            'product_name'   => (string) $pkg['title'],
            'product_slug'   => $slug,
            'contact_name'   => $contactName,
            'email'          => $email,
            'phone'          => $phone,
            'address'        => $address,
            'pincode'        => $pincode,
            'city'           => $city,
            'state'          => $state,
            'persons'        => $persons,
            'appointment_date' => $d->format('Y-m-d'),
            'time_slot'      => $slot,
            'notes'          => $notes,
            'hard_copy'      => $hardCopy,
            'coupon_code'    => $couponCode,
            'coupon_pct'     => $pkgRate,
            'beneficiaries'  => $beneficiaries,
            'items'          => $price['items'],
            'total_paise'    => $price['total_paise'],
            'package_paise'  => $price['package_paise'],
            'addons_paise'   => $price['addons_paise'],
            'discount_paise' => $price['discount_paise'],
            'collection_paise' => $price['collection_paise'],
            'courier_paise'  => $price['courier_paise'],
            'savings_paise'  => $price['savings_paise'],
            'effective_discount_bp' => $price['effective_discount_bp'],
            'status'         => 'pending',
        ],
    ];
}

/**
 * Orders for the patient panel list, newest first.
 *
 * @return array<int,array<string,mixed>>
 */
function ecp_lab_orders_for(int $identityId, int $limit = 100): array
{
    $db = ecp_db();
    if (!$db) {
        return [];
    }
    try {
        $stmt = $db->prepare(
            "SELECT id, order_ref, product_name, product_slug, persons,
                    appointment_date, time_slot, status, payment_status,
                    total_paise, pincode, city, state, created_at
               FROM lab_orders
              WHERE patient_identity_id = :id
              ORDER BY created_at DESC
              LIMIT :lim"
        );
        $stmt->bindValue(':id', $identityId, PDO::PARAM_INT);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();

        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
            $out[] = ecp_lab_order_shape($r);
        }
        return $out;
    } catch (Throwable $e) {
        error_log('[ecp_lab_orders_for] ' . $e->getMessage());
        return [];
    }
}

/**
 * One order with its bill lines and beneficiaries.
 *
 * Ownership is re-checked in the WHERE clause (no IDOR): a patient can only
 * ever open their own order, whatever id they pass.
 */
function ecp_lab_order_find(int $identityId, int $orderId): ?array
{
    $db = ecp_db();
    if (!$db || $orderId <= 0) {
        return null;
    }
    try {
        $stmt = $db->prepare(
            "SELECT * FROM lab_orders
              WHERE id = :oid AND patient_identity_id = :id
              LIMIT 1"
        );
        $stmt->execute(['oid' => $orderId, 'id' => $identityId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        $order = ecp_lab_order_shape($row);

        // Full record adds the things the list view doesn't need.
        $order['contact_name']  = (string) $row['contact_name'];
        $order['contact_email'] = (string) $row['contact_email'];
        $order['contact_phone'] = (string) $row['contact_phone'];
        $order['address']       = (string) $row['address'];
        $order['notes']         = $row['notes'] !== null ? (string) $row['notes'] : null;
        $order['hard_copy']     = (bool) $row['hard_copy'];
        $order['coupon_code']   = $row['coupon_code'] !== null ? (string) $row['coupon_code'] : null;
        $order['coupon_pct']    = (int) $row['coupon_pct'];
        $order['amounts'] = [
            'package'    => ecp_lab_inr((int) $row['package_paise']),
            'addons'     => ecp_lab_inr((int) $row['addons_paise']),
            'discount'   => ecp_lab_inr((int) $row['discount_paise']),
            'collection' => ecp_lab_inr((int) $row['collection_paise']),
            'courier'    => ecp_lab_inr((int) $row['courier_paise']),
            'total'      => ecp_lab_inr((int) $row['total_paise']),
            'savings'    => ecp_lab_inr((int) $row['savings_paise']),
        ];
        $order['savings_paise'] = (int) $row['savings_paise'];
        // Basis points back to a percentage for display (1833 → 18.3).
        $order['effective_discount_pct'] = round(((int) $row['effective_discount_pct']) / 100, 1);

        $its = $db->prepare(
            "SELECT kind, label, unit_paise, qty, amount_paise
               FROM lab_order_items WHERE order_id = :oid ORDER BY sort_order, id"
        );
        $its->execute(['oid' => $orderId]);
        $order['items'] = array_map(static fn ($i) => [
            'kind'   => (string) $i['kind'],
            'label'  => (string) $i['label'],
            'qty'    => (int) $i['qty'],
            'unit'   => ecp_lab_inr((int) $i['unit_paise']),
            'amount' => ecp_lab_inr((int) $i['amount_paise']),
            // Discounts are stored positive; the display layer shows the sign.
            'negative' => $i['kind'] === 'discount',
        ], $its->fetchAll(PDO::FETCH_ASSOC) ?: []);

        $bens = $db->prepare(
            "SELECT name, age, gender FROM lab_order_beneficiaries
              WHERE order_id = :oid ORDER BY position, id"
        );
        $bens->execute(['oid' => $orderId]);
        $order['beneficiaries'] = array_map(static fn ($b) => [
            'name'   => (string) $b['name'],
            'age'    => $b['age'] !== null ? (int) $b['age'] : null,
            'gender' => $b['gender'] !== null ? (string) $b['gender'] : null,
        ], $bens->fetchAll(PDO::FETCH_ASSOC) ?: []);

        return $order;
    } catch (Throwable $e) {
        error_log('[ecp_lab_order_find] ' . $e->getMessage());
        return null;
    }
}

/** Shared list/detail shaping so both views agree on labels and formats. */
function ecp_lab_order_shape(array $r): array
{
    $status = (string) $r['status'];
    $date = (string) $r['appointment_date'];
    $ts = strtotime($date) ?: null;

    return [
        'id'             => (int) $r['id'],
        'order_ref'      => (string) $r['order_ref'],
        'product_name'   => (string) $r['product_name'],
        'product_slug'   => (string) ($r['product_slug'] ?? ''),
        'persons'        => (int) $r['persons'],
        'appointment_date' => $date,
        'appointment_human' => $ts ? date('D, d M Y', $ts) : $date,
        'time_slot'      => (string) $r['time_slot'],
        'status'         => $status,
        'status_label'   => ecp_lab_status_label($status),
        'payment_status' => (string) $r['payment_status'],
        'total'          => ecp_lab_inr((int) $r['total_paise']),
        'total_paise'    => (int) $r['total_paise'],
        'pincode'        => (string) $r['pincode'],
        'city'           => $r['city'] !== null ? (string) $r['city'] : null,
        'state'          => $r['state'] !== null ? (string) $r['state'] : null,
        'created_at'     => (string) $r['created_at'],
        'created_human'  => date('d M Y', strtotime((string) $r['created_at']) ?: time()),
        // Only a request that hasn't been actioned yet can be cancelled by the
        // patient; once a phlebotomist is dispatched it's a phone call.
        'can_cancel'     => in_array($status, ['pending', 'confirmed'], true),
    ];
}

/** Patient-facing status wording. Deliberately not "Confirmed" while pending. */
function ecp_lab_status_label(string $status): string
{
    return [
        'pending'   => 'Request received',
        'confirmed' => 'Confirmed',
        'collected' => 'Sample collected',
        'reported'  => 'Report ready',
        'cancelled' => 'Cancelled',
    ][$status] ?? ucfirst($status);
}

/**
 * Patient-initiated cancellation.
 * Ownership + state are both enforced in the UPDATE, so a replayed request
 * can't resurrect or re-cancel an order.
 */
function ecp_lab_order_cancel(int $identityId, int $orderId): array
{
    $db = ecp_db();
    if (!$db) {
        return ['ok' => false, 'error' => 'server_error'];
    }
    try {
        $stmt = $db->prepare(
            "UPDATE lab_orders
                SET status = 'cancelled', cancelled_at = NOW()
              WHERE id = :oid AND patient_identity_id = :id
                AND status IN ('pending','confirmed')"
        );
        $stmt->execute(['oid' => $orderId, 'id' => $identityId]);
        if ($stmt->rowCount() === 0) {
            return ['ok' => false, 'error' => 'not_cancellable',
                    'message' => 'This booking can no longer be cancelled online. Please call us.'];
        }
        return ['ok' => true];
    } catch (Throwable $e) {
        error_log('[ecp_lab_order_cancel] ' . $e->getMessage());
        return ['ok' => false, 'error' => 'server_error'];
    }
}
