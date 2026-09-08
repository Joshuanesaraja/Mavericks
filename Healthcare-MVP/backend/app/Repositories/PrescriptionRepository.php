<?php

require_once __DIR__ . '/../Config/database.php';

class PrescriptionRepository
{
    /**
     * Get dynamic tenant database connection.
     */
    private static function db(object|array $auth): PDO
    {
        if (is_object($auth)) {
            $dbName = $auth->tenant_db_name ?? null;
            $dbUser = $auth->tenant_db_user ?? null;
            $dbPass = $auth->tenant_db_password ?? null;
        } else {
            $dbName = $auth['tenant_db_name'] ?? null;
            $dbUser = $auth['tenant_db_user'] ?? null;
            $dbPass = $auth['tenant_db_password'] ?? null;
        }

        if (!empty($dbName)) {
            return Database::tenant((string) $dbName, $dbUser, $dbPass);
        }

        return Database::connect();
    }

    /**
     * Create a new prescription record in tenant database.
     */
    public static function create(object|array $auth, array $data): int
    {
        $db = self::db($auth);

        $sql = 'INSERT INTO prescriptions
                (patient_id, provider_id, pharmacist_id, encrypted_data, status)
                VALUES
                (:patient_id, :provider_id, :pharmacist_id, :encrypted_data, :status)';

        $stmt = $db->prepare($sql);
        $stmt->execute([
            'patient_id'      => $data['patient_id'],
            'provider_id'     => $data['provider_id'],
            'pharmacist_id'   => $data['pharmacist_id'] ?? null,
            'encrypted_data'  => $data['encrypted_data'],
            'status'          => $data['status'] ?? 'pending',
        ]);

        return (int) $db->lastInsertId();
    }

    /**
     * Find prescription by ID in tenant database.
     */
    public static function findById(object|array $auth, int $id): ?array
    {
        $db = self::db($auth);

        $sql = 'SELECT p.*,
                       pu.name AS patient_name, pu.email AS patient_email,
                       du.name AS provider_name, du.email AS provider_email,
                       ph.name AS pharmacist_name, ph.email AS pharmacist_email
                FROM prescriptions p
                LEFT JOIN users pu ON pu.id = p.patient_id
                LEFT JOIN users du ON du.id = p.provider_id
                LEFT JOIN users ph ON ph.id = p.pharmacist_id
                WHERE p.id = :id
                LIMIT 1';

        $stmt = $db->prepare($sql);
        $stmt->execute(['id' => $id]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Update prescription status and record verifying pharmacist.
     */
    public static function updateStatus(
        object|array $auth,
        int $id,
        ?int $pharmacistId,
        string $status
    ): bool {
        $db = self::db($auth);

        if ($pharmacistId !== null) {
            $sql = 'UPDATE prescriptions
                    SET status = :status, pharmacist_id = :pharmacist_id
                    WHERE id = :id';
            $params = [
                'id'            => $id,
                'pharmacist_id' => $pharmacistId,
                'status'        => $status,
            ];
        } else {
            $sql = 'UPDATE prescriptions
                    SET status = :status
                    WHERE id = :id';
            $params = [
                'id'     => $id,
                'status' => $status,
            ];
        }

        $stmt = $db->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * List prescriptions with filters.
     */
    public static function listAll(object|array $auth, array $filters = []): array
    {
        $db = self::db($auth);

        $sql = 'SELECT p.*,
                       pu.name AS patient_name, pu.email AS patient_email,
                       du.name AS provider_name, du.email AS provider_email,
                       ph.name AS pharmacist_name
                FROM prescriptions p
                LEFT JOIN users pu ON pu.id = p.patient_id
                LEFT JOIN users du ON du.id = p.provider_id
                LEFT JOIN users ph ON ph.id = p.pharmacist_id
                WHERE 1=1';

        $params = [];

        if (!empty($filters['patient_id'])) {
            $sql .= ' AND p.patient_id = :patient_id';
            $params['patient_id'] = (int) $filters['patient_id'];
        }

        if (!empty($filters['provider_id'])) {
            $sql .= ' AND p.provider_id = :provider_id';
            $params['provider_id'] = (int) $filters['provider_id'];
        }

        if (!empty($filters['status'])) {
            $sql .= ' AND p.status = :status';
            $params['status'] = $filters['status'];
        }

        $sql .= ' ORDER BY p.created_at DESC';

        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
