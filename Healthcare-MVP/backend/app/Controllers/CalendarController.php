<?php

require_once __DIR__ . '/../Services/CalendarService.php';
require_once __DIR__ . '/../Helpers/Response.php';

class CalendarController
{
    /**
     * GET /calendar/date?date=YYYY-MM-DD
     *
     * Router handles:
     * - JWT authentication
     * - Role validation
     */
    public static function getByDate(object $auth): void
    {
        $dateStr = trim(
            $_GET['date'] ?? ''
        );

        try {
            $result = CalendarService::getAppointmentsByDate(
                $auth,
                $dateStr
            );

            if ($result['success']) {
                Response::success(
                    $result['data'] ?? null,
                    $result['message'] ?? 'Calendar appointments fetched successfully',
                    $result['code'] ?? 200
                );
            } else {
                Response::error(
                    $result['message'] ?? 'Failed to fetch calendar appointments',
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
     * GET /calendar/range?start_date=YYYY-MM-DD&end_date=YYYY-MM-DD
     */
    public static function getByRange(object $auth): void
    {
        $startDate = trim(
            $_GET['start_date']
                ?? $_GET['start']
                ?? ''
        );

        $endDate = trim(
            $_GET['end_date']
                ?? $_GET['end']
                ?? ''
        );

        if ($startDate === '' || $endDate === '') {
            Response::error(
                'start_date and end_date query parameters are required',
                400
            );
            return;
        }

        try {
            $result = CalendarService::getAppointmentsByRange(
                $auth,
                $startDate,
                $endDate
            );

            if ($result['success']) {
                Response::success(
                    $result['data'] ?? null,
                    $result['message'] ?? 'Calendar appointments fetched successfully',
                    $result['code'] ?? 200
                );
            } else {
                Response::error(
                    $result['message'] ?? 'Failed to fetch calendar appointments',
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
