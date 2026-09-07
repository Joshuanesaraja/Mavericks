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

    // Check whether a patient exists in the current tenant database.
    public function patientBelongsToTenant(
        int $patientId
    ): bool {
        $sql = "
            SELECT id
            FROM patients
            WHERE id = :patient_id
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            ':patient_id' => $patientId
        ]);

        return $stmt->fetch() !== false;
    }

    // Check whether an appointment belongs to the selected patient
    // in the current tenant database.
    public function appointmentBelongsToTenant(
        int $appointmentId,
        int $patientId
    ): bool {
        $sql = "
            SELECT id
            FROM appointments
            WHERE id = :appointment_id
              AND patient_id = :patient_id
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            ':appointment_id' => $appointmentId,
            ':patient_id' => $patientId
        ]);

        return $stmt->fetch() !== false;
    }

    // Create a new invoice.
    public function createInvoice(
        int $patientId,
        ?int $appointmentId,
        string $invoiceNumber,
        float $amount,
        string $status
    ): int {
        $sql = "
            INSERT INTO invoices
            (
                patient_id,
                appointment_id,
                invoice_number,
                amount,
                status
            )
            VALUES
            (
                :patient_id,
                :appointment_id,
                :invoice_number,
                :amount,
                :status
            )
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            ':patient_id' => $patientId,
            ':appointment_id' => $appointmentId,
            ':invoice_number' => $invoiceNumber,
            ':amount' => $amount,
            ':status' => $status
        ]);

        return (int) $this->db->lastInsertId();
    }

    // Retrieve a single invoice.
    public function findInvoiceById(
        int $invoiceId
    ): ?array {
        $sql = "
            SELECT
                id,
                patient_id,
                appointment_id,
                invoice_number,
                amount,
                status,
                created_at,
                updated_at
            FROM invoices
            WHERE id = :invoice_id
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            ':invoice_id' => $invoiceId
        ]);

        $invoice = $stmt->fetch();

        return $invoice ?: null;
    }

    // Retrieve all invoices for the current tenant.
    public function findAllInvoices(): array
    {
        $sql = "
            SELECT
                id,
                patient_id,
                appointment_id,
                invoice_number,
                amount,
                status,
                created_at,
                updated_at
            FROM invoices
            ORDER BY created_at DESC
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute();

        return $stmt->fetchAll();
    }

    // Update the status of an invoice.
    public function updateInvoiceStatus(
        int $invoiceId,
        string $status
    ): bool {
        $sql = "
            UPDATE invoices
            SET status = :status
            WHERE id = :invoice_id
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            ':status' => $status,
            ':invoice_id' => $invoiceId
        ]);

        return $stmt->rowCount() > 0;
    }

    // Calculate the total amount already paid for an invoice.
    public function getPaidAmount(
        int $invoiceId
    ): float {
        $sql = "
            SELECT
                COALESCE(SUM(amount), 0) AS paid_amount
            FROM payments
            WHERE invoice_id = :invoice_id
              AND status = 'paid'
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            ':invoice_id' => $invoiceId
        ]);

        return (float) $stmt->fetchColumn();
    }

    // Create a new payment.
    public function createPayment(
        int $invoiceId,
        float $amount,
        string $status,
        ?string $paidAt
    ): int {
        $sql = "
            INSERT INTO payments
            (
                invoice_id,
                amount,
                status,
                paid_at
            )
            VALUES
            (
                :invoice_id,
                :amount,
                :status,
                :paid_at
            )
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            ':invoice_id' => $invoiceId,
            ':amount' => $amount,
            ':status' => $status,
            ':paid_at' => $paidAt
        ]);

        return (int) $this->db->lastInsertId();
    }

    // Retrieve all payments for an invoice.
    public function findPaymentsByInvoice(
        int $invoiceId
    ): array {
        $sql = "
            SELECT
                id,
                invoice_id,
                amount,
                status,
                paid_at,
                created_at
            FROM payments
            WHERE invoice_id = :invoice_id
            ORDER BY created_at DESC
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            ':invoice_id' => $invoiceId
        ]);

        return $stmt->fetchAll();
    }

    // Retrieve billing totals for the current tenant.
    public function getBillingSummary(): array
    {
        $sql = "
            SELECT
                COUNT(*) AS total_invoices,
                COALESCE(SUM(amount), 0) AS total_amount
            FROM invoices
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute();

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
        ";

        $paymentStmt = $this->db->prepare($paymentSql);

        $paymentStmt->execute();

        $paymentSummary = $paymentStmt->fetch();

        $totalAmount = (float) (
            $summary['total_amount'] ?? 0
        );

        $paidAmount = (float) (
            $paymentSummary['paid_amount'] ?? 0
        );

        return [
            'total_invoices' => (int) (
                $summary['total_invoices'] ?? 0
            ),
            'total_amount' => $totalAmount,
            'paid_amount' => $paidAmount,
            'pending_amount' => max(
                0,
                $totalAmount - $paidAmount
            )
        ];
    }
}
