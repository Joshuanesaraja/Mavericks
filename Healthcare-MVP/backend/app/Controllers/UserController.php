<?php

require_once __DIR__ . '/../Helpers/Response.php';
require_once __DIR__ . '/../Services/UserService.php';

class UserController
{
    public static function profile(object $auth): void
    {
        $userId = (int) $auth->sub;
        $tenantId = (int) $auth->tenant_id;

        $user = UserService::getProfile(
            $userId,
            $tenantId
        );

        if ($user === null) {
            Response::error('User not found', 404);
            return;
        }

        Response::success([
            'user' => $user,
            'roles' => $auth->roles
        ]);
    }

    // Change Password
    
    public static function changePassword(
        object $auth,
        array $input
    ): void {
        $currentPassword = $input['current_password'] ?? '';
        $newPassword = $input['new_password'] ?? '';

        if ($currentPassword === '' || $newPassword === '') {
            Response::error(
                'Current password and new password are required',
                400
            );
            return;
        }

        if (strlen($newPassword) < 8) {
            Response::error(
                'New password must be at least 8 characters',
                400
            );
            return;
        }

        if ($currentPassword === $newPassword) {
            Response::error(
                'New password must be different from current password',
                400
            );
            return;
        }

        try {
            UserService::changePassword(
                (int) $auth->sub,
                (int) $auth->tenant_id,
                $currentPassword,
                $newPassword
            );

            Response::success(
                null,
                'Password changed successfully'
            );
        } catch (Throwable $e) {
            Response::error(
                $e->getMessage(),
                400
            );
        }
    }
}
