<?php

require_once __DIR__ . '/../Middleware/AuthMiddleware.php';
require_once __DIR__ . '/../Services/CalendarService.php';
require_once __DIR__ . '/../Helpers/Response.php';

class CalendarController
{
    /**
     * GET /calendar/date?date=YYYY-MM-DD
     */
    public static function getByDate(): void
    {
        $auth = AuthMiddleware::handle();
        if ($auth === null) return;

        $dateStr = trim($_GET['date'] ?? '');
        $result = CalendarService::getAppointmentsByDate($auth, $dateStr);

        if ($result['success']) {
            Response::success($result['data'], $result['message'], $result['code']);
        } else {
            Response::error($result['message'], $result['code']);
        }
    }

    /**
     * GET /calendar/range?start_date=YYYY-MM-DD&end_date=YYYY-MM-DD
     */
    public static function getByRange(): void
    {
        $auth = AuthMiddleware::handle();
        if ($auth === null) return;

        $startDate = trim($_GET['start_date'] ?? $_GET['start'] ?? '');
        $endDate   = trim($_GET['end_date'] ?? $_GET['end'] ?? '');

        if (empty($startDate) || empty($endDate)) {
            Response::error('start_date and end_date query parameters are required', 400);
            return;
        }

        $result = CalendarService::getAppointmentsByRange($auth, $startDate, $endDate);

        if ($result['success']) {
            Response::success($result['data'], $result['message'], $result['code']);
        } else {
            Response::error($result['message'], $result['code']);
        }
    }
}
