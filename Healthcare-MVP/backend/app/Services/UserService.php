<?php

require_once __DIR__ . '/../Repositories/UserRepository.php';
require_once __DIR__ . '/../Security/Hash.php';

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

    // GET Users

    public static function getUsers(
        int $tenantId
    ): array {
        return UserRepository::findAllByTenant(
            $tenantId
        );
    }

    // GET User

    public static function getUser(
        int $userId,
        int $tenantId
    ): array {
        $user = UserRepository::findById(
            $userId,
            $tenantId
        );

        if ($user === null) {
            throw new Exception('User not found');
        }

        return $user;
    }

    // Assign Role

    public static function assignRole(
        int $userId,
        int $tenantId,
        string $roleName
    ): bool {
        // Verify the user belongs to this tenant.
        $user = UserRepository::findById(
            $userId,
            $tenantId
        );

        if ($user === null) {
            throw new Exception('User not found');
        }

        $roleId = UserRepository::findRoleIdByName(
            $roleName
        );

        if ($roleId === null) {
            throw new Exception('Invalid role');
        }

        UserRepository::removeRoles(
            $userId,
            $tenantId
        );

        return UserRepository::assignRole(
            $userId,
            $roleId
        );
    }

    // Update User Status

    public static function updateStatus(
        int $userId,
        int $tenantId,
        string $status
    ): bool {
        if (!in_array(
            $status,
            ['active', 'inactive'],
            true
        )) {
            throw new Exception('Invalid status');
        }

        // Verify tenant ownership before updating.
        $user = UserRepository::findById(
            $userId,
            $tenantId
        );

        if ($user === null) {
            throw new Exception('User not found');
        }

        return UserRepository::updateStatus(
            $userId,
            $tenantId,
            $status
        );
    }

    // Change Password

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

        if (!Hash::verify(
            $currentPassword,
            $currentHash
        )) {
            throw new Exception(
                'Current password is incorrect'
            );
        }

        $newHash = Hash::make($newPassword);

        return UserRepository::updatePassword(
            $userId,
            $tenantId,
            $newHash
        );
    }
}