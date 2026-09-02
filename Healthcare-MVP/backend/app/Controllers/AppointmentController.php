<?php

require_once __DIR__ . '/../Middleware/AuthMiddleware.php';
require_once __DIR__ . '/../Services/AppointmentService.php';

class AppointmentController
{
    /**
     * POST /appointments/create or POST /appointments
     */
    public static function create(): void
    {
        $user = AuthMiddleware::authenticate();
        if (!$user) {
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $result = AppointmentService::createAppointment($user, $input);

        http_response_code($result['code']);
        echo json_encode($result);
    }

    /**
     * PUT /appointments/update or POST /appointments/update
     */
    public static function update(): void
    {
        $user = AuthMiddleware::authenticate();
        if (!$user) {
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $id = (int) ($_GET['id'] ?? $input['id'] ?? 0);

        if ($id <= 0) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Appointment id is required'
            ]);
            return;
        }

        $result = AppointmentService::updateAppointment($user, $id, $input);

        http_response_code($result['code']);
        echo json_encode($result);
    }

    /**
     * POST /appointments/cancel or PUT /appointments/cancel
     */
    public static function cancel(): void
    {
        $user = AuthMiddleware::authenticate();
        if (!$user) {
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $id = (int) ($_GET['id'] ?? $input['id'] ?? 0);
        $reason = $input['reason'] ?? $_GET['reason'] ?? null;

        if ($id <= 0) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Appointment id is required'
            ]);
            return;
        }

        $result = AppointmentService::cancelAppointment($user, $id, $reason);

        http_response_code($result['code']);
        echo json_encode($result);
    }

    /**
     * POST /appointments/status or PUT /appointments/status
     */
    public static function updateStatus(): void
    {
        $user = AuthMiddleware::authenticate();
        if (!$user) {
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $id = (int) ($_GET['id'] ?? $input['id'] ?? 0);
        $status = trim($input['status'] ?? $_GET['status'] ?? '');

        if ($id <= 0 || empty($status)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Appointment id and status are required'
            ]);
            return;
        }

        $result = AppointmentService::updateStatus($user, $id, $status);

        http_response_code($result['code']);
        echo json_encode($result);
    }

    /**
     * GET /appointments/upcoming
     */
    public static function upcoming(): void
    {
        $user = AuthMiddleware::authenticate();
        if (!$user) {
            return;
        }

        $result = AppointmentService::getUpcomingAppointments($user);

        http_response_code($result['code']);
        echo json_encode($result);
    }

    /**
     * GET /appointments/detail
     */
    public static function detail(): void
    {
        $user = AuthMiddleware::authenticate();
        if (!$user) {
            return;
        }

        $id = (int) ($_GET['id'] ?? 0);

        if ($id <= 0) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Appointment id is required'
            ]);
            return;
        }

        $result = AppointmentService::getAppointmentDetail($user, $id);

        http_response_code($result['code']);
        echo json_encode($result);
    }

    /**
     * GET /appointments or GET /appointments/list
     */
    public static function list(): void
    {
        $user = AuthMiddleware::authenticate();
        if (!$user) {
            return;
        }

        $result = AppointmentService::listAppointments($user, $_GET);

        http_response_code($result['code']);
        echo json_encode($result);
    }
}
