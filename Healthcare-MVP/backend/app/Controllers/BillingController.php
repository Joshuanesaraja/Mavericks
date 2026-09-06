<?php

namespace App\Controllers;

use App\Repositories\BillingRepository;
use App\Services\BillingService;
use Exception;

class BillingController
{
    // Create a new invoice for a patient.
    public static function createInvoice(
        object $auth,
        array $input
    ): void {
        try {
            $tenantId = (int) ($auth->tenant_id ?? 0);
            $patientId = (int) ($input['patient_id'] ?? 0);
            $appointmentId = isset($input['appointment_id'])
                ? (int) $input['appointment_id']
                : null;
            $amount = (float) ($input['amount'] ?? 0);

            if ($tenantId <= 0 || $patientId <= 0 || $amount <= 0) {
                \Response::error(
                    'Invalid invoice data.',
                    400
                );
                return;
            }

            $service = new BillingService(
                new BillingRepository(
                    \Database::connect()
                )
            );

            $invoiceId = $service->createInvoice(
                $tenantId,
                $patientId,
                $appointmentId,
                $amount
            );

            $invoice = $service->getInvoice(
                $invoiceId,
                $tenantId
            );

            \Response::success(
                $invoice,
                'Invoice created successfully.',
                201
            );
        } catch (Exception $e) {
            \Response::error(
                $e->getMessage(),
                400
            );
        }
    }

    // Retrieve a single invoice belonging to the current tenant.
    public static function getInvoice(
        object $auth,
        int $invoiceId
    ): void {
        try {
            $tenantId = (int) ($auth->tenant_id ?? 0);

            $service = new BillingService(
                new BillingRepository(
                    \Database::connect()
                )
            );

            $invoice = $service->getInvoice(
                $invoiceId,
                $tenantId
            );

            \Response::success(
                $invoice,
                'Invoice retrieved successfully.'
            );
        } catch (Exception $e) {
            \Response::error(
                $e->getMessage(),
                404
            );
        }
    }

    // Retrieve all invoices belonging to the current tenant.
    public static function getInvoices(
        object $auth
    ): void {
        try {
            $tenantId = (int) ($auth->tenant_id ?? 0);

            $service = new BillingService(
                new BillingRepository(
                    \Database::connect()
                )
            );

            $invoices = $service->getInvoices(
                $tenantId
            );

            \Response::success(
                $invoices,
                'Invoices retrieved successfully.'
            );
        } catch (Exception $e) {
            \Response::error(
                $e->getMessage(),
                400
            );
        }
    }

    // Update the status of an invoice.
    public static function updateInvoiceStatus(
        object $auth,
        int $invoiceId,
        array $input
    ): void {
        try {
            $tenantId = (int) ($auth->tenant_id ?? 0);
            $status = trim((string) ($input['status'] ?? ''));

            if ($status === '') {
                \Response::error(
                    'Invoice status is required.',
                    400
                );
                return;
            }

            $service = new BillingService(
                new BillingRepository(
                    \Database::connect()
                )
            );

            $service->updateInvoiceStatus(
                $invoiceId,
                $tenantId,
                $status
            );

            $invoice = $service->getInvoice(
                $invoiceId,
                $tenantId
            );

            \Response::success(
                $invoice,
                'Invoice status updated successfully.'
            );
        } catch (Exception $e) {
            \Response::error(
                $e->getMessage(),
                400
            );
        }
    }

    // Record a payment against an invoice.
    public static function createPayment(
        object $auth,
        array $input
    ): void {
        try {
            $tenantId = (int) ($auth->tenant_id ?? 0);
            $invoiceId = (int) ($input['invoice_id'] ?? 0);
            $amount = (float) ($input['amount'] ?? 0);

            if ($tenantId <= 0 || $invoiceId <= 0 || $amount <= 0) {
                \Response::error(
                    'Invalid payment data.',
                    400
                );
                return;
            }

            $service = new BillingService(
                new BillingRepository(
                    \Database::connect()
                )
            );

            $paymentId = $service->createPayment(
                $tenantId,
                $invoiceId,
                $amount
            );

            \Response::success(
                [
                    'payment_id' => $paymentId
                ],
                'Payment recorded successfully.',
                201
            );
        } catch (Exception $e) {
            \Response::error(
                $e->getMessage(),
                400
            );
        }
    }

    // Retrieve all payments associated with an invoice.
    public static function getPayments(
        object $auth,
        int $invoiceId
    ): void {
        try {
            $tenantId = (int) ($auth->tenant_id ?? 0);

            $service = new BillingService(
                new BillingRepository(
                    \Database::connect()
                )
            );

            $payments = $service->getPayments(
                $invoiceId,
                $tenantId
            );

            \Response::success(
                $payments,
                'Payments retrieved successfully.'
            );
        } catch (Exception $e) {
            \Response::error(
                $e->getMessage(),
                404
            );
        }
    }

    // Retrieve billing totals for the current tenant.
    public static function getBillingSummary(
        object $auth
    ): void {
        try {
            $tenantId = (int) ($auth->tenant_id ?? 0);

            $service = new BillingService(
                new BillingRepository(
                    \Database::connect()
                )
            );

            $summary = $service->getBillingSummary(
                $tenantId
            );

            \Response::success(
                $summary,
                'Billing summary retrieved successfully.'
            );
        } catch (Exception $e) {
            \Response::error(
                $e->getMessage(),
                400
            );
        }
    }
}
