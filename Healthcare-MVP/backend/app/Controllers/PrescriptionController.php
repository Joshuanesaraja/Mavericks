<?php

require_once __DIR__ . '/../Middleware/AuthMiddleware.php';
require_once __DIR__ . '/../Middleware/RoleMiddleware.php';
require_once __DIR__ . '/../Services/PrescriptionService.php';
require_once __DIR__ . '/../Helpers/Response.php';

class PrescriptionController
{
    /**
     * POST /prescriptions/create or POST /prescriptions
     */
    public static function create(): void
    {
        $auth = AuthMiddleware::handle();
        if ($auth === null) return;

        if (!RoleMiddleware::handle($auth, ['Provider', 'Admin'])) {
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $result = PrescriptionService::createPrescription($auth, $input);

        if ($result['success']) {
            Response::success($result['data'], $result['message'], $result['code']);
        } else {
            Response::error($result['message'], $result['code']);
        }
    }

    /**
     * POST /prescriptions/verify or PUT /prescriptions/status
     */
    public static function updateStatus(): void
    {
        $auth = AuthMiddleware::handle();
        if ($auth === null) return;

        if (!RoleMiddleware::handle($auth, ['Pharmacist', 'Admin'])) {
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $id = (int) ($_GET['id'] ?? $input['id'] ?? 0);
        $status = trim($input['status'] ?? $_GET['status'] ?? '');

        if ($id <= 0 || empty($status)) {
            Response::error('Prescription id and status are required', 400);
            return;
        }

        $result = PrescriptionService::updateStatus($auth, $id, $status);

        if ($result['success']) {
            Response::success($result['data'], $result['message'], $result['code']);
        } else {
            Response::error($result['message'], $result['code']);
        }
    }

    /**
     * GET /prescriptions/detail
     */
    public static function detail(): void
    {
        $auth = AuthMiddleware::handle();
        if ($auth === null) return;

        $id = (int) ($_GET['id'] ?? 0);

        if ($id <= 0) {
            Response::error('Prescription id is required', 400);
            return;
        }

        $result = PrescriptionService::getPrescriptionDetail($auth, $id);

        if ($result['success']) {
            Response::success($result['data'], $result['message'], $result['code']);
        } else {
            Response::error($result['message'], $result['code']);
        }
    }

    /**
     * GET /prescriptions or GET /prescriptions/list
     */
    public static function list(): void
    {
        $auth = AuthMiddleware::handle();
        if ($auth === null) return;

        $result = PrescriptionService::listPrescriptions($auth, $_GET);

        if ($result['success']) {
            Response::success($result['data'], $result['message'], $result['code']);
        } else {
            Response::error($result['message'], $result['code']);
        }
    }
}
