<?php

require_once __DIR__ . '/../Middleware/AuthMiddleware.php';
require_once __DIR__ . '/../Middleware/RoleMiddleware.php';
require_once __DIR__ . '/../Services/CommunicationService.php';
require_once __DIR__ . '/../Helpers/Response.php';

class CommunicationController
{
    /**
     * POST /notes/create or POST /notes
     */
    public static function createNote(): void
    {
        $auth = AuthMiddleware::handle();
        if ($auth === null) return;

        if (!RoleMiddleware::handle($auth, ['Provider', 'Nurse', 'Admin'])) {
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $result = CommunicationService::createNote($auth, $input);

        if ($result['success']) {
            Response::success($result['data'], $result['message'], $result['code']);
        } else {
            Response::error($result['message'], $result['code']);
        }
    }

    /**
     * GET /notes?appointment_id=X
     */
    public static function getNotes(): void
    {
        $auth = AuthMiddleware::handle();
        if ($auth === null) return;

        $appointmentId = (int) ($_GET['appointment_id'] ?? 0);
        $result = CommunicationService::getNotes($auth, $appointmentId);

        if ($result['success']) {
            Response::success($result['data'], $result['message'], $result['code']);
        } else {
            Response::error($result['message'], $result['code']);
        }
    }

    /**
     * POST /messages/send or POST /messages
     */
    public static function sendMessage(): void
    {
        $auth = AuthMiddleware::handle();
        if ($auth === null) return;

        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $result = CommunicationService::sendMessage($auth, $input);

        if ($result['success']) {
            Response::success($result['data'], $result['message'], $result['code']);
        } else {
            Response::error($result['message'], $result['code']);
        }
    }

    /**
     * GET /messages/history?appointment_id=X
     */
    public static function getMessageHistory(): void
    {
        $auth = AuthMiddleware::handle();
        if ($auth === null) return;

        $appointmentId = (int) ($_GET['appointment_id'] ?? 0);
        $result = CommunicationService::getMessageHistory($auth, $appointmentId);

        if ($result['success']) {
            Response::success($result['data'], $result['message'], $result['code']);
        } else {
            Response::error($result['message'], $result['code']);
        }
    }
}
