<?php

require_once __DIR__ . '/../Repositories/UserRepository.php';
require_once __DIR__ . '/../Security/Hash.php';

class UserService
{
    private const ROLES = [
        'Admin',
        'Provider',
        'Nurse',
        'Patient',
        'Pharmacist'
    ];

    public static function getProfile(object $auth): ?array
    {
        return UserRepository::findById(
            (int) $auth->sub,
            (int) $auth->tenant_id
        );
    }

    public static function getUsers(object $auth): array
    {
        return UserRepository::findAllByTenant(
            (int) $auth->tenant_id
        );
    }

    public static function getUser(
        object $auth,
        int $userId
    ): ?array {
        return UserRepository::findById(
            $userId,
            (int) $auth->tenant_id
        );
    }

    public static function createUser(
        object $auth,
        string $name,
        string $email,
        string $password,
        string $role
    ): array {
        $tenantId = (int) $auth->tenant_id;

        $roleName = self::normalizeRole($role);

        if ($roleName === null) {
            throw new Exception('Invalid role');
        }

        $existing = UserRepository::findByEmail($email);

        if ($existing) {
            throw new Exception('Email already exists');
        }

        if (strlen($password) < 8) {
            throw new Exception(
                'Password must be at least 8 characters'
            );
        }

        $passwordHash = Hash::make($password);
        $roleId = UserRepository::findRoleIdByName($roleName);

        if ($roleId === null) {
            throw new Exception('Role not found');
        }

        $pdo = Database::connect();

        try {
            $pdo->beginTransaction();

            $userId = UserRepository::create(
                $tenantId,
                $name,
                $email,
                $passwordHash
            );

            UserRepository::assignRole(
                $userId,
                $roleId
            );

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $e;
        }

        $user = UserRepository::findById(
            $userId,
            $tenantId
        );

        if (!$user) {
            throw new Exception('User creation failed');
        }

        return $user;
    }

    public static function updateUser(
        object $auth,
        int $userId,
        string $name,
        string $email
    ): array {
        $tenantId = (int) $auth->tenant_id;

        $user = UserRepository::findById(
            $userId,
            $tenantId
        );

        if (!$user) {
            throw new Exception('User not found');
        }

        $existing = UserRepository::findByEmail($email);

        if (
            $existing &&
            (int) $existing['id'] !== $userId
        ) {
            throw new Exception('Email already exists');
        }

        UserRepository::update(
            $userId,
            $tenantId,
            $name,
            $email
        );

        return UserRepository::findById(
            $userId,
            $tenantId
        );
    }

    public static function assignRole(
        object $auth,
        int $userId,
        string $role
    ): array {
        $tenantId = (int) $auth->tenant_id;

        $user = UserRepository::findById(
            $userId,
            $tenantId
        );

        if (!$user) {
            throw new Exception('User not found');
        }

        $roleName = self::normalizeRole($role);

        if ($roleName === null) {
            throw new Exception('Invalid role');
        }

        $roleId = UserRepository::findRoleIdByName(
            $roleName
        );

        if ($roleId === null) {
            throw new Exception('Role not found');
        }

        $pdo = Database::connect();

        try {
            $pdo->beginTransaction();

            UserRepository::removeRoles(
                $userId,
                $tenantId
            );

            UserRepository::assignRole(
                $userId,
                $roleId
            );

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $e;
        }

        return UserRepository::findById(
            $userId,
            $tenantId
        );
    }

    public static function updateStatus(
        object $auth,
        int $userId,
        string $status
    ): array {
        $tenantId = (int) $auth->tenant_id;

        $status = strtolower(trim($status));

        if (!in_array(
            $status,
            ['active', 'inactive'],
            true
        )) {
            throw new Exception('Invalid status');
        }

        $user = UserRepository::findById(
            $userId,
            $tenantId
        );

        if (!$user) {
            throw new Exception('User not found');
        }

        UserRepository::updateStatus(
            $userId,
            $tenantId,
            $status
        );

        return UserRepository::findById(
            $userId,
            $tenantId
        );
    }

    public static function changePassword(
        object $auth,
        string $currentPassword,
        string $newPassword
    ): bool {
        $userId = (int) $auth->sub;
        $tenantId = (int) $auth->tenant_id;

        $hash = UserRepository::findPasswordHash(
            $userId,
            $tenantId
        );

        if (
            !$hash ||
            !Hash::verify($currentPassword, $hash)
        ) {
            throw new Exception(
                'Current password is incorrect'
            );
        }

        if (strlen($newPassword) < 8) {
            throw new Exception(
                'New password must be at least 8 characters'
            );
        }

        if (Hash::verify($newPassword, $hash)) {
            throw new Exception(
                'New password must be different from current password'
            );
        }

        $newHash = Hash::make($newPassword);

        if (!UserRepository::updatePassword(
            $userId,
            $tenantId,
            $newHash
        )) {
            throw new Exception(
                'Password update failed'
            );
        }

        UserRepository::revokeRefreshTokensByUser(
            $userId,
            $tenantId
        );

        return true;
    }

    private static function normalizeRole(
        string $role
    ): ?string {
        $role = strtolower(trim($role));

        foreach (self::ROLES as $allowedRole) {
            if (strtolower($allowedRole) === $role) {
                return $allowedRole;
            }
        }

        return null;
    }
}