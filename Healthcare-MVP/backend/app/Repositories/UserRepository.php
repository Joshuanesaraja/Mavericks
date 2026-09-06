<?php

require_once __DIR__ . '/../Config/database.php';

class UserRepository
{
    public static function findById(
        int $userId,
        int $tenantId
    ): ?array {
        $pdo = Database::connect();

        $stmt = $pdo->prepare("
            SELECT
                u.id,
                u.tenant_id,
                u.name,
                u.email,
                u.status,
                u.created_at,
                u.updated_at,
                GROUP_CONCAT(
                    r.name
                    ORDER BY r.name
                    SEPARATOR ','
                ) AS roles
            FROM users u
            LEFT JOIN user_roles ur
                ON u.id = ur.user_id
            LEFT JOIN roles r
                ON ur.role_id = r.id
            WHERE u.id = :user_id
              AND u.tenant_id = :tenant_id
            GROUP BY
                u.id,
                u.tenant_id,
                u.name,
                u.email,
                u.status,
                u.created_at,
                u.updated_at
            LIMIT 1
        ");

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

    public static function findAllByTenant(
        int $tenantId
    ): array {
        $pdo = Database::connect();

        $stmt = $pdo->prepare("
            SELECT
                u.id,
                u.tenant_id,
                u.name,
                u.email,
                u.status,
                u.created_at,
                u.updated_at,
                GROUP_CONCAT(
                    r.name
                    ORDER BY r.name
                    SEPARATOR ','
                ) AS roles
            FROM users u
            LEFT JOIN user_roles ur
                ON u.id = ur.user_id
            LEFT JOIN roles r
                ON ur.role_id = r.id
            WHERE u.tenant_id = :tenant_id
            GROUP BY
                u.id,
                u.tenant_id,
                u.name,
                u.email,
                u.status,
                u.created_at,
                u.updated_at
            ORDER BY u.id DESC
        ");

        $stmt->execute([
            'tenant_id' => $tenantId
        ]);

        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($users as &$user) {
            $user['roles'] = $user['roles']
                ? explode(',', $user['roles'])
                : [];
        }

        unset($user);

        return $users;
    }

    public static function findByEmail(
        string $email
    ): ?array {
        $pdo = Database::connect();

        $stmt = $pdo->prepare("
            SELECT
                id,
                tenant_id,
                name,
                email,
                password_hash,
                status
            FROM users
            WHERE email = :email
            LIMIT 1
        ");

        $stmt->execute([
            'email' => $email
        ]);

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        return $user ?: null;
    }

    public static function findPasswordHash(
        int $userId,
        int $tenantId
    ): ?string {
        $pdo = Database::connect();

        $stmt = $pdo->prepare("
            SELECT password_hash
            FROM users
            WHERE id = :user_id
              AND tenant_id = :tenant_id
            LIMIT 1
        ");

        $stmt->execute([
            'user_id' => $userId,
            'tenant_id' => $tenantId
        ]);

        $hash = $stmt->fetchColumn();

        return $hash ?: null;
    }

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

    public static function update(
        int $userId,
        int $tenantId,
        string $name,
        string $email
    ): bool {
        $pdo = Database::connect();

        $stmt = $pdo->prepare("
            UPDATE users
            SET
                name = :name,
                email = :email,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :user_id
              AND tenant_id = :tenant_id
        ");

        $stmt->execute([
            'user_id' => $userId,
            'tenant_id' => $tenantId,
            'name' => $name,
            'email' => $email
        ]);

        return $stmt->rowCount() > 0;
    }

    public static function updateStatus(
        int $userId,
        int $tenantId,
        string $status
    ): bool {
        $pdo = Database::connect();

        $stmt = $pdo->prepare("
            UPDATE users
            SET
                status = :status,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :user_id
              AND tenant_id = :tenant_id
        ");

        $stmt->execute([
            'user_id' => $userId,
            'tenant_id' => $tenantId,
            'status' => $status
        ]);

        return $stmt->rowCount() > 0;
    }

    public static function findRoleIdByName(
        string $roleName
    ): ?int {
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

        return $roleId !== false
            ? (int) $roleId
            : null;
    }

    public static function assignRole(
        int $userId,
        int $roleId
    ): bool {
        $pdo = Database::connect();

        $stmt = $pdo->prepare("
            INSERT INTO user_roles (
                user_id,
                role_id
            )
            VALUES (
                :user_id,
                :role_id
            )
        ");

        return $stmt->execute([
            'user_id' => $userId,
            'role_id' => $roleId
        ]);
    }

    public static function removeRoles(
        int $userId,
        int $tenantId
    ): bool {
        $pdo = Database::connect();

        $stmt = $pdo->prepare("
            DELETE ur
            FROM user_roles ur
            INNER JOIN users u
                ON u.id = ur.user_id
            WHERE ur.user_id = :user_id
              AND u.tenant_id = :tenant_id
        ");

        return $stmt->execute([
            'user_id' => $userId,
            'tenant_id' => $tenantId
        ]);
    }

    public static function updatePassword(
        int $userId,
        int $tenantId,
        string $passwordHash
    ): bool {
        $pdo = Database::connect();

        $stmt = $pdo->prepare("
            UPDATE users
            SET
                password_hash = :password_hash,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :user_id
              AND tenant_id = :tenant_id
        ");

        $stmt->execute([
            'user_id' => $userId,
            'tenant_id' => $tenantId,
            'password_hash' => $passwordHash
        ]);

        return $stmt->rowCount() > 0;
    }

    public static function revokeRefreshTokensByUser(
        int $userId,
        int $tenantId
    ): bool {
        $pdo = Database::connect();

        $stmt = $pdo->prepare("
            UPDATE refresh_tokens
            SET revoked = TRUE
            WHERE user_id = :user_id
              AND tenant_id = :tenant_id
              AND revoked = FALSE
        ");

        return $stmt->execute([
            'user_id' => $userId,
            'tenant_id' => $tenantId
        ]);
    }
}