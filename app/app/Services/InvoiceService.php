<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\QueryBuilder;
use App\Core\RequestContext;

final class InvoiceService
{
    public static function find(int $clinicId, int $id): ?array
    {
        $row = QueryBuilder::table('invoices')
            ->forClinic($clinicId)
            ->where('id', '=', $id)
            ->first();

        return $row ?: null;
    }

    /** @return array<string, mixed>|null */
    public static function findDetailed(int $clinicId, int $id): ?array
    {
        $invoice = self::find($clinicId, $id);
        if ($invoice === null) {
            return null;
        }

        $invoice['items'] = self::items($id);
        $stmt = Database::connection()->prepare(
            'SELECT p.name AS patient_name, p.uhid, p.phone, p.advance_balance
             FROM patients p WHERE p.id = ? AND p.clinic_id = ?',
        );
        $stmt->execute([(int) $invoice['patient_id'], $clinicId]);
        $patient = $stmt->fetch() ?: [];
        $invoice['patient'] = $patient;

        return $invoice;
    }

    /** @return list<array<string, mixed>> */
    public static function items(int $invoiceId): array
    {
        return QueryBuilder::table('invoice_items')
            ->where('invoice_id', '=', $invoiceId)
            ->get();
    }

    /** Latest non-cancelled invoice linked to a visit, if any. */
    public static function findForVisit(int $clinicId, int $visitId): ?array
    {
        $rows = QueryBuilder::table('invoices')
            ->forClinic($clinicId)
            ->where('visit_id', '=', $visitId)
            ->orderBy('id', 'DESC')
            ->get();

        foreach ($rows as $row) {
            if ((string) ($row['status'] ?? '') === 'cancelled') {
                continue;
            }

            return $row;
        }

        return null;
    }

    /** @return list<array{description: string, amount: float}> */
    public static function chargeLinesForVisit(int $clinicId, int $visitId): array
    {
        $invoice = self::findForVisit($clinicId, $visitId);
        if ($invoice === null) {
            return [];
        }

        $lines = [];
        foreach (self::items((int) $invoice['id']) as $item) {
            $desc = trim((string) ($item['description'] ?? ''));
            $qty = max(1, (int) ($item['qty'] ?? 1));
            $unit = (float) ($item['unit_price'] ?? 0);
            $discount = (float) ($item['discount'] ?? 0);
            $amount = round($unit * $qty - $discount, 2);
            if ($desc === '' && $amount <= 0) {
                continue;
            }
            $lines[] = [
                'description' => $desc !== '' ? $desc : 'Charge',
                'amount' => $amount,
            ];
        }

        return $lines;
    }

    /** @param array<string, mixed> $payload */
    public static function createDraftFromVisit(int $clinicId, array $payload): int
    {
        $visitId = (int) ($payload['visit_id'] ?? 0);
        if ($visitId < 1) {
            return 0;
        }

        $existing = self::findForVisit($clinicId, $visitId);
        if ($existing !== null) {
            return (int) $existing['id'];
        }

        $visit = VisitService::findDetailed($clinicId, $visitId);
        if ($visit === null) {
            return 0;
        }

        $config = OnboardingService::specialtyConfig($clinicId) ?? [];
        $fee = ClinicSettingsService::consultationFeeForClinic($clinicId);
        $clinic = QueryBuilder::table('tenants')->where('id', '=', $clinicId)->first();

        $invoiceId = self::create($clinicId, [
            'patient_id' => (int) $visit['patient_id'],
            'visit_id' => $visitId,
            'attributed_doctor_id' => (int) $visit['doctor_id'],
            'currency' => $clinic['currency'] ?? 'INR',
            'tax_label' => $config['invoice_tax_label'] ?? 'GST',
            'tax_percent' => (float) ($config['invoice_tax_percent'] ?? 0),
            'status' => 'draft',
            'items' => $fee > 0 ? [[
                'description' => 'Consultation fee',
                'item_type' => 'consultation',
                'qty' => 1,
                'unit_price' => $fee,
                'discount' => 0,
            ]] : [],
        ]);

        return $invoiceId;
    }

    /** @param array<string, mixed> $data */
    public static function create(int $clinicId, array $data): int
    {
        $config = OnboardingService::specialtyConfig($clinicId) ?? [];
        $prefix = $config['invoice_prefix'] ?? 'INV';

        // Retry on uq_inv_num collisions: two concurrent invoices can compute
        // the same next number; the unique key rejects one, we renumber it.
        $id = 0;
        for ($attempt = 0; $attempt < 3; $attempt++) {
            $number = self::nextInvoiceNumber($clinicId, $prefix);
            try {
                $id = QueryBuilder::table('invoices')->insert([
                    'clinic_id' => $clinicId,
                    'patient_id' => (int) $data['patient_id'],
                    'visit_id' => $data['visit_id'] ?? null,
                    'attributed_doctor_id' => $data['attributed_doctor_id'] ?? null,
                    'invoice_number' => $number,
                    'currency' => $data['currency'] ?? 'INR',
                    'subtotal' => 0,
                    'discount_amount' => 0,
                    'tax_label' => $data['tax_label'] ?? ($config['invoice_tax_label'] ?? 'GST'),
                    'tax_percent' => (float) ($data['tax_percent'] ?? ($config['invoice_tax_percent'] ?? 0)),
                    'tax_amount' => 0,
                    'total' => 0,
                    'status' => $data['status'] ?? 'draft',
                    'notes' => $data['notes'] ?? null,
                ]);
                break;
            } catch (\PDOException $e) {
                if ($attempt < 2 && str_contains($e->getMessage(), 'uq_inv_num')) {
                    continue;
                }
                throw $e;
            }
        }

        foreach ($data['items'] ?? [] as $item) {
            self::addItem($id, $item);
        }

        self::recalculate($clinicId, $id);

        return $id;
    }

    /** @param array<string, mixed> $item */
    public static function addItem(int $invoiceId, array $item): int
    {
        return QueryBuilder::table('invoice_items')->insert([
            'invoice_id' => $invoiceId,
            'description' => $item['description'] ?? 'Line item',
            'item_type' => $item['item_type'] ?? 'other',
            'qty' => max(1, (int) ($item['qty'] ?? 1)),
            'unit_price' => (float) ($item['unit_price'] ?? 0),
            'discount' => (float) ($item['discount'] ?? 0),
        ]);
    }

    /** @param array<string, mixed> $data */
    public static function update(int $clinicId, int $invoiceId, array $data): array
    {
        $invoice = self::find($clinicId, $invoiceId);
        if ($invoice === null) {
            throw new \RuntimeException('Invoice not found');
        }

        if (isset($data['items']) && is_array($data['items'])) {
            QueryBuilder::table('invoice_items')->where('invoice_id', '=', $invoiceId)->delete();
            foreach ($data['items'] as $item) {
                self::addItem($invoiceId, $item);
            }
        }

        $update = [];
        if (array_key_exists('discount_percent', $data)) {
            $subtotal = self::itemsSubtotal($invoiceId);
            $update['discount_amount'] = round($subtotal * ((float) $data['discount_percent'] / 100), 2);
        }
        if (array_key_exists('tax_percent', $data)) {
            $update['tax_percent'] = (float) $data['tax_percent'];
        }
        if (array_key_exists('notes', $data)) {
            $update['notes'] = $data['notes'];
        }
        if (array_key_exists('status', $data)) {
            $update['status'] = $data['status'];
        }

        if ($update !== []) {
            QueryBuilder::table('invoices')
                ->forClinic($clinicId)
                ->where('id', '=', $invoiceId)
                ->update($update);
        }

        self::recalculate($clinicId, $invoiceId);

        return self::findDetailed($clinicId, $invoiceId) ?? [];
    }

    public static function recalculate(int $clinicId, int $invoiceId): void
    {
        $invoice = self::find($clinicId, $invoiceId);
        if ($invoice === null) {
            return;
        }

        $subtotal = self::itemsSubtotal($invoiceId);
        $discount = (float) ($invoice['discount_amount'] ?? 0);
        $taxable = max(0, $subtotal - $discount);
        $taxPercent = (float) ($invoice['tax_percent'] ?? 0);
        $taxAmount = round($taxable * ($taxPercent / 100), 2);
        $total = round($taxable + $taxAmount, 2);

        QueryBuilder::table('invoices')
            ->forClinic($clinicId)
            ->where('id', '=', $invoiceId)
            ->update([
                'subtotal' => $subtotal,
                'tax_amount' => $taxAmount,
                'total' => $total,
            ]);
    }

    private static function itemsSubtotal(int $invoiceId): float
    {
        $items = self::items($invoiceId);
        $sum = 0.0;
        foreach ($items as $item) {
            $sum += (float) ($item['total'] ?? ((int) $item['qty'] * (float) $item['unit_price'] - (float) $item['discount']));
        }

        return round($sum, 2);
    }

    /**
     * Payment block on the visit screen: one call settles the money side of a
     * visit invoice.
     *
     *   amount  — the payable base BEFORE tax (the "Amount (₹)" field). It is
     *             seeded from the charge lines but the doctor may edit it:
     *             lower than the line total is stored as a discount, higher
     *             adds an "Additional charges" line, so the invoice items and
     *             the amount always reconcile.
     *   gst     — add the clinic's tax percent on top (0 when unticked).
     *   type    — cash | online (stored as the invoice payment_mode).
     *   status  — paid | due. "paid" records a payment for the whole balance;
     *             "due" leaves the balance open. Already-recorded payments are
     *             never reversed here — that is a billing-screen action.
     *
     * @param array<string, mixed> $payment
     * @return array<string, mixed> the refreshed invoice
     */
    public static function applyVisitPayment(int $clinicId, int $invoiceId, array $payment): array
    {
        $invoice = self::find($clinicId, $invoiceId);
        if ($invoice === null) {
            throw new \RuntimeException('Invoice not found');
        }

        $config = OnboardingService::specialtyConfig($clinicId) ?? [];
        $gst = !empty($payment['gst']);
        $taxPercent = $gst ? (float) ($config['invoice_tax_percent'] ?? 0) : 0.0;
        if ($gst && $taxPercent <= 0) {
            $taxPercent = 18.0;   // clinic never configured a rate — GST default
        }

        $type = in_array($payment['type'] ?? '', ['cash', 'online'], true) ? $payment['type'] : 'cash';
        $status = ($payment['status'] ?? 'due') === 'paid' ? 'paid' : 'due';

        $subtotal = self::itemsSubtotal($invoiceId);
        $amount = array_key_exists('amount', $payment) && $payment['amount'] !== '' && $payment['amount'] !== null
            ? round(max(0, (float) $payment['amount']), 2)
            : $subtotal;

        $discount = 0.0;
        if ($amount > $subtotal + 0.005) {
            self::addItem($invoiceId, [
                'description' => 'Additional charges',
                'item_type' => 'other',
                'unit_price' => round($amount - $subtotal, 2),
            ]);
        } elseif ($amount < $subtotal - 0.005) {
            $discount = round($subtotal - $amount, 2);
        }

        QueryBuilder::table('invoices')
            ->forClinic($clinicId)
            ->where('id', '=', $invoiceId)
            ->update([
                'discount_amount' => $discount,
                'tax_percent' => $taxPercent,
                'tax_label' => $config['invoice_tax_label'] ?? 'GST',
                'payment_mode' => $type,
            ]);

        self::recalculate($clinicId, $invoiceId);

        $invoice = self::find($clinicId, $invoiceId) ?? [];
        $due = round(
            (float) ($invoice['total'] ?? 0)
            - (float) ($invoice['advance_paid'] ?? 0)
            - (float) ($invoice['amount_paid'] ?? 0),
            2,
        );

        if ($status === 'paid' && $due > 0.005) {
            self::recordPayment($clinicId, $invoiceId, null, $type);
        } elseif ($status === 'due' && (string) ($invoice['status'] ?? '') === 'draft' && $due > 0.005) {
            // Draft reads as "not billed yet"; 'sent' is the open-balance state
            // the billing list filters and chases.
            QueryBuilder::table('invoices')
                ->forClinic($clinicId)
                ->where('id', '=', $invoiceId)
                ->update(['status' => 'sent']);
        }

        return self::findDetailed($clinicId, $invoiceId) ?? [];
    }

    /**
     * Payment state per appointment, for the day list: appointment → visit →
     * invoice. Keyed by appointment id; appointments with no invoice yet are
     * simply absent.
     *
     * @param list<int> $appointmentIds
     * @return array<int, array{invoice_id: int, invoice_number: string, total: float, due: float, status: string, payment_mode: string}>
     */
    public static function forAppointments(int $clinicId, array $appointmentIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $appointmentIds))));
        if ($ids === [] || !Database::ping()) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        try {
            $stmt = Database::connection()->prepare(
                "SELECT v.appointment_id, i.id, i.invoice_number, i.total, i.status, i.payment_mode,
                        COALESCE(i.advance_paid, 0) AS advance_paid, COALESCE(i.amount_paid, 0) AS amount_paid
                   FROM invoices i
                   INNER JOIN visits v ON v.id = i.visit_id
                  WHERE i.clinic_id = ? AND v.appointment_id IN ($placeholders)
                        AND i.status != 'cancelled'
                  ORDER BY i.id ASC",
            );
            $stmt->execute([$clinicId, ...$ids]);
        } catch (\Throwable $e) {
            return [];
        }

        $out = [];
        foreach ($stmt->fetchAll() ?: [] as $row) {
            // ORDER BY id ASC + overwrite = the newest invoice wins.
            $out[(int) $row['appointment_id']] = [
                'invoice_id' => (int) $row['id'],
                'invoice_number' => (string) ($row['invoice_number'] ?? ''),
                'total' => (float) $row['total'],
                'due' => round((float) $row['total'] - (float) $row['advance_paid'] - (float) $row['amount_paid'], 2),
                'status' => (string) $row['status'],
                'payment_mode' => (string) ($row['payment_mode'] ?? ''),
            ];
        }

        return $out;
    }

    /** Amount still owed on an invoice (total − advance − payments). */
    public static function balanceDue(array $invoice): float
    {
        return round(
            (float) ($invoice['total'] ?? 0)
            - (float) ($invoice['advance_paid'] ?? 0)
            - (float) ($invoice['amount_paid'] ?? 0),
            2,
        );
    }

    public static function markPaid(int $clinicId, int $invoiceId, string $method, ?string $gatewayRef = null): array
    {
        return self::recordPayment($clinicId, $invoiceId, null, $method, $gatewayRef);
    }

    /**
     * Record a (possibly partial) payment. $amount null = settle the balance.
     * Status becomes 'partial' until the balance reaches zero, then 'paid'.
     */
    public static function recordPayment(int $clinicId, int $invoiceId, ?float $amount, string $method, ?string $gatewayRef = null): array
    {
        $invoice = self::findDetailed($clinicId, $invoiceId);
        if ($invoice === null) {
            throw new \RuntimeException('Invoice not found');
        }

        $alreadyPaid = (float) ($invoice['amount_paid'] ?? 0);
        $due = round((float) $invoice['total'] - (float) ($invoice['advance_paid'] ?? 0) - $alreadyPaid, 2);
        if ($due <= 0) {
            throw new \RuntimeException('This invoice has no balance due.');
        }

        $amount = $amount === null ? $due : round($amount, 2);
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Payment amount must be positive.');
        }
        if ($amount > $due) {
            throw new \InvalidArgumentException(
                'Payment exceeds the balance due (' . number_format($due, 2) . '). Record an advance instead.',
            );
        }

        $method = in_array($method, ['cash', 'upi', 'card', 'online', 'insurance', 'bank_transfer'], true) ? $method : 'cash';

        $user = RequestContext::user();
        QueryBuilder::table('payments')->insert([
            'clinic_id' => $clinicId,
            'invoice_id' => $invoiceId,
            'amount' => $amount,
            'method' => $method,
            'gateway_ref' => $gatewayRef,
            'recorded_by' => $user['id'] ?? null,
        ]);

        $newPaid = round($alreadyPaid + $amount, 2);
        $settled = $newPaid + (float) ($invoice['advance_paid'] ?? 0) >= (float) $invoice['total'] - 0.005;

        $update = [
            'status' => $settled ? 'paid' : 'partial',
            'payment_mode' => in_array($method, ['cash', 'upi', 'card', 'online', 'insurance'], true) ? $method : 'cash',
        ];
        if ($settled) {
            $update['paid_at'] = date('Y-m-d H:i:s');
        }

        try {
            QueryBuilder::table('invoices')
                ->forClinic($clinicId)
                ->where('id', '=', $invoiceId)
                ->update(array_merge($update, ['amount_paid' => $newPaid]));
        } catch (\Throwable $e) {
            // amount_paid column missing (migration 023 not applied yet) —
            // keep the legacy behaviour: full payments only.
            QueryBuilder::table('invoices')
                ->forClinic($clinicId)
                ->where('id', '=', $invoiceId)
                ->update($update);
        }

        if (!$settled) {
            DashboardService::invalidateStats($clinicId);

            return self::findDetailed($clinicId, $invoiceId) ?? [];
        }

        $invoice = self::findDetailed($clinicId, $invoiceId);
        $patient = $invoice !== null ? PatientService::find($clinicId, (int) $invoice['patient_id']) : null;
        $clinic = QueryBuilder::table('tenants')->where('id', '=', $clinicId)->first();

        if ($invoice !== null && $patient !== null && $clinic !== null) {
            $pdfPath = InvoicePdfService::generate($invoice, $patient, $clinic);
            QueryBuilder::table('invoices')
                ->forClinic($clinicId)
                ->where('id', '=', $invoiceId)
                ->update(['pdf_path' => $pdfPath]);

            $pdfUrl = $pdfPath;
            if ($pdfUrl !== '' && str_starts_with($pdfUrl, '/')) {
                $pdfUrl = rtrim($_ENV['APP_URL'] ?? '', '/') . $pdfUrl;
            }

            $paidPayload = [
                'patient_name' => $patient['name'],
                'clinic_name' => $clinic['name'],
                'invoice_number' => $invoice['invoice_number'],
                'total' => $invoice['total'],
                'pdf_url' => $pdfUrl,
            ];
            $paidAt = date('Y-m-d H:i:s', time() + 60);

            NotificationService::queueWhatsApp(
                $clinicId,
                (int) $patient['id'],
                (string) $patient['phone'],
                'invoice_paid',
                $paidPayload,
                $paidAt,
            );

            // Email the receipt too, when the patient has an address on file.
            NotificationService::queueEmail(
                $clinicId,
                (int) $patient['id'],
                $patient['email'] ?? null,
                'invoice_paid',
                $paidPayload,
                $paidAt,
            );

            EventBus::fire('invoice.paid', [
                'invoice_id' => $invoiceId,
                'patient_id' => (int) $patient['id'],
                'total' => $invoice['total'],
            ], 'invoices', $invoiceId);
        }

        DashboardService::invalidateStats($clinicId);

        return self::findDetailed($clinicId, $invoiceId) ?? [];
    }

    public static function applyAdvance(int $clinicId, int $invoiceId, ?float $amount = null): void
    {
        $invoice = self::find($clinicId, $invoiceId);
        if ($invoice === null) {
            throw new \RuntimeException('Invoice not found');
        }

        $patient = PatientService::find($clinicId, (int) $invoice['patient_id']);
        if ($patient === null) {
            throw new \RuntimeException('Patient not found');
        }

        $balance = (float) ($patient['advance_balance'] ?? 0);
        if ($balance <= 0) {
            return;
        }

        $due = (float) $invoice['total'] - (float) ($invoice['advance_paid'] ?? 0);
        $apply = $amount !== null ? min($amount, $balance, $due) : min($balance, $due);
        if ($apply <= 0) {
            return;
        }

        QueryBuilder::table('invoices')
            ->forClinic($clinicId)
            ->where('id', '=', $invoiceId)
            ->update(['advance_paid' => (float) ($invoice['advance_paid'] ?? 0) + $apply]);

        QueryBuilder::table('patients')
            ->forClinic($clinicId)
            ->where('id', '=', (int) $patient['id'])
            ->update(['advance_balance' => $balance - $apply]);
    }

    public static function recordAdvance(int $clinicId, int $patientId, float $amount, string $method = 'cash'): void
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Amount must be positive');
        }

        $patient = PatientService::find($clinicId, $patientId);
        if ($patient === null) {
            throw new \RuntimeException('Patient not found');
        }

        $newBalance = (float) ($patient['advance_balance'] ?? 0) + $amount;
        QueryBuilder::table('patients')
            ->forClinic($clinicId)
            ->where('id', '=', $patientId)
            ->update(['advance_balance' => $newBalance]);
    }

    /** @return list<array<string, mixed>> */
    public static function list(int $clinicId, array $filters = [], int $limit = 50): array
    {
        if (!Database::ping()) {
            return [];
        }

        $sql = 'SELECT i.*, p.name AS patient_name, p.uhid
                FROM invoices i
                INNER JOIN patients p ON p.id = i.patient_id
                WHERE i.clinic_id = ?';
        $params = [$clinicId];

        if (!empty($filters['status'])) {
            $sql .= ' AND i.status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['patient_id'])) {
            $sql .= ' AND i.patient_id = ?';
            $params[] = (int) $filters['patient_id'];
        }
        if (!empty($filters['q'])) {
            $sql .= ' AND (p.name LIKE ? OR i.invoice_number LIKE ? OR p.uhid LIKE ?)';
            $like = '%' . $filters['q'] . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $sql .= ' ORDER BY i.created_at DESC LIMIT ' . (int) $limit;

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll() ?: [];
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{rows: list<array<string, mixed>>, total: int, page: int, per_page: int}
     */
    public static function listPaginated(int $clinicId, array $filters = [], int $page = 1, int $perPage = 20): array
    {
        if (!Database::ping()) {
            return ['rows' => [], 'total' => 0, 'page' => $page, 'per_page' => $perPage];
        }

        $page = max(1, $page);
        $where = 'i.clinic_id = ?';
        $params = [$clinicId];
        if (($filters['status'] ?? '') === 'due') {
            // "Due" is not a stored status — it is any live invoice that still
            // has money outstanding, which is what reception chases.
            $where .= " AND i.status NOT IN ('paid', 'cancelled', 'refunded')"
                . ' AND (i.total - COALESCE(i.advance_paid, 0) - COALESCE(i.amount_paid, 0)) > 0.005';
        } elseif (!empty($filters['status'])) {
            $where .= ' AND i.status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['q'])) {
            $where .= ' AND (p.name LIKE ? OR i.invoice_number LIKE ? OR p.uhid LIKE ?)';
            $like = '%' . $filters['q'] . '%';
            array_push($params, $like, $like, $like);
        }

        $pdo = Database::connection();
        $countStmt = $pdo->prepare(
            "SELECT COUNT(*) AS c FROM invoices i INNER JOIN patients p ON p.id = i.patient_id WHERE {$where}",
        );
        $countStmt->execute($params);
        $total = (int) ($countStmt->fetch()['c'] ?? 0);

        $offset = ($page - 1) * $perPage;
        $stmt = $pdo->prepare(
            "SELECT i.*, p.name AS patient_name, p.uhid
             FROM invoices i INNER JOIN patients p ON p.id = i.patient_id
             WHERE {$where}
             ORDER BY i.created_at DESC, i.id DESC
             LIMIT {$perPage} OFFSET {$offset}",
        );
        $stmt->execute($params);

        return [
            'rows' => $stmt->fetchAll() ?: [],
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
        ];
    }

    private static function nextInvoiceNumber(int $clinicId, string $prefix): string
    {
        // COUNT(*)+1 repeats numbers as soon as any invoice is deleted —
        // duplicate invoice numbers are a GST-compliance violation. Use the
        // highest existing sequence instead; uq_inv_num + the create() retry
        // loop cover the concurrent case.
        $stmt = Database::connection()->prepare(
            "SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(invoice_number, '-', -1) AS UNSIGNED)), 0) + 1 AS n
             FROM invoices WHERE clinic_id = ?",
        );
        $stmt->execute([$clinicId]);
        $next = (int) ($stmt->fetch()['n'] ?? 1);

        return $prefix . '-' . str_pad((string) $next, 5, '0', STR_PAD_LEFT);
    }
}
