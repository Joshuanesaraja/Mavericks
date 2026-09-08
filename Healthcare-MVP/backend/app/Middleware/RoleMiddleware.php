<?php

require_once __DIR__ . '/../Helpers/Response.php';

class RoleMiddleware
{
    public static function handle(
        object $auth,
        array $allowedRoles
    ): bool {
        $userRoles = $auth->roles ?? [];

        foreach ($allowedRoles as $allowedRole) {
            if (in_array($allowedRole, $userRoles, true)) {
                return true;
            }
        }

        Response::error('Access denied', 403);

        return false;
    }
}
