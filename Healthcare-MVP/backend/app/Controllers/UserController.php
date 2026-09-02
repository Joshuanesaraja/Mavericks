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
}
