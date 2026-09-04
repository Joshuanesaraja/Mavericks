<?php

require_once __DIR__ . '/../Config/database.php';

class UserRepository
{
    // Find user by ID within tenant
    public static function findById(int $userId, int $tenantId): ?array
    {
        $pdo = Database::connect();

        $sql = "
            SELECT
                u.id,
                u.tenant_id,
                u.name,
                u.email,
                u.status,
                u.created_at,
                u.updated_at,
                GROUP_CONCAT(r.name ORDER BY r.name SEPARATOR ',') AS roles
            FROM users u
            LEFT JOIN user_roles ur ON u.id = ur.user_id
            LEFT JOIN roles r ON ur.role_id = r.id
            WHERE u.id = :user_id
              AND u.tenant_id = :tenant_id
            GROUP BY u.id
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'user_id' => $userId,
            'tenant_id' => $tenantId
        ]);

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            return null;
        }

        $user['roles'] = $user['roles']
            ? explode(',', $user['roles'])
            : [];

        return $user;
    }

    // Get all users in tenant
    public static function findAllByTenant(int $tenantId): array
    {
        $pdo = Database::connect();

        $sql = "
            SELECT
                u.id,
                u.tenant_id,
                u.name,
                u.email,
                u.status,
                u.created_at,
                u.updated_at,
                GROUP_CONCAT(r.name ORDER BY r.name SEPARATOR ',') AS roles
            FROM users u
            LEFT JOIN user_roles ur ON u.id = ur.user_id
            LEFT JOIN roles r ON ur.role_id = r.id
            WHERE u.tenant_id = :tenant_id
            GROUP BY u.id
            ORDER BY u.id DESC
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'tenant_id' => $tenantId
        ]);

        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($users as &$user) {
            $user['roles'] = $user['roles']
                ? explode(',', $user['roles'])
                : [];
        }

        return $users;
    }

    // Find user by email
    public static function findByEmail(string $email): ?array
    {
        $pdo = Database::connect();

        $sql = "
            SELECT id, tenant_id, name, email, password_hash, status
            FROM users
            WHERE email = :email
            LIMIT 1
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'email' => $email
        ]);

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        return $user ?: null;
    }

    // Get password hash
    public static function findPasswordHash(int $userId): ?string
    {
        $pdo = Database::connect();

        $stmt = $pdo->prepare("
            SELECT password_hash
            FROM users
            WHERE id = :user_id
            LIMIT 1
        ");

        $stmt->execute([
            'user_id' => $userId
        ]);

        $hash = $stmt->fetchColumn();

        return $hash ?: null;
    }

    // Create user
    public static function create(
        int $tenantId,
        string $name,
        string $email,
        string $passwordHash
    ): int {
        $pdo = Database::connect();

        $stmt = $pdo->prepare("
            INSERT INTO users (
                tenant_id,
                name,
                email,
                password_hash,
                status
            )
            VALUES (
                :tenant_id,
                :name,
                :email,
                :password_hash,
                'active'
            )
        ");

        $stmt->execute([
            'tenant_id' => $tenantId,
            'name' => $name,
            'email' => $email,
            'password_hash' => $passwordHash
        ]);

        return (int) $pdo->lastInsertId();
    }

    // Update user details
    public static function update(
        int $userId,
        int $tenantId,
        string $name,
        string $email
    ): bool {
        $pdo = Database::connect();

        $stmt = $pdo->prepare("
            UPDATE users
            SET name = :name,
                email = :email,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :user_id
              AND tenant_id = :tenant_id
        ");

        return $stmt->execute([
            'user_id' => $userId,
            'tenant_id' => $tenantId,
            'name' => $name,
            'email' => $email
        ]);
    }

    // Find role ID
    public static function findRoleIdByName(string $roleName): ?int
    {
        $pdo = Database::connect();

        $stmt = $pdo->prepare("
            SELECT id
            FROM roles
            WHERE name = :name
            LIMIT 1
        ");

        $stmt->execute([
            'name' => $roleName
        ]);

        $roleId = $stmt->fetchColumn();

        return $roleId !== false ? (int) $roleId : null;
    }

    // Assign role
    public static function assignRole(int $userId, int $roleId): bool
    {
        $pdo = Database::connect();

        $stmt = $pdo->prepare("
            INSERT INTO user_roles (user_id, role_id)
            VALUES (:user_id, :role_id)
        ");

        return $stmt->execute([
            'user_id' => $userId,
            'role_id' => $roleId
        ]);
    }

    // Remove current roles
    public static function removeRoles(int $userId, int $tenantId): bool
    {
        $pdo = Database::connect();

        $stmt = $pdo->prepare("
            DELETE ur
            FROM user_roles ur
            INNER JOIN users u ON u.id = ur.user_id
            WHERE ur.user_id = :user_id
              AND u.tenant_id = :tenant_id
        ");

        return $stmt->execute([
            'user_id' => $userId,
            'tenant_id' => $tenantId
        ]);
    }

    // Update user status
    public static function updateStatus(
        int $userId,
        int $tenantId,
        string $status
    ): bool {
        $pdo = Database::connect();

        $stmt = $pdo->prepare("
            UPDATE users
            SET status = :status,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :user_id
              AND tenant_id = :tenant_id
        ");

        return $stmt->execute([
            'user_id' => $userId,
            'tenant_id' => $tenantId,
            'status' => $status
        ]);
    }

    // Change password
    public static function updatePassword(
        int $userId,
        string $passwordHash
    ): bool {
        $pdo = Database::connect();

        $stmt = $pdo->prepare("
            UPDATE users
            SET password_hash = :password_hash,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :user_id
        ");

        return $stmt->execute([
            'user_id' => $userId,
            'password_hash' => $passwordHash
        ]);
    }
}