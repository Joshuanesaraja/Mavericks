<?php

require_once __DIR__ . '/../Config/database.php';

class AppointmentRepository
{
    /**
     * Check if a provider has an overlapping appointment within the given tenant.
     * Overlap condition: (existing.start_at < new.end_at AND existing.end_at > new.start_at)
     */
    public static function hasOverlappingAppointment(
        int $tenantId,
        int $providerId,
        string $startAt,
        string $endAt,
        ?int $excludeId = null
    ): bool {
        $db = Database::connect();

        $sql = 'SELECT COUNT(*) FROM appointments
        WHERE tenant_id = :tenant_id
          AND provider_id = :provider_id
          AND status NOT IN (''cancelled'', ''completed'')
          AND start_at < :end_at
          AND end_at > :start_at';

        $params = [
            'tenant_id'   => $tenantId,
            'provider_id' => $providerId,
            'start_at'    => $startAt,
            'end_at'      => $endAt,
        ];

        if ($excludeId !== null) {
            $sql .= ' AND id != :exclude_id';
            $params['exclude_id'] = $excludeId;
        }

        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Create a new appointment record.
     */
    public static function create(array $data): int
    {
        $db = Database::connect();

        $sql = 'INSERT INTO appointments
                (tenant_id, patient_id, provider_id, start_at, end_at, status, reason)
                VALUES
                (:tenant_id, :patient_id, :provider_id, :start_at, :end_at, :status, :reason)';

        $stmt = $db->prepare($sql);
        $stmt->execute([
            'tenant_id'   => $data['tenant_id'],
            'patient_id'  => $data['patient_id'],
            'provider_id' => $data['provider_id'],
            'start_at'    => $data['start_at'],
            'end_at'      => $data['end_at'],
            'status'      => $data['status'] ?? 'scheduled',
            'reason'      => $data['reason'] ?? null,
        ]);

        return (int) $db->lastInsertId();
    }

    /**
     * Find appointment by ID and tenant ID.
     */
    public static function findById(int $id, int $tenantId): ?array
    {
        $db = Database::connect();

        $sql = 'SELECT a.*,
                       pu.name AS patient_name, pu.email AS patient_email,
                       du.name AS provider_name, du.email AS provider_email
                FROM appointments a
                LEFT JOIN users pu ON pu.id = a.patient_id
                LEFT JOIN users du ON du.id = a.provider_id
                WHERE a.id = :id AND a.tenant_id = :tenant_id
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
     * Update appointment details.
     */
    public static function update(int $id, int $tenantId, array $data): bool
    {
        $db = Database::connect();

        $fields = [];
        $params = [
            'id'        => $id,
            'tenant_id' => $tenantId,
        ];

        if (array_key_exists('patient_id', $data)) {
            $fields[] = 'patient_id = :patient_id';
            $params['patient_id'] = $data['patient_id'];
        }
        if (array_key_exists('provider_id', $data)) {
            $fields[] = 'provider_id = :provider_id';
            $params['provider_id'] = $data['provider_id'];
        }
        if (array_key_exists('start_at', $data)) {
            $fields[] = 'start_at = :start_at';
            $params['start_at'] = $data['start_at'];
        }
        if (array_key_exists('end_at', $data)) {
            $fields[] = 'end_at = :end_at';
            $params['end_at'] = $data['end_at'];
        }
        if (array_key_exists('status', $data)) {
            $fields[] = 'status = :status';
            $params['status'] = $data['status'];
            if ($data['status'] === 'cancelled') {
                $fields[] = 'cancelled_at = NOW()';
            }
        }
        if (array_key_exists('reason', $data)) {
            $fields[] = 'reason = :reason';
            $params['reason'] = $data['reason'];
        }

        if (empty($fields)) {
            return false;
        }

        $sql = 'UPDATE appointments SET ' . implode(', ', $fields) . ' WHERE id = :id AND tenant_id = :tenant_id';
        $stmt = $db->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Update appointment status.
     */
    public static function updateStatus(int $id, int $tenantId, string $status): bool
    {
        $db = Database::connect();

        if ($status === 'cancelled') {
            $sql = 'UPDATE appointments SET status = :status, cancelled_at = NOW() WHERE id = :id AND tenant_id = :tenant_id';
        } else {
            $sql = 'UPDATE appointments SET status = :status WHERE id = :id AND tenant_id = :tenant_id';
        }

        $stmt = $db->prepare($sql);
        return $stmt->execute([
            'id'        => $id,
            'tenant_id' => $tenantId,
            'status'    => $status,
        ]);
    }

    /**
     * Cancel an appointment with optional reason update.
     */
    public static function cancel(int $id, int $tenantId, ?string $reason = null): bool
    {
        $db = Database::connect();

        if ($reason !== null) {
            $sql = 'UPDATE appointments
                    SET status = "cancelled", cancelled_at = NOW(), reason = :reason
                    WHERE id = :id AND tenant_id = :tenant_id';
            $stmt = $db->prepare($sql);
            return $stmt->execute([
                'id'        => $id,
                'tenant_id' => $tenantId,
                'reason'    => $reason,
            ]);
        }

        $sql = 'UPDATE appointments
                SET status = "cancelled", cancelled_at = NOW()
                WHERE id = :id AND tenant_id = :tenant_id';
        $stmt = $db->prepare($sql);
        return $stmt->execute([
            'id'        => $id,
            'tenant_id' => $tenantId,
        ]);
    }

    /**
     * Get upcoming appointments starting from current time.
     */
    public static function getUpcoming(
        int $tenantId,
        ?int $patientId = null,
        ?int $providerId = null
    ): array {
        $db = Database::connect();

        $sql = 'SELECT a.*,
                       pu.name AS patient_name, pu.email AS patient_email,
                       du.name AS provider_name, du.email AS provider_email
                FROM appointments a
                LEFT JOIN users pu ON pu.id = a.patient_id
                LEFT JOIN users du ON du.id = a.provider_id
                WHERE a.tenant_id = :tenant_id
                  AND a.start_at >= NOW()
                  AND a.status != "cancelled"';

        $params = ['tenant_id' => $tenantId];

        if ($patientId !== null) {
            $sql .= ' AND a.patient_id = :patient_id';
            $params['patient_id'] = $patientId;
        }

        if ($providerId !== null) {
            $sql .= ' AND a.provider_id = :provider_id';
            $params['provider_id'] = $providerId;
        }

        $sql .= ' ORDER BY a.start_at ASC';

        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * List appointments with flexible filtering options.
     */
    public static function listAll(int $tenantId, array $filters = []): array
    {
        $db = Database::connect();

        $sql = 'SELECT a.*,
                       pu.name AS patient_name, pu.email AS patient_email,
                       du.name AS provider_name, du.email AS provider_email
                FROM appointments a
                LEFT JOIN users pu ON pu.id = a.patient_id
                LEFT JOIN users du ON du.id = a.provider_id
                WHERE a.tenant_id = :tenant_id';

        $params = ['tenant_id' => $tenantId];

        if (!empty($filters['patient_id'])) {
            $sql .= ' AND a.patient_id = :patient_id';
            $params['patient_id'] = (int) $filters['patient_id'];
        }

        if (!empty($filters['provider_id'])) {
            $sql .= ' AND a.provider_id = :provider_id';
            $params['provider_id'] = (int) $filters['provider_id'];
        }

        if (!empty($filters['status'])) {
            $sql .= ' AND a.status = :status';
            $params['status'] = $filters['status'];
        }

        if (!empty($filters['date_from'])) {
            $sql .= ' AND a.start_at >= :date_from';
            $params['date_from'] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $sql .= ' AND a.end_at <= :date_to';
            $params['date_to'] = $filters['date_to'];
        }

        $sql .= ' ORDER BY a.start_at DESC';

        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
