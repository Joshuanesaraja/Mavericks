<?php

namespace App\Repositories;

use PDO;

class UserRepository
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function findAllByTenant(int $tenantId): array
    {
        $sql = "
            SELECT
                u.id,
                u.tenant_id,
                u.email,
                u.status,
                u.created_at,
                u.updated_at,
                GROUP_CONCAT(r.name ORDER BY r.name SEPARATOR ',') AS roles
            FROM users u
            LEFT JOIN user_roles ur
                ON ur.user_id = u.id
            LEFT JOIN roles r
                ON r.id = ur.role_id
            WHERE u.tenant_id = :tenant_id
            GROUP BY u.id
            ORDER BY u.id DESC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':tenant_id' => $tenantId
        ]);

        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($users as &$user) {
            $user['roles'] = !empty($user['roles'])
                ? explode(',', $user['roles'])
                : [];
        }

        return $users;
    }

    public function findById(int $userId, int $tenantId): ?array
    {
        $sql = "
            SELECT
                u.id,
                u.tenant_id,
                u.email,
                u.status,
                u.created_at,
                u.updated_at,
                GROUP_CONCAT(r.name ORDER BY r.name SEPARATOR ',') AS roles
            FROM users u
            LEFT JOIN user_roles ur
                ON ur.user_id = u.id
            LEFT JOIN roles r
                ON r.id = ur.role_id
            WHERE u.id = :user_id
              AND u.tenant_id = :tenant_id
            GROUP BY u.id
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':user_id' => $userId,
            ':tenant_id' => $tenantId
        ]);

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            return null;
        }

        $user['roles'] = !empty($user['roles'])
            ? explode(',', $user['roles'])
            : [];

        return $user;
    }

    public function findRoleIdByName(string $roleName): ?int
    {
        $stmt = $this->db->prepare(
            "SELECT id FROM roles WHERE name = :name LIMIT 1"
        );

        $stmt->execute([
            ':name' => $roleName
        ]);

        $roleId = $stmt->fetchColumn();

        return $roleId !== false ? (int) $roleId : null;
    }

    public function assignRole(int $userId, int $roleId): bool
    {
        $stmt = $this->db->prepare(
            "INSERT INTO user_roles (user_id, role_id)
             VALUES (:user_id, :role_id)"
        );

        return $stmt->execute([
            ':user_id' => $userId,
            ':role_id' => $roleId
        ]);
    }

    public function assignRoleToUserByName(
        int $userId,
        int $tenantId,
        string $roleName
    ): bool {
        $user = $this->findById($userId, $tenantId);

        if (!$user) {
            return false;
        }

        $roleId = $this->findRoleIdByName($roleName);

        if (!$roleId) {
            return false;
        }

        $this->removeRoles($userId);

        return $this->assignRole($userId, $roleId);
    }
    
    public function removeRoles(int $userId): bool
    {
        $stmt = $this->db->prepare(
            "DELETE FROM user_roles WHERE user_id = :user_id"
        );

        return $stmt->execute([
            ':user_id' => $userId
        ]);
    }

    public function updateStatus(
        int $userId,
        int $tenantId,
        string $status
    ): bool {
        $stmt = $this->db->prepare(
            "UPDATE users
             SET status = :status,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :user_id
               AND tenant_id = :tenant_id"
        );

        return $stmt->execute([
            ':status' => $status,
            ':user_id' => $userId,
            ':tenant_id' => $tenantId
        ]);
    }
}