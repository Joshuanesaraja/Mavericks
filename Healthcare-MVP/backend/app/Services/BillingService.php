<?php

namespace App\Services;

use App\Repositories\BillingRepository;
use Exception;

class BillingService
{
    private BillingRepository $repository;

    public function __construct(BillingRepository $repository)
    {
        $this->repository = $repository;
    }

    // Create a new invoice.
    public function createInvoice(
        int $tenantId,
        int $patientId,
        ?int $appointmentId,
        float $amount
    ): int {
        if ($amount <= 0) {
            throw new Exception('Invoice amount must be greater than zero.');
        }

        if (!$this->repository->patientBelongsToTenant(
            $patientId,
            $tenantId
        )) {
            throw new Exception('Patient does not belong to the current tenant.');
        }

        if ($appointmentId !== null) {
            if (!$this->repository->appointmentBelongsToTenant(
                $appointmentId,
                $patientId,
                $tenantId
            )) {
                throw new Exception(
                    'Appointment does not belong to the selected patient and tenant.'
                );
            }
        }

        $invoiceNumber = 'INV-' . strtoupper(bin2hex(random_bytes(6)));

        return $this->repository->createInvoice(
            $tenantId,
            $patientId,
            $appointmentId,
            $invoiceNumber,
            $amount,
            'pending'
        );
    }

    // Retrieve a single invoice.
    public function getInvoice(
        int $invoiceId,
        int $tenantId
    ): array {
        $invoice = $this->repository->findInvoiceById(
            $invoiceId,
            $tenantId
        );

        if ($invoice === null) {
            throw new Exception('Invoice not found.');
        }

        return $invoice;
    }

    // Retrieve all invoices for a tenant.
    public function getInvoices(
        int $tenantId
    ): array {
        return $this->repository->findAllInvoices(
            $tenantId
        );
    }

    // Update the invoice status.
    public function updateInvoiceStatus(
        int $invoiceId,
        int $tenantId,
        string $status
    ): bool {
        $allowedStatuses = [
            'pending',
            'paid',
            'cancelled'
        ];

        if (!in_array($status, $allowedStatuses, true)) {
            throw new Exception('Invalid invoice status.');
        }

        $invoice = $this->repository->findInvoiceById(
            $invoiceId,
            $tenantId
        );

        if ($invoice === null) {
            throw new Exception('Invoice not found.');
        }

        return $this->repository->updateInvoiceStatus(
            $invoiceId,
            $tenantId,
            $status
        );
    }

    // Record a payment against an invoice.
    public function createPayment(
        int $tenantId,
        int $invoiceId,
        float $amount
    ): int {
        if ($amount <= 0) {
            throw new Exception('Payment amount must be greater than zero.');
        }

        $invoice = $this->repository->findInvoiceById(
            $invoiceId,
            $tenantId
        );

        if ($invoice === null) {
            throw new Exception('Invoice not found.');
        }

        if ($invoice['status'] === 'cancelled') {
            throw new Exception('Cannot pay a cancelled invoice.');
        }

        if ($invoice['status'] === 'paid') {
            throw new Exception('Invoice is already fully paid.');
        }

        $invoiceAmount = (float) $invoice['amount'];

        $paidAmount = $this->repository->getPaidAmount(
            $invoiceId,
            $tenantId
        );

        $remainingAmount = $invoiceAmount - $paidAmount;

        if ($remainingAmount <= 0) {
            throw new Exception('Invoice is already fully paid.');
        }

        if ($amount > $remainingAmount) {
            throw new Exception(
                'Payment amount cannot exceed the remaining invoice amount.'
            );
        }

        $paymentId = $this->repository->createPayment(
            $tenantId,
            $invoiceId,
            $amount,
            'paid',
            date('Y-m-d H:i:s')
        );

        $newPaidAmount = $paidAmount + $amount;

        if ($newPaidAmount >= $invoiceAmount) {
            $this->repository->updateInvoiceStatus(
                $invoiceId,
                $tenantId,
                'paid'
            );
        }

        return $paymentId;
    }

    // Retrieve all payments for an invoice.
    public function getPayments(
        int $invoiceId,
        int $tenantId
    ): array {
        $invoice = $this->repository->findInvoiceById(
            $invoiceId,
            $tenantId
        );

        if ($invoice === null) {
            throw new Exception('Invoice not found.');
        }

        return $this->repository->findPaymentsByInvoice(
            $invoiceId,
            $tenantId
        );
    }

    // Retrieve billing summary for a tenant.
    public function getBillingSummary(
        int $tenantId
    ): array {
        return $this->repository->getBillingSummary(
            $tenantId
        );
    }
}
