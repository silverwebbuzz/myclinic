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

    /** @param array<string, mixed> $payload */
    public static function createDraftFromVisit(int $clinicId, array $payload): int
    {
        $visitId = (int) ($payload['visit_id'] ?? 0);
        if ($visitId < 1) {
            return 0;
        }

        $existing = QueryBuilder::table('invoices')
            ->forClinic($clinicId)
            ->where('visit_id', '=', $visitId)
            ->where('status', '=', 'draft')
            ->first();

        if ($existing !== null) {
            return (int) $existing['id'];
        }

        $visit = VisitService::findDetailed($clinicId, $visitId);
        if ($visit === null) {
            return 0;
        }

        $config = OnboardingService::specialtyConfig($clinicId) ?? [];
        $fee = (float) ($config['consultation_fee'] ?? 0);
        $clinic = QueryBuilder::table('tenants')->where('id', '=', $clinicId)->first();

        $invoiceId = self::create($clinicId, [
            'patient_id' => (int) $visit['patient_id'],
            'visit_id' => $visitId,
            'attributed_doctor_id' => (int) $visit['doctor_id'],
            'currency' => $clinic['currency'] ?? 'INR',
            'tax_label' => $config['invoice_tax_label'] ?? 'GST',
            'tax_percent' => (float) ($config['invoice_tax_percent'] ?? 0),
            'status' => 'draft',
            'items' => [[
                'description' => 'Consultation fee',
                'item_type' => 'consultation',
                'qty' => 1,
                'unit_price' => $fee > 0 ? $fee : 500.00,
                'discount' => 0,
            ]],
        ]);

        return $invoiceId;
    }

    /** @param array<string, mixed> $data */
    public static function create(int $clinicId, array $data): int
    {
        $config = OnboardingService::specialtyConfig($clinicId) ?? [];
        $prefix = $config['invoice_prefix'] ?? 'INV';
        $number = self::nextInvoiceNumber($clinicId, $prefix);

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

    public static function markPaid(int $clinicId, int $invoiceId, string $method, ?string $gatewayRef = null): array
    {
        $invoice = self::findDetailed($clinicId, $invoiceId);
        if ($invoice === null) {
            throw new \RuntimeException('Invoice not found');
        }

        $amount = (float) $invoice['total'] - (float) ($invoice['advance_paid'] ?? 0);
        if ($amount < 0) {
            $amount = 0;
        }

        $user = RequestContext::user();
        QueryBuilder::table('payments')->insert([
            'clinic_id' => $clinicId,
            'invoice_id' => $invoiceId,
            'amount' => $amount,
            'method' => match ($method) {
                'upi', 'card', 'online' => $method,
                default => 'cash',
            },
            'gateway_ref' => $gatewayRef,
            'recorded_by' => $user['id'] ?? null,
        ]);

        QueryBuilder::table('invoices')
            ->forClinic($clinicId)
            ->where('id', '=', $invoiceId)
            ->update([
                'status' => 'paid',
                'payment_mode' => $method === 'upi' ? 'upi' : ($method === 'card' ? 'card' : ($method === 'online' ? 'online' : 'cash')),
                'paid_at' => date('Y-m-d H:i:s'),
            ]);

        $invoice = self::findDetailed($clinicId, $invoiceId);
        $patient = PatientService::find($clinicId, (int) $invoice['patient_id']);
        $clinic = QueryBuilder::table('tenants')->where('id', '=', $clinicId)->first();

        if ($invoice !== null && $patient !== null && $clinic !== null) {
            $pdfPath = InvoicePdfService::generate($invoice, $patient, $clinic);
            QueryBuilder::table('invoices')
                ->forClinic($clinicId)
                ->where('id', '=', $invoiceId)
                ->update(['pdf_path' => $pdfPath]);

            NotificationService::queueWhatsApp(
                $clinicId,
                (int) $patient['id'],
                (string) $patient['phone'],
                'invoice_paid',
                [
                    'patient_name' => $patient['name'],
                    'clinic_name' => $clinic['name'],
                    'invoice_number' => $invoice['invoice_number'],
                    'total' => $invoice['total'],
                    'pdf_url' => $pdfPath,
                ],
                date('Y-m-d H:i:s', time() + 60),
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

    private static function nextInvoiceNumber(int $clinicId, string $prefix): string
    {
        $count = QueryBuilder::table('invoices')->forClinic($clinicId)->count();

        return $prefix . '-' . str_pad((string) ($count + 1), 5, '0', STR_PAD_LEFT);
    }
}
