<?php

require_once __DIR__ . '/../Services/PrescriptionService.php';
require_once __DIR__ . '/../Helpers/Response.php';

class PrescriptionController
{
    /**
     * POST /prescriptions/create
     * POST /prescriptions
     *
     * Router is responsible for:
     * - CSRF validation
     * - AES decryption
     * - JWT authentication
     * - Role validation
     */
    public static function create(object $auth, array $input): void
    {
        try {
            $result = PrescriptionService::createPrescription(
                $auth,
                $input
            );

            if ($result['success']) {
                Response::success(
                    $result['data'] ?? null,
                    $result['message'] ?? 'Prescription created successfully',
                    $result['code'] ?? 201
                );
            } else {
                Response::error(
                    $result['message'] ?? 'Failed to create prescription',
                    $result['code'] ?? 400
                );
            }
        } catch (Throwable $e) {
            Response::error(
                $e->getMessage(),
                400
            );
        }
    }

    /**
     * POST /prescriptions/verify
     * PUT /prescriptions/status
     *
     * Router is responsible for:
     * - CSRF validation
     * - AES decryption
     * - JWT authentication
     * - Role validation
     */
    public static function updateStatus(object $auth, array $input): void
    {
        $id = (int) (
            $_GET['id']
            ?? $input['id']
            ?? 0
        );

        $status = trim(
            $input['status']
            ?? $_GET['status']
            ?? ''
        );

        if ($id <= 0 || $status === '') {
            Response::error(
                'Prescription id and status are required',
                400
            );
            return;
        }

        try {
            $result = PrescriptionService::updateStatus(
                $auth,
                $id,
                $status
            );

            if ($result['success']) {
                Response::success(
                    $result['data'] ?? null,
                    $result['message'] ?? 'Prescription status updated successfully',
                    $result['code'] ?? 200
                );
            } else {
                Response::error(
                    $result['message'] ?? 'Failed to update prescription status',
                    $result['code'] ?? 400
                );
            }
        } catch (Throwable $e) {
            Response::error(
                $e->getMessage(),
                400
            );
        }
    }

    /**
     * GET /prescriptions/detail?id=X
     */
    public static function detail(object $auth): void
    {
        $id = (int) (
            $_GET['id']
            ?? 0
        );

        if ($id <= 0) {
            Response::error(
                'Prescription id is required',
                400
            );
            return;
        }

        try {
            $result = PrescriptionService::getPrescriptionDetail(
                $auth,
                $id
            );

            if ($result['success']) {
                Response::success(
                    $result['data'] ?? null,
                    $result['message'] ?? 'Prescription details fetched successfully',
                    $result['code'] ?? 200
                );
            } else {
                Response::error(
                    $result['message'] ?? 'Failed to fetch prescription details',
                    $result['code'] ?? 400
                );
            }
        } catch (Throwable $e) {
            Response::error(
                $e->getMessage(),
                400
            );
        }
    }

    /**
     * GET /prescriptions
     * GET /prescriptions/list
     */
    public static function list(object $auth): void
    {
        try {
            $result = PrescriptionService::listPrescriptions(
                $auth,
                $_GET
            );

            if ($result['success']) {
                Response::success(
                    $result['data'] ?? null,
                    $result['message'] ?? 'Prescriptions fetched successfully',
                    $result['code'] ?? 200
                );
            } else {
                Response::error(
                    $result['message'] ?? 'Failed to fetch prescriptions',
                    $result['code'] ?? 400
                );
            }
        } catch (Throwable $e) {
            Response::error(
                $e->getMessage(),
                400
            );
        }
    }
}
