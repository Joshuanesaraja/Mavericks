<?php

namespace App\Repositories;

use PDO;

class BillingRepository
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    // Check whether a patient belongs to the current tenant.
    public function patientBelongsToTenant(
        int $patientId,
        int $tenantId
    ): bool {
        $sql = "
            SELECT id
            FROM patients
            WHERE id = :patient_id
              AND tenant_id = :tenant_id
              AND deleted_at IS NULL
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            ':patient_id' => $patientId,
            ':tenant_id' => $tenantId
        ]);

        return $stmt->fetch() !== false;
    }

    // Check whether an appointment belongs to the current tenant and patient.
    public function appointmentBelongsToTenant(
        int $appointmentId,
        int $patientId,
        int $tenantId
    ): bool {
        $sql = "
            SELECT id
            FROM appointments
            WHERE id = :appointment_id
              AND patient_id = :patient_id
              AND tenant_id = :tenant_id
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            ':appointment_id' => $appointmentId,
            ':patient_id' => $patientId,
            ':tenant_id' => $tenantId
        ]);

        return $stmt->fetch() !== false;
    }

    // Create a new invoice.
    public function createInvoice(
        int $tenantId,
        int $patientId,
        ?int $appointmentId,
        string $invoiceNumber,
        float $amount,
        string $status
    ): int {
        $sql = "
            INSERT INTO invoices
            (
                tenant_id,
                patient_id,
                appointment_id,
                invoice_number,
                amount,
                status
            )
            VALUES
            (
                :tenant_id,
                :patient_id,
                :appointment_id,
                :invoice_number,
                :amount,
                :status
            )
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            ':tenant_id' => $tenantId,
            ':patient_id' => $patientId,
            ':appointment_id' => $appointmentId,
            ':invoice_number' => $invoiceNumber,
            ':amount' => $amount,
            ':status' => $status
        ]);

        return (int) $this->db->lastInsertId();
    }

    // Retrieve a single invoice belonging to a tenant.
    public function findInvoiceById(
        int $invoiceId,
        int $tenantId
    ): ?array {
        $sql = "
            SELECT
                id,
                tenant_id,
                patient_id,
                appointment_id,
                invoice_number,
                amount,
                status,
                created_at,
                updated_at
            FROM invoices
            WHERE id = :invoice_id
              AND tenant_id = :tenant_id
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            ':invoice_id' => $invoiceId,
            ':tenant_id' => $tenantId
        ]);

        $invoice = $stmt->fetch();

        return $invoice ?: null;
    }

    // Retrieve all invoices belonging to a tenant.
    public function findAllInvoices(
        int $tenantId
    ): array {
        $sql = "
            SELECT
                id,
                tenant_id,
                patient_id,
                appointment_id,
                invoice_number,
                amount,
                status,
                created_at,
                updated_at
            FROM invoices
            WHERE tenant_id = :tenant_id
            ORDER BY created_at DESC
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            ':tenant_id' => $tenantId
        ]);

        return $stmt->fetchAll();
    }

    // Update the status of an invoice.
    public function updateInvoiceStatus(
        int $invoiceId,
        int $tenantId,
        string $status
    ): bool {
        $sql = "
            UPDATE invoices
            SET status = :status
            WHERE id = :invoice_id
              AND tenant_id = :tenant_id
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            ':status' => $status,
            ':invoice_id' => $invoiceId,
            ':tenant_id' => $tenantId
        ]);

        return $stmt->rowCount() > 0;
    }

    // Calculate the total amount already paid for an invoice.
    public function getPaidAmount(
        int $invoiceId,
        int $tenantId
    ): float {
        $sql = "
            SELECT
                COALESCE(SUM(amount), 0) AS paid_amount
            FROM payments
            WHERE invoice_id = :invoice_id
              AND tenant_id = :tenant_id
              AND status = 'paid'
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            ':invoice_id' => $invoiceId,
            ':tenant_id' => $tenantId
        ]);

        return (float) $stmt->fetchColumn();
    }

    // Create a new payment.
    public function createPayment(
        int $tenantId,
        int $invoiceId,
        float $amount,
        string $status,
        ?string $paidAt
    ): int {
        $sql = "
            INSERT INTO payments
            (
                tenant_id,
                invoice_id,
                amount,
                status,
                paid_at
            )
            VALUES
            (
                :tenant_id,
                :invoice_id,
                :amount,
                :status,
                :paid_at
            )
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            ':tenant_id' => $tenantId,
            ':invoice_id' => $invoiceId,
            ':amount' => $amount,
            ':status' => $status,
            ':paid_at' => $paidAt
        ]);

        return (int) $this->db->lastInsertId();
    }

    // Retrieve all payments for an invoice.
    public function findPaymentsByInvoice(
        int $invoiceId,
        int $tenantId
    ): array {
        $sql = "
            SELECT
                id,
                tenant_id,
                invoice_id,
                amount,
                status,
                paid_at,
                created_at
            FROM payments
            WHERE invoice_id = :invoice_id
              AND tenant_id = :tenant_id
            ORDER BY created_at DESC
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            ':invoice_id' => $invoiceId,
            ':tenant_id' => $tenantId
        ]);

        return $stmt->fetchAll();
    }

    // Retrieve billing totals for a tenant.
    public function getBillingSummary(
        int $tenantId
    ): array {
        $sql = "
            SELECT
                COUNT(*) AS total_invoices,
                COALESCE(SUM(amount), 0) AS total_amount
            FROM invoices
            WHERE tenant_id = :tenant_id
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            ':tenant_id' => $tenantId
        ]);

        $summary = $stmt->fetch();

        $paymentSql = "
            SELECT
                COALESCE(
                    SUM(
                        CASE
                            WHEN p.status = 'paid' THEN p.amount
                            ELSE 0
                        END
                    ),
                    0
                ) AS paid_amount
            FROM payments p
            WHERE p.tenant_id = :tenant_id
        ";

        $paymentStmt = $this->db->prepare($paymentSql);

        $paymentStmt->execute([
            ':tenant_id' => $tenantId
        ]);

        $paymentSummary = $paymentStmt->fetch();

        $totalAmount = (float) ($summary['total_amount'] ?? 0);
        $paidAmount = (float) ($paymentSummary['paid_amount'] ?? 0);

        return [
            'total_invoices' => (int) ($summary['total_invoices'] ?? 0),
            'total_amount' => $totalAmount,
            'paid_amount' => $paidAmount,
            'pending_amount' => max(0, $totalAmount - $paidAmount)
        ];
    }
}
