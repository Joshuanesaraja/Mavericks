<?php

require_once __DIR__ . '/../Config/database.php';

class UserRepository
{
    public static function findById(
        int $userId,
        int $tenantId
    ): ?array {
        $db = Database::connect();

        $stmt = $db->prepare(
            'SELECT id, tenant_id, name, email, status
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

        return $user ?: null;
    }

    // for password change : We only fetch the hashed password, not the whole user record.

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

    // The authenticated user can update only their own tenant's account.

    public static function updatePassword(
        int $userId,
        int $tenantId,
        string $passwordHash
    ): bool {

        $db = Database::connect();

        $stmt = $db->prepare(
            'UPDATE users
         SET password_hash = ?
         WHERE id = ?
         AND tenant_id = ?'
        );

        return $stmt->execute([
            $passwordHash,
            $userId,
            $tenantId
        ]);
    }
}
