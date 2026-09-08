<?php

namespace App\Controllers;

use App\Repositories\BillingRepository;
use App\Services\BillingService;
use Exception;

class BillingController
{
    /**
     * Get a BillingService connected to the current tenant database.
     */
    private static function service(object $auth): BillingService
    {
        if (
            empty($auth->tenant_db_name) ||
            empty($auth->tenant_db_user) ||
            empty($auth->tenant_db_password)
        ) {
            throw new Exception(
                'Tenant database configuration is missing.'
            );
        }

        $db = \Database::tenant(
            $auth->tenant_db_name,
            $auth->tenant_db_user,
            $auth->tenant_db_password
        );

        return new BillingService(
            new BillingRepository($db)
        );
    }

    // Create a new invoice for a patient.
    public static function createInvoice(
        object $auth,
        array $input
    ): void {
        try {
            $patientId = (int) ($input['patient_id'] ?? 0);

            $appointmentId = isset($input['appointment_id'])
                ? (int) $input['appointment_id']
                : null;

            $amount = (float) ($input['amount'] ?? 0);

            if ($patientId <= 0 || $amount <= 0) {
                \Response::error(
                    'Invalid invoice data.',
                    400
                );
                return;
            }

            $service = self::service($auth);

            $invoiceId = $service->createInvoice(
                $patientId,
                $appointmentId,
                $amount
            );

            $invoice = $service->getInvoice(
                $invoiceId
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
            $service = self::service($auth);

            $invoice = $service->getInvoice(
                $invoiceId
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
            $service = self::service($auth);

            $invoices = $service->getInvoices();

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
            $status = trim(
                (string) ($input['status'] ?? '')
            );

            if ($status === '') {
                \Response::error(
                    'Invoice status is required.',
                    400
                );
                return;
            }

            $service = self::service($auth);

            $service->updateInvoiceStatus(
                $invoiceId,
                $status
            );

            $invoice = $service->getInvoice(
                $invoiceId
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
            $invoiceId = (int) ($input['invoice_id'] ?? 0);
            $amount = (float) ($input['amount'] ?? 0);

            if ($invoiceId <= 0 || $amount <= 0) {
                \Response::error(
                    'Invalid payment data.',
                    400
                );
                return;
            }

            $service = self::service($auth);

            $paymentId = $service->createPayment(
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
            $service = self::service($auth);

            $payments = $service->getPayments(
                $invoiceId
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
            $service = self::service($auth);

            $summary = $service->getBillingSummary();

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
