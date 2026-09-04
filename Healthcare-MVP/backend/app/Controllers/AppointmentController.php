<?php

require_once __DIR__ . '/../Middleware/AuthMiddleware.php';
require_once __DIR__ . '/../Services/AppointmentService.php';
require_once __DIR__ . '/../Helpers/Response.php';

class AppointmentController
{
    /**
     * POST /appointments/create or POST /appointments
     */
    public static function create(array $user, array $input): void
    {
        try {
            $result = AppointmentService::createAppointment($user, $input);

            if ($result['success']) {
                Response::success(
                    $result['data'] ?? null,
                    $result['message'] ?? 'Appointment created successfully',
                    $result['code'] ?? 200
                );
            } else {
                Response::error(
                    $result['message'] ?? 'Failed to create appointment',
                    $result['code'] ?? 400
                );
            }
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    /**
     * PUT /appointments/update or POST /appointments/update
     */
    public static function update(array $user, array $input): void
    {
        $id = (int) ($_GET['id'] ?? $input['id'] ?? 0);

        if ($id <= 0) {
            Response::error('Appointment id is required', 400);
            return;
        }

        try {
            $result = AppointmentService::updateAppointment(
                $user,
                $id,
                $input
            );

            if ($result['success']) {
                Response::success(
                    $result['data'] ?? null,
                    $result['message'] ?? 'Appointment updated successfully',
                    $result['code'] ?? 200
                );
            } else {
                Response::error(
                    $result['message'] ?? 'Failed to update appointment',
                    $result['code'] ?? 400
                );
            }
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    /**
     * POST /appointments/cancel or PUT /appointments/cancel
     */
    public static function cancel(array $user, array $input): void
    {
        $id = (int) ($_GET['id'] ?? $input['id'] ?? 0);
        $reason = $input['reason'] ?? $_GET['reason'] ?? null;

        if ($id <= 0) {
            Response::error('Appointment id is required', 400);
            return;
        }

        try {
            $result = AppointmentService::cancelAppointment(
                $user,
                $id,
                $reason
            );

            if ($result['success']) {
                Response::success(
                    $result['data'] ?? null,
                    $result['message'] ?? 'Appointment cancelled successfully',
                    $result['code'] ?? 200
                );
            } else {
                Response::error(
                    $result['message'] ?? 'Failed to cancel appointment',
                    $result['code'] ?? 400
                );
            }
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    /**
     * POST /appointments/status or PUT /appointments/status
     */
    public static function updateStatus(array $user, array $input): void
    {
        $id = (int) ($_GET['id'] ?? $input['id'] ?? 0);
        $status = trim($input['status'] ?? $_GET['status'] ?? '');

        if ($id <= 0 || $status === '') {
            Response::error(
                'Appointment id and status are required',
                400
            );
            return;
        }

        try {
            $result = AppointmentService::updateStatus(
                $user,
                $id,
                $status
            );

            if ($result['success']) {
                Response::success(
                    $result['data'] ?? null,
                    $result['message'] ?? 'Appointment status updated successfully',
                    $result['code'] ?? 200
                );
            } else {
                Response::error(
                    $result['message'] ?? 'Failed to update appointment status',
                    $result['code'] ?? 400
                );
            }
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    /**
     * GET /appointments/upcoming
     */
    public static function upcoming(array $user): void
    {
        try {
            $result = AppointmentService::getUpcomingAppointments($user);

            if ($result['success']) {
                Response::success(
                    $result['data'] ?? null,
                    $result['message'] ?? 'Upcoming appointments fetched successfully',
                    $result['code'] ?? 200
                );
            } else {
                Response::error(
                    $result['message'] ?? 'Failed to fetch upcoming appointments',
                    $result['code'] ?? 400
                );
            }
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    /**
     * GET /appointments/detail
     */
    public static function detail(array $user): void
    {
        $id = (int) ($_GET['id'] ?? 0);

        if ($id <= 0) {
            Response::error('Appointment id is required', 400);
            return;
        }

        try {
            $result = AppointmentService::getAppointmentDetail(
                $user,
                $id
            );

            if ($result['success']) {
                Response::success(
                    $result['data'] ?? null,
                    $result['message'] ?? 'Appointment details fetched successfully',
                    $result['code'] ?? 200
                );
            } else {
                Response::error(
                    $result['message'] ?? 'Failed to fetch appointment details',
                    $result['code'] ?? 400
                );
            }
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    /**
     * GET /appointments or GET /appointments/list
     */
    public static function list(array $user): void
    {
        try {
            $result = AppointmentService::listAppointments(
                $user,
                $_GET
            );

            if ($result['success']) {
                Response::success(
                    $result['data'] ?? null,
                    $result['message'] ?? 'Appointments fetched successfully',
                    $result['code'] ?? 200
                );
            } else {
                Response::error(
                    $result['message'] ?? 'Failed to fetch appointments',
                    $result['code'] ?? 400
                );
            }
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 400);
        }
    }
}
