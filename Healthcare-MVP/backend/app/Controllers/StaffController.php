<?php

require_once __DIR__ . '/../Services/StaffService.php';
require_once __DIR__ . '/../Repositories/UserRepository.php';
require_once __DIR__ . '/../Helpers/Response.php';

class StaffController
{
    public static function index(object $auth): void
    {
        try {
            $repository = new UserRepository();
            $service = new StaffService($repository);

            $staff = $service->getStaff(
                (int) $auth->tenant_id
            );

            Response::success(
                $staff,
                'Staff fetched successfully'
            );
        } catch (Exception $e) {
            Response::error(
                $e->getMessage(),
                400
            );
        }
    }

    public static function assignRole(
        object $auth,
        int $userId,
        array $input
    ): void {
        $role = trim($input['role'] ?? '');

        if ($role === '') {
            Response::error(
                'Role is required',
                400
            );
            return;
        }

        try {
            $repository = new UserRepository();
            $service = new StaffService($repository);

            $service->assignStaffRole(
                $userId,
                (int) $auth->tenant_id,
                $role
            );

            $user = UserRepository::findById(
                $userId,
                (int) $auth->tenant_id
            );

            Response::success(
                $user,
                'Staff role assigned successfully'
            );
        } catch (Exception $e) {
            $status = $e->getMessage() === 'User not found'
                ? 404
                : 400;

            Response::error(
                $e->getMessage(),
                $status
            );
        }
    }

    public static function delete(
        object $auth,
        int $userId
    ): void {
        try {
            $repository = new UserRepository();
            $service = new StaffService($repository);

            $service->deactivateStaff(
                $userId,
                (int) $auth->tenant_id
            );

            Response::success(
                null,
                'Staff deactivated successfully'
            );
        } catch (Exception $e) {
            $status = $e->getMessage() === 'Staff not found'
                ? 404
                : 400;

            Response::error(
                $e->getMessage(),
                $status
            );
        }
    }
}
