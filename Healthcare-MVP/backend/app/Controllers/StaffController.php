<?php

require_once __DIR__ . '/../Services/StaffService.php';
require_once __DIR__ . '/../Repositories/StaffRepository.php';
require_once __DIR__ . '/../Helpers/Response.php';

class StaffController
{
    private static function service(): StaffService
    {
        return new StaffService(
            new StaffRepository()
        );
    }

    public static function index(
        object $auth
    ): void {
        try {
            $staff = self::service()->getStaff(
                (int) $auth->tenant_id
            );

            Response::success(
                $staff,
                'Staff fetched successfully'
            );
        } catch (Throwable $e) {
            Response::error(
                $e->getMessage(),
                400
            );
        }
    }

    public static function show(
        object $auth,
        int $staffId
    ): void {
        try {
            $staff = self::service()->getStaffMember(
                $staffId,
                (int) $auth->tenant_id
            );

            Response::success(
                $staff,
                'Staff fetched successfully'
            );
        } catch (Throwable $e) {
            Response::error(
                $e->getMessage(),
                $e->getMessage() === 'Staff not found.'
                    ? 404
                    : 400
            );
        }
    }

    public static function store(
        object $auth,
        array $input
    ): void {
        $name = trim(
            $input['name'] ?? ''
        );

        $email = trim(
            $input['email'] ?? ''
        );

        $password =
            $input['password'] ?? '';

        $staffType = strtolower(
            trim($input['staff_type'] ?? '')
        );

        if (
            $name === '' ||
            $email === '' ||
            $password === '' ||
            $staffType === ''
        ) {
            Response::error(
                'Name, email, password and staff_type are required',
                400
            );
            return;
        }

        if (!filter_var(
            $email,
            FILTER_VALIDATE_EMAIL
        )) {
            Response::error(
                'Invalid email address',
                400
            );
            return;
        }

        try {
            $staff = self::service()->createStaff(
                (int) $auth->tenant_id,
                $name,
                $email,
                $password,
                $staffType
            );

            Response::success(
                $staff,
                'Staff created successfully',
                201
            );
        } catch (Throwable $e) {
            Response::error(
                $e->getMessage(),
                400
            );
        }
    }

    public static function update(
        object $auth,
        int $staffId,
        array $input
    ): void {
        $name = trim(
            $input['name'] ?? ''
        );

        $email = trim(
            $input['email'] ?? ''
        );

        $staffType = strtolower(
            trim($input['staff_type'] ?? '')
        );

        if (
            $name === '' ||
            $email === '' ||
            $staffType === ''
        ) {
            Response::error(
                'Name, email and staff_type are required',
                400
            );
            return;
        }

        if (!filter_var(
            $email,
            FILTER_VALIDATE_EMAIL
        )) {
            Response::error(
                'Invalid email address',
                400
            );
            return;
        }

        try {
            $staff = self::service()->updateStaff(
                $staffId,
                (int) $auth->tenant_id,
                $name,
                $email,
                $staffType
            );

            Response::success(
                $staff,
                'Staff updated successfully'
            );
        } catch (Throwable $e) {
            Response::error(
                $e->getMessage(),
                $e->getMessage() === 'Staff not found.'
                    ? 404
                    : 400
            );
        }
    }

    public static function updateStatus(
        object $auth,
        int $staffId,
        array $input
    ): void {
        $status = strtolower(
            trim($input['status'] ?? '')
        );

        if ($status === '') {
            Response::error(
                'Status is required',
                400
            );
            return;
        }

        try {
            $staff = self::service()->updateStatus(
                $staffId,
                (int) $auth->tenant_id,
                $status
            );

            Response::success(
                $staff,
                'Staff status updated successfully'
            );
        } catch (Throwable $e) {
            Response::error(
                $e->getMessage(),
                $e->getMessage() === 'Staff not found.'
                    ? 404
                    : 400
            );
        }
    }

    public static function delete(
        object $auth,
        int $staffId
    ): void {
        try {
            self::service()->deleteStaff(
                $staffId,
                (int) $auth->tenant_id
            );

            Response::success(
                null,
                'Staff deleted successfully'
            );
        } catch (Throwable $e) {
            Response::error(
                $e->getMessage(),
                $e->getMessage() === 'Staff not found.'
                    ? 404
                    : 400
            );
        }
    }
}