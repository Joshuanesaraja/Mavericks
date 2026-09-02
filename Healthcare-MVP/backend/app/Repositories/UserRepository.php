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
}