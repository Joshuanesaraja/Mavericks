<?php

require_once __DIR__ . '/../Config/database.php';

class StaffRepository
{
    private PDO $db;

    public function __construct(
        PDO $db
    ) {
        $this->db = $db;
    }

    public function findAll(): array
    {
        $stmt = $this->db->query("
            SELECT
                s.id,
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
            LEFT JOIN user_roles ur
                ON ur.user_id = u.id
            LEFT JOIN roles r
                ON r.id = ur.role_id
            WHERE s.deleted_at IS NULL
            GROUP BY
                s.id,
                s.user_id,
                s.staff_type,
                s.status,
                s.created_at,
                s.updated_at,
                u.name,
                u.email
            ORDER BY s.id DESC
        ");

        $staff = $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );

        foreach ($staff as &$member) {
            $member['roles'] =
                $member['roles']
                    ? explode(
                        ',',
                        $member['roles']
                    )
                    : [];
        }

        unset($member);

        return $staff;
    }

    public function findById(
        int $staffId
    ): ?array {
        $stmt = $this->db->prepare("
            SELECT
                s.id,
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
            LEFT JOIN user_roles ur
                ON ur.user_id = u.id
            LEFT JOIN roles r
                ON r.id = ur.role_id
            WHERE s.id = :staff_id
              AND s.deleted_at IS NULL
            GROUP BY
                s.id,
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
            'staff_id' => $staffId
        ]);

        $staff = $stmt->fetch(
            PDO::FETCH_ASSOC
        );

        if (!$staff) {
            return null;
        }

        $staff['roles'] =
            $staff['roles']
                ? explode(
                    ',',
                    $staff['roles']
                )
                : [];

        return $staff;
    }

    public function findByUserId(
        int $userId
    ): ?array {
        $stmt = $this->db->prepare("
            SELECT
                id,
                user_id,
                staff_type,
                status,
                created_at,
                updated_at
            FROM staff
            WHERE user_id = :user_id
              AND deleted_at IS NULL
            LIMIT 1
        ");

        $stmt->execute([
            'user_id' => $userId
        ]);

        $staff = $stmt->fetch(
            PDO::FETCH_ASSOC
        );

        return $staff ?: null;
    }

    public function create(
        string $name,
        string $email,
        string $passwordHash,
        string $staffType,
        int $roleId
    ): int {
        try {
            $this->db->beginTransaction();

            $stmt = $this->db->prepare("
                INSERT INTO users (
                    name,
                    email,
                    password_hash,
                    status
                )
                VALUES (
                    :name,
                    :email,
                    :password_hash,
                    'active'
                )
            ");

            $stmt->execute([
                'name' => $name,
                'email' => $email,
                'password_hash' => $passwordHash
            ]);

            $userId =
                (int) $this->db->lastInsertId();

            $stmt = $this->db->prepare("
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

            $stmt = $this->db->prepare("
                INSERT INTO staff (
                    user_id,
                    staff_type,
                    status
                )
                VALUES (
                    :user_id,
                    :staff_type,
                    'active'
                )
            ");

            $stmt->execute([
                'user_id' => $userId,
                'staff_type' => $staffType
            ]);

            $staffId =
                (int) $this->db->lastInsertId();

            $this->db->commit();

            return $staffId;
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $e;
        }
    }

    public function update(
        int $staffId,
        string $name,
        string $email,
        string $staffType,
        int $roleId
    ): bool {
        try {
            $this->db->beginTransaction();

            $stmt = $this->db->prepare("
                SELECT user_id
                FROM staff
                WHERE id = :staff_id
                  AND deleted_at IS NULL
                LIMIT 1
            ");

            $stmt->execute([
                'staff_id' => $staffId
            ]);

            $userId = $stmt->fetchColumn();

            if ($userId === false) {
                $this->db->rollBack();
                return false;
            }

            $userId = (int) $userId;

            $stmt = $this->db->prepare("
                UPDATE users
                SET
                    name = :name,
                    email = :email,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :user_id
            ");

            $stmt->execute([
                'name' => $name,
                'email' => $email,
                'user_id' => $userId
            ]);

            $stmt = $this->db->prepare("
                UPDATE staff
                SET
                    staff_type = :staff_type,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :staff_id
                  AND deleted_at IS NULL
            ");

            $stmt->execute([
                'staff_type' => $staffType,
                'staff_id' => $staffId
            ]);

            $stmt = $this->db->prepare("
                DELETE FROM user_roles
                WHERE user_id = :user_id
            ");

            $stmt->execute([
                'user_id' => $userId
            ]);

            $stmt = $this->db->prepare("
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

            $this->db->commit();

            return true;
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $e;
        }
    }

    public function updateStatus(
        int $staffId,
        string $status
    ): bool {
        try {
            $this->db->beginTransaction();

            $stmt = $this->db->prepare("
                SELECT user_id
                FROM staff
                WHERE id = :staff_id
                  AND deleted_at IS NULL
                LIMIT 1
            ");

            $stmt->execute([
                'staff_id' => $staffId
            ]);

            $userId = $stmt->fetchColumn();

            if ($userId === false) {
                $this->db->rollBack();
                return false;
            }

            $userId = (int) $userId;

            $stmt = $this->db->prepare("
                UPDATE staff
                SET
                    status = :status,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :staff_id
                  AND deleted_at IS NULL
            ");

            $stmt->execute([
                'status' => $status,
                'staff_id' => $staffId
            ]);

            $stmt = $this->db->prepare("
                UPDATE users
                SET
                    status = :status,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :user_id
            ");

            $stmt->execute([
                'status' => $status,
                'user_id' => $userId
            ]);

            $this->db->commit();

            return true;
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $e;
        }
    }

    public function softDelete(
        int $staffId
    ): bool {
        try {
            $this->db->beginTransaction();

            $stmt = $this->db->prepare("
                SELECT user_id
                FROM staff
                WHERE id = :staff_id
                  AND deleted_at IS NULL
                LIMIT 1
            ");

            $stmt->execute([
                'staff_id' => $staffId
            ]);

            $userId = $stmt->fetchColumn();

            if ($userId === false) {
                $this->db->rollBack();
                return false;
            }

            $userId = (int) $userId;

            $stmt = $this->db->prepare("
                UPDATE staff
                SET
                    status = 'inactive',
                    deleted_at = CURRENT_TIMESTAMP,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :staff_id
                  AND deleted_at IS NULL
            ");

            $stmt->execute([
                'staff_id' => $staffId
            ]);

            $stmt = $this->db->prepare("
                UPDATE users
                SET
                    status = 'inactive',
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :user_id
            ");

            $stmt->execute([
                'user_id' => $userId
            ]);

            $this->db->commit();

            return true;
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $e;
        }
    }

    public function emailExists(
        string $email,
        ?int $exceptUserId = null
    ): bool {
        $sql = "
            SELECT id
            FROM users
            WHERE email = :email
        ";

        $params = [
            'email' => $email
        ];

        if ($exceptUserId !== null) {
            $sql .= "
                AND id <> :user_id
            ";

            $params['user_id'] =
                $exceptUserId;
        }

        $sql .= " LIMIT 1";

        $stmt = $this->db->prepare($sql);

        $stmt->execute($params);

        return $stmt->fetchColumn() !== false;
    }

    public function roleId(
        string $roleName
    ): ?int {
        $stmt = $this->db->prepare("
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