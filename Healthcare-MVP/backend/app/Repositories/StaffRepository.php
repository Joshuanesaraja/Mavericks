<?php

require_once __DIR__ . '/../Config/database.php';

class StaffRepository
{
    public function findAllByTenant(
        int $tenantId
    ): array {
        $pdo = Database::connect();

        $stmt = $pdo->prepare("
            SELECT
                s.id,
                s.tenant_id,
                s.user_id,
                s.staff_type,
                s.status,
                s.created_at,
                s.updated_at,
                u.name,
                u.email,
                GROUP_CONCAT(
                    r.name
                    ORDER BY r.name
                    SEPARATOR ','
                ) AS roles
            FROM staff s
            INNER JOIN users u
                ON u.id = s.user_id
               AND u.tenant_id = s.tenant_id
            LEFT JOIN user_roles ur
                ON ur.user_id = u.id
            LEFT JOIN roles r
                ON r.id = ur.role_id
            WHERE s.tenant_id = :tenant_id
              AND s.deleted_at IS NULL
            GROUP BY
                s.id,
                s.tenant_id,
                s.user_id,
                s.staff_type,
                s.status,
                s.created_at,
                s.updated_at,
                u.name,
                u.email
            ORDER BY s.id DESC
        ");

        $stmt->execute([
            'tenant_id' => $tenantId
        ]);

        $staff = $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );

        foreach ($staff as &$member) {
            $member['roles'] = $member['roles']
                ? explode(',', $member['roles'])
                : [];
        }

        unset($member);

        return $staff;
    }

    public function findById(
        int $staffId,
        int $tenantId
    ): ?array {
        $pdo = Database::connect();

        $stmt = $pdo->prepare("
            SELECT
                s.id,
                s.tenant_id,
                s.user_id,
                s.staff_type,
                s.status,
                s.created_at,
                s.updated_at,
                u.name,
                u.email,
                GROUP_CONCAT(
                    r.name
                    ORDER BY r.name
                    SEPARATOR ','
                ) AS roles
            FROM staff s
            INNER JOIN users u
                ON u.id = s.user_id
               AND u.tenant_id = s.tenant_id
            LEFT JOIN user_roles ur
                ON ur.user_id = u.id
            LEFT JOIN roles r
                ON r.id = ur.role_id
            WHERE s.id = :staff_id
              AND s.tenant_id = :tenant_id
              AND s.deleted_at IS NULL
            GROUP BY
                s.id,
                s.tenant_id,
                s.user_id,
                s.staff_type,
                s.status,
                s.created_at,
                s.updated_at,
                u.name,
                u.email
            LIMIT 1
        ");

        $stmt->execute([
            'staff_id' => $staffId,
            'tenant_id' => $tenantId
        ]);

        $staff = $stmt->fetch(
            PDO::FETCH_ASSOC
        );

        if (!$staff) {
            return null;
        }

        $staff['roles'] = $staff['roles']
            ? explode(',', $staff['roles'])
            : [];

        return $staff;
    }

    public function findByUserId(
        int $userId,
        int $tenantId
    ): ?array {
        $pdo = Database::connect();

        $stmt = $pdo->prepare("
            SELECT
                id,
                tenant_id,
                user_id,
                staff_type,
                status,
                created_at,
                updated_at
            FROM staff
            WHERE user_id = :user_id
              AND tenant_id = :tenant_id
              AND deleted_at IS NULL
            LIMIT 1
        ");

        $stmt->execute([
            'user_id' => $userId,
            'tenant_id' => $tenantId
        ]);

        $staff = $stmt->fetch(
            PDO::FETCH_ASSOC
        );

        return $staff ?: null;
    }

    public function create(
        int $tenantId,
        string $name,
        string $email,
        string $passwordHash,
        string $staffType,
        int $roleId
    ): int {
        $pdo = Database::connect();

        try {
            $pdo->beginTransaction();

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

            $userId = (int) $pdo->lastInsertId();

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

            $stmt->execute([
                'user_id' => $userId,
                'role_id' => $roleId
            ]);

            $stmt = $pdo->prepare("
                INSERT INTO staff (
                    tenant_id,
                    user_id,
                    staff_type,
                    status
                )
                VALUES (
                    :tenant_id,
                    :user_id,
                    :staff_type,
                    'active'
                )
            ");

            $stmt->execute([
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'staff_type' => $staffType
            ]);

            $staffId = (int) $pdo->lastInsertId();

            $pdo->commit();

            return $staffId;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $e;
        }
    }

    public function update(
        int $staffId,
        int $tenantId,
        string $name,
        string $email,
        string $staffType,
        int $roleId
    ): bool {
        $pdo = Database::connect();

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                SELECT user_id
                FROM staff
                WHERE id = :staff_id
                  AND tenant_id = :tenant_id
                  AND deleted_at IS NULL
                LIMIT 1
            ");

            $stmt->execute([
                'staff_id' => $staffId,
                'tenant_id' => $tenantId
            ]);

            $userId = $stmt->fetchColumn();

            if ($userId === false) {
                $pdo->rollBack();
                return false;
            }

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
                'name' => $name,
                'email' => $email,
                'user_id' => $userId,
                'tenant_id' => $tenantId
            ]);

            $stmt = $pdo->prepare("
                UPDATE staff
                SET
                    staff_type = :staff_type,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :staff_id
                  AND tenant_id = :tenant_id
                  AND deleted_at IS NULL
            ");

            $stmt->execute([
                'staff_type' => $staffType,
                'staff_id' => $staffId,
                'tenant_id' => $tenantId
            ]);

            $stmt = $pdo->prepare("
                DELETE ur
                FROM user_roles ur
                INNER JOIN users u
                    ON u.id = ur.user_id
                WHERE ur.user_id = :user_id
                  AND u.tenant_id = :tenant_id
            ");

            $stmt->execute([
                'user_id' => $userId,
                'tenant_id' => $tenantId
            ]);

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

            $stmt->execute([
                'user_id' => $userId,
                'role_id' => $roleId
            ]);

            $pdo->commit();

            return true;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $e;
        }
    }

    public function updateStatus(
        int $staffId,
        int $tenantId,
        string $status
    ): bool {
        $pdo = Database::connect();

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                SELECT user_id
                FROM staff
                WHERE id = :staff_id
                  AND tenant_id = :tenant_id
                  AND deleted_at IS NULL
                LIMIT 1
            ");

            $stmt->execute([
                'staff_id' => $staffId,
                'tenant_id' => $tenantId
            ]);

            $userId = $stmt->fetchColumn();

            if ($userId === false) {
                $pdo->rollBack();
                return false;
            }

            $stmt = $pdo->prepare("
                UPDATE staff
                SET
                    status = :status,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :staff_id
                  AND tenant_id = :tenant_id
                  AND deleted_at IS NULL
            ");

            $stmt->execute([
                'status' => $status,
                'staff_id' => $staffId,
                'tenant_id' => $tenantId
            ]);

            $stmt = $pdo->prepare("
                UPDATE users
                SET
                    status = :status,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :user_id
                  AND tenant_id = :tenant_id
            ");

            $stmt->execute([
                'status' => $status,
                'user_id' => $userId,
                'tenant_id' => $tenantId
            ]);

            $pdo->commit();

            return true;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $e;
        }
    }

    public function softDelete(
        int $staffId,
        int $tenantId
    ): bool {
        $pdo = Database::connect();

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                SELECT user_id
                FROM staff
                WHERE id = :staff_id
                  AND tenant_id = :tenant_id
                  AND deleted_at IS NULL
                LIMIT 1
            ");

            $stmt->execute([
                'staff_id' => $staffId,
                'tenant_id' => $tenantId
            ]);

            $userId = $stmt->fetchColumn();

            if ($userId === false) {
                $pdo->rollBack();
                return false;
            }

            $stmt = $pdo->prepare("
                UPDATE staff
                SET
                    status = 'inactive',
                    deleted_at = CURRENT_TIMESTAMP,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :staff_id
                  AND tenant_id = :tenant_id
                  AND deleted_at IS NULL
            ");

            $stmt->execute([
                'staff_id' => $staffId,
                'tenant_id' => $tenantId
            ]);

            $stmt = $pdo->prepare("
                UPDATE users
                SET
                    status = 'inactive',
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :user_id
                  AND tenant_id = :tenant_id
            ");

            $stmt->execute([
                'user_id' => $userId,
                'tenant_id' => $tenantId
            ]);

            $pdo->commit();

            return true;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $e;
        }
    }

    public function emailExists(
        string $email,
        ?int $exceptUserId = null
    ): bool {
        $pdo = Database::connect();

        $sql = "
            SELECT id
            FROM users
            WHERE email = :email
        ";

        $params = [
            'email' => $email
        ];

        if ($exceptUserId !== null) {
            $sql .= " AND id <> :user_id";
            $params['user_id'] = $exceptUserId;
        }

        $sql .= " LIMIT 1";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchColumn() !== false;
    }

    public function roleId(
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

        $id = $stmt->fetchColumn();

        return $id === false
            ? null
            : (int) $id;
    }
}