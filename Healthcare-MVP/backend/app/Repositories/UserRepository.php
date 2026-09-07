<?php

require_once __DIR__ . '/../Config/database.php';

class UserRepository
{
    /**
     * Get a connection to the authenticated tenant's database.
     */
    private static function db(object $auth): PDO
    {
        if (
            empty($auth->tenant_db_name) ||
            empty($auth->tenant_db_user) ||
            empty($auth->tenant_db_password)
        ) {
            throw new RuntimeException(
                'Tenant database credentials are missing.'
            );
        }

        return Database::tenant(
            (string) $auth->tenant_db_name,
            (string) $auth->tenant_db_user,
            (string) $auth->tenant_db_password
        );
    }

    /**
     * Find a user by ID.
     */
    public static function findById(
        object $auth,
        int $userId
    ): ?array {
        $db = self::db($auth);

        $stmt = $db->prepare(
            'SELECT
                id,
                name,
                email,
                status,
                created_at,
                updated_at
             FROM users
             WHERE id = :id
             LIMIT 1'
        );

        $stmt->execute([
            ':id' => $userId
        ]);

        $user = $stmt->fetch();

        if (!$user) {
            return null;
        }

        $user['roles'] = self::getRoles($auth, $userId);

        return $user;
    }

    /**
     * Get all users in the current tenant.
     */
    public static function findAll(object $auth): array
    {
        $db = self::db($auth);

        $stmt = $db->query(
            'SELECT
                id,
                name,
                email,
                status,
                created_at,
                updated_at
             FROM users
             ORDER BY id DESC'
        );

        $users = $stmt->fetchAll();

        foreach ($users as &$user) {
            $user['roles'] = self::getRoles(
                $auth,
                (int) $user['id']
            );
        }

        return $users;
    }

    /**
     * Find a user by email.
     */
    public static function findByEmail(
        object $auth,
        string $email
    ): ?array {
        $db = self::db($auth);

        $stmt = $db->prepare(
            'SELECT
                id,
                name,
                email,
                password_hash,
                status,
                created_at,
                updated_at
             FROM users
             WHERE email = :email
             LIMIT 1'
        );

        $stmt->execute([
            ':email' => $email
        ]);

        $user = $stmt->fetch();

        if (!$user) {
            return null;
        }

        $user['roles'] = self::getRoles(
            $auth,
            (int) $user['id']
        );

        return $user;
    }

    /**
     * Get only the password hash of a user.
     */
    public static function findPasswordHash(
        object $auth,
        int $userId
    ): ?string {
        $db = self::db($auth);

        $stmt = $db->prepare(
            'SELECT password_hash
             FROM users
             WHERE id = :id
             LIMIT 1'
        );

        $stmt->execute([
            ':id' => $userId
        ]);

        $result = $stmt->fetch();

        return $result
            ? $result['password_hash']
            : null;
    }

    /**
     * Create a user inside the current tenant database.
     */
    public static function create(
        object $auth,
        string $name,
        string $email,
        string $passwordHash
    ): int {
        $db = self::db($auth);

        $stmt = $db->prepare(
            'INSERT INTO users
                (name, email, password_hash, status)
             VALUES
                (:name, :email, :password_hash, :status)'
        );

        $stmt->execute([
            ':name' => $name,
            ':email' => $email,
            ':password_hash' => $passwordHash,
            ':status' => 'active'
        ]);

        return (int) $db->lastInsertId();
    }

    /**
     * Update user profile information.
     */
    public static function update(
        object $auth,
        int $userId,
        string $name,
        string $email
    ): bool {
        $db = self::db($auth);

        $stmt = $db->prepare(
            'UPDATE users
             SET
                name = :name,
                email = :email
             WHERE id = :id'
        );

        return $stmt->execute([
            ':name' => $name,
            ':email' => $email,
            ':id' => $userId
        ]);
    }

    /**
     * Update user account status.
     */
    public static function updateStatus(
        object $auth,
        int $userId,
        string $status
    ): bool {
        $db = self::db($auth);

        $stmt = $db->prepare(
            'UPDATE users
             SET status = :status
             WHERE id = :id'
        );

        return $stmt->execute([
            ':status' => $status,
            ':id' => $userId
        ]);
    }

    /**
     * Find a role ID by role name.
     */
    public static function findRoleIdByName(
        object $auth,
        string $roleName
    ): ?int {
        $db = self::db($auth);

        $stmt = $db->prepare(
            'SELECT id
             FROM roles
             WHERE name = :name
             LIMIT 1'
        );

        $stmt->execute([
            ':name' => $roleName
        ]);

        $result = $stmt->fetch();

        return $result
            ? (int) $result['id']
            : null;
    }

    /**
     * Assign a role to a user.
     */
    public static function assignRole(
        object $auth,
        int $userId,
        int $roleId
    ): bool {
        $db = self::db($auth);

        $stmt = $db->prepare(
            'INSERT INTO user_roles
                (user_id, role_id)
             VALUES
                (:user_id, :role_id)'
        );

        return $stmt->execute([
            ':user_id' => $userId,
            ':role_id' => $roleId
        ]);
    }

    /**
     * Remove all roles assigned to a user.
     */
    public static function removeRoles(
        object $auth,
        int $userId
    ): bool {
        $db = self::db($auth);

        $stmt = $db->prepare(
            'DELETE FROM user_roles
             WHERE user_id = :user_id'
        );

        return $stmt->execute([
            ':user_id' => $userId
        ]);
    }

    /**
     * Get all roles assigned to a user.
     */
    public static function getRoles(
        object $auth,
        int $userId
    ): array {
        $db = self::db($auth);

        $stmt = $db->prepare(
            'SELECT r.name
             FROM roles r
             INNER JOIN user_roles ur
                ON ur.role_id = r.id
             WHERE ur.user_id = :user_id
             ORDER BY r.name'
        );

        $stmt->execute([
            ':user_id' => $userId
        ]);

        return array_column(
            $stmt->fetchAll(),
            'name'
        );
    }

    /**
     * Update a user's password.
     */
    public static function updatePassword(
        object $auth,
        int $userId,
        string $passwordHash
    ): bool {
        $db = self::db($auth);

        $stmt = $db->prepare(
            'UPDATE users
             SET password_hash = :password_hash
             WHERE id = :id'
        );

        return $stmt->execute([
            ':password_hash' => $passwordHash,
            ':id' => $userId
        ]);
    }

    /**
     * Revoke all refresh tokens for a user.
     */
    public static function revokeRefreshTokensByUser(
        object $auth,
        int $userId
    ): bool {
        $db = self::db($auth);

        $stmt = $db->prepare(
            'UPDATE refresh_tokens
         SET revoked = TRUE
         WHERE user_id = :user_id
           AND revoked = FALSE'
        );

        return $stmt->execute([
            ':user_id' => $userId
        ]);
    }
}
