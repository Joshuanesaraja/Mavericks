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
        int $patientId,
        ?int $appointmentId,
        float $amount
    ): int {
        if ($amount <= 0) {
            throw new Exception(
                'Invoice amount must be greater than zero.'
            );
        }

        if (!$this->repository->patientBelongsToTenant(
            $patientId
        )) {
            throw new Exception(
                'Patient does not exist in the current tenant.'
            );
        }

        if ($appointmentId !== null) {

            if (!$this->repository->appointmentBelongsToTenant(
                $appointmentId,
                $patientId
            )) {
                throw new Exception(
                    'Appointment does not belong to the selected patient.'
                );
            }
        }

        $invoiceNumber = 'INV-' .
            strtoupper(bin2hex(random_bytes(6)));

        return $this->repository->createInvoice(
            $patientId,
            $appointmentId,
            $invoiceNumber,
            $amount,
            'pending'
        );
    }

    // Retrieve a single invoice.
    public function getInvoice(
        int $invoiceId
    ): array {
        $invoice = $this->repository->findInvoiceById(
            $invoiceId
        );

        if ($invoice === null) {
            throw new Exception(
                'Invoice not found.'
            );
        }

        return $invoice;
    }

    // Retrieve all invoices for the current tenant.
    public function getInvoices(): array
    {
        return $this->repository->findAllInvoices();
    }

    // Update the invoice status.
    public function updateInvoiceStatus(
        int $invoiceId,
        string $status
    ): bool {
        $allowedStatuses = [
            'pending',
            'paid',
            'cancelled'
        ];

        if (!in_array(
            $status,
            $allowedStatuses,
            true
        )) {
            throw new Exception(
                'Invalid invoice status.'
            );
        }

        $invoice = $this->repository->findInvoiceById(
            $invoiceId
        );

        if ($invoice === null) {
            throw new Exception(
                'Invoice not found.'
            );
        }

        return $this->repository->updateInvoiceStatus(
            $invoiceId,
            $status
        );
    }

    // Record a payment against an invoice.
    public function createPayment(
        int $invoiceId,
        float $amount
    ): int {
        if ($amount <= 0) {
            throw new Exception(
                'Payment amount must be greater than zero.'
            );
        }

        $invoice = $this->repository->findInvoiceById(
            $invoiceId
        );

        if ($invoice === null) {
            throw new Exception(
                'Invoice not found.'
            );
        }

        if ($invoice['status'] === 'cancelled') {
            throw new Exception(
                'Cannot pay a cancelled invoice.'
            );
        }

        if ($invoice['status'] === 'paid') {
            throw new Exception(
                'Invoice is already fully paid.'
            );
        }

        $invoiceAmount = (float) $invoice['amount'];

        $paidAmount = $this->repository->getPaidAmount(
            $invoiceId
        );

        $remainingAmount =
            $invoiceAmount - $paidAmount;

        if ($remainingAmount <= 0) {
            throw new Exception(
                'Invoice is already fully paid.'
            );
        }

        if ($amount > $remainingAmount) {
            throw new Exception(
                'Payment amount cannot exceed the remaining invoice amount.'
            );
        }

        $paymentId = $this->repository->createPayment(
            $invoiceId,
            $amount,
            'paid',
            date('Y-m-d H:i:s')
        );

        $newPaidAmount =
            $paidAmount + $amount;

        if ($newPaidAmount >= $invoiceAmount) {

            $this->repository->updateInvoiceStatus(
                $invoiceId,
                'paid'
            );
        }

        return $paymentId;
    }

    // Retrieve all payments for an invoice.
    public function getPayments(
        int $invoiceId
    ): array {
        $invoice = $this->repository->findInvoiceById(
            $invoiceId
        );

        if ($invoice === null) {
            throw new Exception(
                'Invoice not found.'
            );
        }

        return $this->repository->findPaymentsByInvoice(
            $invoiceId
        );
    }

    // Retrieve billing summary for the current tenant.
    public function getBillingSummary(): array
    {
        return $this->repository->getBillingSummary();
    }
}
