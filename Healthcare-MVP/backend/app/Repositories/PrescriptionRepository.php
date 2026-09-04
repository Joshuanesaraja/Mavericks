<?php

require_once __DIR__ . '/../Config/database.php';

class PrescriptionRepository
{
    /**
     * Create a new prescription record.
     */
    public static function create(array $data): int
    {
        $db = Database::connect();

        $sql = 'INSERT INTO prescriptions
                (tenant_id, patient_id, provider_id, pharmacist_id, encrypted_data, status)
                VALUES
                (:tenant_id, :patient_id, :provider_id, :pharmacist_id, :encrypted_data, :status)';

        $stmt = $db->prepare($sql);
        $stmt->execute([
            'tenant_id'      => $data['tenant_id'],
            'patient_id'     => $data['patient_id'],
            'provider_id'    => $data['provider_id'],
            'pharmacist_id'  => $data['pharmacist_id'] ?? null,
            'encrypted_data' => $data['encrypted_data'],
            'status'         => $data['status'] ?? 'pending',
        ]);

        return (int) $db->lastInsertId();
    }

    /**
     * Find prescription by ID and tenant ID.
     */
    public static function findById(int $id, int $tenantId): ?array
    {
        $db = Database::connect();

        $sql = 'SELECT p.*,
                       pu.name AS patient_name, pu.email AS patient_email,
                       du.name AS provider_name, du.email AS provider_email,
                       ph.name AS pharmacist_name, ph.email AS pharmacist_email
                FROM prescriptions p
                LEFT JOIN users pu ON pu.id = p.patient_id
                LEFT JOIN users du ON du.id = p.provider_id
                LEFT JOIN users ph ON ph.id = p.pharmacist_id
                WHERE p.id = :id AND p.tenant_id = :tenant_id
                LIMIT 1';

        $stmt = $db->prepare($sql);
        $stmt->execute([
            'id'        => $id,
            'tenant_id' => $tenantId,
        ]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Update prescription status and record verifying pharmacist.
     */
    public static function updateStatus(
        int $id,
        int $tenantId,
        ?int $pharmacistId,
        string $status
    ): bool {
        $db = Database::connect();

        if ($pharmacistId !== null) {
            $sql = 'UPDATE prescriptions
                    SET status = :status, pharmacist_id = :pharmacist_id
                    WHERE id = :id AND tenant_id = :tenant_id';
            $params = [
                'id'            => $id,
                'tenant_id'     => $tenantId,
                'pharmacist_id' => $pharmacistId,
                'status'        => $status,
            ];
        } else {
            $sql = 'UPDATE prescriptions
                    SET status = :status
                    WHERE id = :id AND tenant_id = :tenant_id';
            $params = [
                'id'        => $id,
                'tenant_id' => $tenantId,
                'status'    => $status,
            ];
        }

        $stmt = $db->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * List prescriptions with filters.
     */
    public static function listAll(int $tenantId, array $filters = []): array
    {
        $db = Database::connect();

        $sql = 'SELECT p.*,
                       pu.name AS patient_name, pu.email AS patient_email,
                       du.name AS provider_name, du.email AS provider_email,
                       ph.name AS pharmacist_name
                FROM prescriptions p
                LEFT JOIN users pu ON pu.id = p.patient_id
                LEFT JOIN users du ON du.id = p.provider_id
                LEFT JOIN users ph ON ph.id = p.pharmacist_id
                WHERE p.tenant_id = :tenant_id';

        $params = ['tenant_id' => $tenantId];

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
