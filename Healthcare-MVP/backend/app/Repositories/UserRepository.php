<?php

require_once __DIR__ . '/../Config/database.php';

class UserRepository
{
    // Get a single user belonging to the tenant.
    public static function findById(
        int $userId,
        int $tenantId
    ): ?array {
        $db = Database::connect();

        $stmt = $db->prepare(
            'SELECT
                u.id,
                u.tenant_id,
                u.name,
                u.email,
                u.status,
                u.created_at,
                u.updated_at
             FROM users u
             WHERE u.id = ?
               AND u.tenant_id = ?
             LIMIT 1'
        );

        $stmt->execute([
            $userId,
            $tenantId
        ]);

        $user = $stmt->fetch();

        return $user ?: null;
    }

    // Get all users belonging to the tenant.
    public static function findAllByTenant(
        int $tenantId
    ): array {
        $db = Database::connect();

        $stmt = $db->prepare(
            'SELECT
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
                    SEPARATOR ","
                ) AS roles
             FROM users u
             LEFT JOIN user_roles ur
                ON ur.user_id = u.id
             LEFT JOIN roles r
                ON r.id = ur.role_id
             WHERE u.tenant_id = ?
             GROUP BY u.id
             ORDER BY u.id DESC'
        );

        $stmt->execute([
            $tenantId
        ]);

        $users = $stmt->fetchAll();

        foreach ($users as &$user) {
            $user['roles'] = !empty($user['roles'])
                ? explode(',', $user['roles'])
                : [];
        }

        return $users;
    }

    // Get password hash for password change.
    public static function findPasswordHash(
        int $userId,
        int $tenantId
    ): ?string {
        $db = Database::connect();

        $stmt = $db->prepare(
            'SELECT password_hash
             FROM users
             WHERE id = ?
               AND tenant_id = ?
             LIMIT 1'
        );

        $stmt->execute([
            $userId,
            $tenantId
        ]);

        $user = $stmt->fetch();

        return $user['password_hash'] ?? null;
    }

    // Update password for the authenticated user's tenant.
    public static function updatePassword(
        int $userId,
        int $tenantId,
        string $passwordHash
    ): bool {
        $db = Database::connect();

        $stmt = $db->prepare(
            'UPDATE users
             SET password_hash = ?,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = ?
               AND tenant_id = ?'
        );

        return $stmt->execute([
            $passwordHash,
            $userId,
            $tenantId
        ]);
    }

    public static function findRoleIdByName(
        string $roleName
    ): ?int {
        $db = Database::connect();

        $stmt = $db->prepare(
            'SELECT id
             FROM roles
             WHERE name = ?
             LIMIT 1'
        );

        $stmt->execute([
            $roleName
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
        $db = Database::connect();

        $stmt = $db->prepare(
            'INSERT IGNORE INTO user_roles
                (user_id, role_id)
             VALUES
                (?, ?)'
        );

        return $stmt->execute([
            $userId,
            $roleId
        ]);
    }

    public static function removeRoles(
        int $userId,
        int $tenantId
    ): bool {
        $db = Database::connect();

        $stmt = $db->prepare(
            'DELETE ur
             FROM user_roles ur
             INNER JOIN users u
                ON u.id = ur.user_id
             WHERE ur.user_id = ?
               AND u.tenant_id = ?'
        );

        return $stmt->execute([
            $userId,
            $tenantId
        ]);
    }

    public static function updateStatus(
        int $userId,
        int $tenantId,
        string $status
    ): bool {
        $db = Database::connect();

        $stmt = $db->prepare(
            'UPDATE users
             SET status = ?,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = ?
               AND tenant_id = ?'
        );

        return $stmt->execute([
            $status,
            $userId,
            $tenantId
        ]);
    }
}