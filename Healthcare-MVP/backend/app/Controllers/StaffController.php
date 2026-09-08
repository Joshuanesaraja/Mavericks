<?php

require_once __DIR__ . '/../Services/StaffService.php';
require_once __DIR__ . '/../Repositories/StaffRepository.php';
require_once __DIR__ . '/../Helpers/Response.php';
require_once __DIR__ . '/../Config/database.php';

class StaffController
{
    private static function service(
        object $auth
    ): StaffService {
        if (
            empty($auth->tenant_db_name) ||
            empty($auth->tenant_db_user) ||
            empty($auth->tenant_db_password)
        ) {
            throw new RuntimeException(
                'Tenant database credentials are missing.'
            );
        }

        $db = Database::tenant(
            (string) $auth->tenant_db_name,
            (string) $auth->tenant_db_user,
            (string) $auth->tenant_db_password
        );

        return new StaffService(
            new StaffRepository($db)
        );
    }

    public static function index(
        object $auth
    ): void {
        try {
            $staff =
                self::service($auth)->getStaff();

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
            $staff =
                self::service($auth)
                    ->getStaffMember(
                        $staffId
                    );

            Response::success(
                $staff,
                'Staff fetched successfully'
            );
        } catch (Throwable $e) {
            Response::error(
                $e->getMessage(),
                $e->getMessage() ===
                    'Staff not found.'
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
            trim(
                $input['staff_type'] ?? ''
            )
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
            $staff =
                self::service($auth)
                    ->createStaff(
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
            trim(
                $input['staff_type'] ?? ''
            )
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
            $staff =
                self::service($auth)
                    ->updateStaff(
                        $staffId,
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
                $e->getMessage() ===
                    'Staff not found.'
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
            trim(
                $input['status'] ?? ''
            )
        );

        if ($status === '') {
            Response::error(
                'Status is required',
                400
            );
            return;
        }

        try {
            $staff =
                self::service($auth)
                    ->updateStatus(
                        $staffId,
                        $status
                    );

            Response::success(
                $staff,
                'Staff status updated successfully'
            );
        } catch (Throwable $e) {
            Response::error(
                $e->getMessage(),
                $e->getMessage() ===
                    'Staff not found.'
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
            self::service($auth)
                ->deleteStaff(
                    $staffId
                );

            Response::success(
                null,
                'Staff deleted successfully'
            );
        } catch (Throwable $e) {
            Response::error(
                $e->getMessage(),
                $e->getMessage() ===
                    'Staff not found.'
                    ? 404
                    : 400
            );
        }
    }
}
