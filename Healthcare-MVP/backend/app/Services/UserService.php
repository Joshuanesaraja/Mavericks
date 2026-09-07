<?php

require_once __DIR__ . '/../Repositories/UserRepository.php';
require_once __DIR__ . '/../Config/database.php';
require_once __DIR__ . '/../Config/hash.php';

class UserService
{
    /**
     * Get the authenticated user's profile.
     */
    public static function getProfile(object $auth): ?array
    {
        return UserRepository::findById(
            $auth,
            (int) $auth->sub
        );
    }

    /**
     * Get all users in the current tenant.
     */
    public static function getUsers(object $auth): array
    {
        return UserRepository::findAll($auth);
    }

    /**
     * Get a single user.
     */
    public static function getUser(
        object $auth,
        int $userId
    ): ?array {
        return UserRepository::findById(
            $auth,
            $userId
        );
    }

    /**
     * Create a new user inside the current tenant.
     */
    public static function createUser(
        object $auth,
        string $name,
        string $email,
        string $password,
        string $role
    ): ?array {
        $name = trim($name);
        $email = strtolower(trim($email));
        $role = trim($role);

        if ($name === '') {
            throw new InvalidArgumentException(
                'Name is required.'
            );
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException(
                'Invalid email address.'
            );
        }

        if (strlen($password) < 8) {
            throw new InvalidArgumentException(
                'Password must be at least 8 characters.'
            );
        }

        /*
         * Normalize role names.
         *
         * Provider is the canonical internal role
         * for doctors.
         */
        $roleMap = [
            'admin' => 'Admin',
            'provider' => 'Provider',
            'doctor' => 'Provider',
            'nurse' => 'Nurse',
            'patient' => 'Patient',
            'pharmacist' => 'Pharmacist'
        ];

        $roleKey = strtolower($role);

        if (!isset($roleMap[$roleKey])) {
            throw new InvalidArgumentException(
                'Invalid role.'
            );
        }

        $roleName = $roleMap[$roleKey];

        /*
         * Check whether email already exists
         * in this tenant only.
         */
        $existingUser = UserRepository::findByEmail(
            $auth,
            $email
        );

        if ($existingUser) {
            throw new RuntimeException(
                'Email already exists.'
            );
        }

        /*
         * Find role in the tenant database.
         */
        $roleId = UserRepository::findRoleIdByName(
            $auth,
            $roleName
        );

        if (!$roleId) {
            throw new RuntimeException(
                'Role not found.'
            );
        }

        /*
         * Generate password hash.
         */
        $passwordHash = Hash::make($password);

        /*
         * Get the SAME tenant connection used by
         * UserRepository.
         */
        $db = Database::tenant(
            (string) $auth->tenant_db_name,
            (string) $auth->tenant_db_user,
            (string) $auth->tenant_db_password
        );

        try {
            $db->beginTransaction();

            /*
             * Create user.
             */
            $userId = UserRepository::create(
                $auth,
                $name,
                $email,
                $passwordHash
            );

            /*
             * Assign role.
             */
            UserRepository::assignRole(
                $auth,
                $userId,
                $roleId
            );

            $db->commit();

            return UserRepository::findById(
                $auth,
                $userId
            );
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            throw $e;
        }
    }

    /**
     * Update user profile information.
     */
    public static function updateUser(
        object $auth,
        int $userId,
        string $name,
        string $email
    ): ?array {
        $name = trim($name);
        $email = strtolower(trim($email));

        if ($name === '') {
            throw new InvalidArgumentException(
                'Name is required.'
            );
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException(
                'Invalid email address.'
            );
        }

        /*
         * Make sure another user does not already
         * use the requested email.
         */
        $existingUser = UserRepository::findByEmail(
            $auth,
            $email
        );

        if (
            $existingUser &&
            (int) $existingUser['id'] !== $userId
        ) {
            throw new RuntimeException(
                'Email already exists.'
            );
        }

        UserRepository::update(
            $auth,
            $userId,
            $name,
            $email
        );

        return UserRepository::findById(
            $auth,
            $userId
        );
    }

    /**
     * Assign a new role to a user.
     */
    public static function assignRole(
        object $auth,
        int $userId,
        string $role
    ): ?array {
        $role = trim($role);

        $roleMap = [
            'admin' => 'Admin',
            'provider' => 'Provider',
            'doctor' => 'Provider',
            'nurse' => 'Nurse',
            'patient' => 'Patient',
            'pharmacist' => 'Pharmacist'
        ];

        $roleKey = strtolower($role);

        if (!isset($roleMap[$roleKey])) {
            throw new InvalidArgumentException(
                'Invalid role.'
            );
        }

        $roleName = $roleMap[$roleKey];

        $roleId = UserRepository::findRoleIdByName(
            $auth,
            $roleName
        );

        if (!$roleId) {
            throw new RuntimeException(
                'Role not found.'
            );
        }

        $db = Database::tenant(
            (string) $auth->tenant_db_name,
            (string) $auth->tenant_db_user,
            (string) $auth->tenant_db_password
        );

        try {
            $db->beginTransaction();

            /*
             * Remove existing roles first so the user
             * has the requested role.
             */
            UserRepository::removeRoles(
                $auth,
                $userId
            );

            UserRepository::assignRole(
                $auth,
                $userId,
                $roleId
            );

            $db->commit();

            return UserRepository::findById(
                $auth,
                $userId
            );
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            throw $e;
        }
    }

    /**
     * Update a user's status.
     */
    public static function updateStatus(
        object $auth,
        int $userId,
        string $status
    ): ?array {
        $status = strtolower(trim($status));

        $allowedStatuses = [
            'active',
            'inactive'
        ];

        if (!in_array($status, $allowedStatuses, true)) {
            throw new InvalidArgumentException(
                'Invalid user status.'
            );
        }

        UserRepository::updateStatus(
            $auth,
            $userId,
            $status
        );

        return UserRepository::findById(
            $auth,
            $userId
        );
    }

    /**
     * Change the authenticated user's password.
     */
    public static function changePassword(
        object $auth,
        string $currentPassword,
        string $newPassword
    ): bool {
        if ($currentPassword === '') {
            throw new InvalidArgumentException(
                'Current password is required.'
            );
        }

        if (strlen($newPassword) < 8) {
            throw new InvalidArgumentException(
                'New password must be at least 8 characters.'
            );
        }

        $userId = (int) $auth->sub;

        /*
         * Get current password hash from the
         * authenticated tenant database.
         */
        $currentHash = UserRepository::findPasswordHash(
            $auth,
            $userId
        );

        if (!$currentHash) {
            throw new RuntimeException(
                'User password not found.'
            );
        }

        /*
         * Verify current password.
         */
        if (!Hash::verify($currentPassword, $currentHash)) {
            throw new RuntimeException(
                'Current password is incorrect.'
            );
        }

        /*
         * Generate new password hash.
         */
        $newPasswordHash = Hash::make($newPassword);

        $db = Database::tenant(
            (string) $auth->tenant_db_name,
            (string) $auth->tenant_db_user,
            (string) $auth->tenant_db_password
        );

        try {
            $db->beginTransaction();

            /*
             * Update password.
             */
            UserRepository::updatePassword(
                $auth,
                $userId,
                $newPasswordHash
            );

            /*
             * Revoke all existing refresh tokens.
             *
             * The user must authenticate again after
             * changing the password.
             */
            UserRepository::revokeRefreshTokensByUser(
                $auth,
                $userId
            );

            $db->commit();

            return true;
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            throw $e;
        }
    }
}
