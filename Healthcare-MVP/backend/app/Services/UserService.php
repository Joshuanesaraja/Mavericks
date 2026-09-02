<?php

require_once __DIR__ . '/../Repositories/UserRepository.php';

// service can check:

// whether a user is active
// whether a role can be assigned
// whether the user belongs to the tenant
// whether profile changes are allowed

class UserService
{
    public static function getProfile(
        int $userId,
        int $tenantId
    ): ?array {
        return UserRepository::findById(
            $userId,
            $tenantId
        );
    }
}