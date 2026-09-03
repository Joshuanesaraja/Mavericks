<?php

require_once __DIR__ . '/../Repositories/UserRepository.php';
require_once __DIR__ . '/../Security/Hash.php';

// service can check:

// whether a user is active
// whether a role can be assigned
// whether the user belongs to the tenant
// whether profile changes are allowed

class UserService
{
    // GET Profile

    public static function getProfile(
        int $userId,
        int $tenantId
    ): ?array {
        return UserRepository::findById(
            $userId,
            $tenantId
        );
    }

    // Password Change

    public static function changePassword(
        int $userId,
        int $tenantId,
        string $currentPassword,
        string $newPassword
    ): bool {

        $currentHash = UserRepository::findPasswordHash(
            $userId,
            $tenantId
        );

        if ($currentHash === null) {
            throw new Exception('User not found');
        }

        if (!Hash::verify($currentPassword, $currentHash)) {
            throw new Exception('Current password is incorrect');
        }

        $newHash = Hash::make($newPassword);

        return UserRepository::updatePassword(
            $userId,
            $tenantId,
            $newHash
        );
    }
}
