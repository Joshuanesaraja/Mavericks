<?php

namespace App\Middleware;

class RoleMiddleware
{
    /**
     * Check whether the authenticated user has one
     * of the required roles.
     *
     * @param array $user
     * @param array $allowedRoles
     * @return bool
     */
    public static function check(array $user, array $allowedRoles): bool
    {
        if (empty($user['roles'])) {
            return false;
        }

        foreach ($user['roles'] as $role) {
            if (in_array($role, $allowedRoles, true)) {
                return true;
            }
        }

        return false;
    }
}