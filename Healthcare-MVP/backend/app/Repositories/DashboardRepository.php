<?php

namespace App\Repositories;

use PDO;

class DashboardRepository
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Get total patients for the current tenant.
     *
     * If providerId is supplied, only patients who have
     * appointments or prescriptions with that provider
     * are counted.
     */
    public function getTotalPatients(
        ?int $providerId = null
    ): int {
        if ($providerId !== null) {
            $sql = "
                SELECT COUNT(*) AS total_patients
                FROM patients p
                WHERE p.deleted_at IS NULL
                  AND (
                      EXISTS (
                          SELECT 1
                          FROM appointments a
                          WHERE a.patient_id = p.id
                            AND a.provider_id = :appointment_provider_id
                      )
                      OR EXISTS (
                          SELECT 1
                          FROM prescriptions pr
                          WHERE pr.patient_id = p.id
                            AND pr.provider_id = :prescription_provider_id
                      )
                  )
            ";

            $stmt = $this->db->prepare($sql);

            $stmt->execute([
                ':appointment_provider_id' =>
                    $providerId,
                ':prescription_provider_id' =>
                    $providerId
            ]);

            return (int) $stmt->fetchColumn();
        }

        $stmt = $this->db->query("
            SELECT COUNT(*) AS total_patients
            FROM patients
            WHERE deleted_at IS NULL
        ");

        return (int) $stmt->fetchColumn();
    }

    /**
     * Get appointment statistics.
     *
     * Admin users see all appointments in the
     * current tenant database.
     *
     * Provider users see their own appointments.
     */
    public function getAppointmentStatistics(
        ?int $providerId = null
    ): array {
        $sql = "
            SELECT
                COUNT(*) AS total_appointments,

                COALESCE(
                    SUM(
                        CASE
                            WHEN status = 'scheduled'
                            THEN 1
                            ELSE 0
                        END
                    ),
                    0
                ) AS scheduled,

                COALESCE(
                    SUM(
                        CASE
                            WHEN status = 'confirmed'
                            THEN 1
                            ELSE 0
                        END
                    ),
                    0
                ) AS confirmed,

                COALESCE(
                    SUM(
                        CASE
                            WHEN status = 'completed'
                            THEN 1
                            ELSE 0
                        END
                    ),
                    0
                ) AS completed,

                COALESCE(
                    SUM(
                        CASE
                            WHEN status = 'cancelled'
                            THEN 1
                            ELSE 0
                        END
                    ),
                    0
                ) AS cancelled,

                COALESCE(
                    SUM(
                        CASE
                            WHEN status = 'no-show'
                            THEN 1
                            ELSE 0
                        END
                    ),
                    0
                ) AS no_show,

                COALESCE(
                    SUM(
                        CASE
                            WHEN start_at >= NOW()
                             AND status IN (
                                 'scheduled',
                                 'confirmed'
                             )
                            THEN 1
                            ELSE 0
                        END
                    ),
                    0
                ) AS upcoming

            FROM appointments
        ";

        $params = [];

        if ($providerId !== null) {
            $sql .= "
                WHERE provider_id = :provider_id
            ";

            $params[':provider_id'] =
                $providerId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        $result = $stmt->fetch();

        return [
            'total_appointments' =>
                (int) ($result['total_appointments'] ?? 0),

            'scheduled' =>
                (int) ($result['scheduled'] ?? 0),

            'confirmed' =>
                (int) ($result['confirmed'] ?? 0),

            'completed' =>
                (int) ($result['completed'] ?? 0),

            'cancelled' =>
                (int) ($result['cancelled'] ?? 0),

            'no_show' =>
                (int) ($result['no_show'] ?? 0),

            'upcoming' =>
                (int) ($result['upcoming'] ?? 0)
        ];
    }

    /**
     * Get appointment status breakdown.
     */
    public function getAppointmentStatusBreakdown(
        ?int $providerId = null
    ): array {
        $sql = "
            SELECT
                status,
                COUNT(*) AS total
            FROM appointments
        ";

        $params = [];

        if ($providerId !== null) {
            $sql .= "
                WHERE provider_id = :provider_id
            ";

            $params[':provider_id'] =
                $providerId;
        }

        $sql .= "
            GROUP BY status
            ORDER BY status
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        $rows = $stmt->fetchAll();

        $breakdown = [];

        foreach ($rows as $row) {
            $breakdown[] = [
                'status' => $row['status'],
                'total' => (int) $row['total']
            ];
        }

        return $breakdown;
    }

    /**
     * Get prescription statistics.
     */
    public function getPrescriptionSummary(
        ?int $providerId = null
    ): array {
        $sql = "
            SELECT
                COUNT(*) AS total_prescriptions,

                COALESCE(
                    SUM(
                        CASE
                            WHEN status = 'pending'
                            THEN 1
                            ELSE 0
                        END
                    ),
                    0
                ) AS pending,

                COALESCE(
                    SUM(
                        CASE
                            WHEN status = 'verified'
                            THEN 1
                            ELSE 0
                        END
                    ),
                    0
                ) AS verified,

                COALESCE(
                    SUM(
                        CASE
                            WHEN status = 'dispensed'
                            THEN 1
                            ELSE 0
                        END
                    ),
                    0
                ) AS dispensed,

                COALESCE(
                    SUM(
                        CASE
                            WHEN status = 'cancelled'
                            THEN 1
                            ELSE 0
                        END
                    ),
                    0
                ) AS cancelled

            FROM prescriptions
        ";

        $params = [];

        if ($providerId !== null) {
            $sql .= "
                WHERE provider_id = :provider_id
            ";

            $params[':provider_id'] =
                $providerId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        $result = $stmt->fetch();

        return [
            'total_prescriptions' =>
                (int) ($result['total_prescriptions'] ?? 0),

            'pending' =>
                (int) ($result['pending'] ?? 0),

            'verified' =>
                (int) ($result['verified'] ?? 0),

            'dispensed' =>
                (int) ($result['dispensed'] ?? 0),

            'cancelled' =>
                (int) ($result['cancelled'] ?? 0)
        ];
    }

    /**
     * Get prescription status breakdown.
     */
    public function getPrescriptionStatusBreakdown(
        ?int $providerId = null
    ): array {
        $sql = "
            SELECT
                status,
                COUNT(*) AS total
            FROM prescriptions
        ";

        $params = [];

        if ($providerId !== null) {
            $sql .= "
                WHERE provider_id = :provider_id
            ";

            $params[':provider_id'] =
                $providerId;
        }

        $sql .= "
            GROUP BY status
            ORDER BY status
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        $rows = $stmt->fetchAll();

        $breakdown = [];

        foreach ($rows as $row) {
            $breakdown[] = [
                'status' => $row['status'],
                'total' => (int) $row['total']
            ];
        }

        return $breakdown;
    }

    /**
     * Get tenant-wide aggregate analytics.
     *
     * The database itself represents the tenant.
     */
    public function getTenantAnalytics(): array
    {
        $sql = "
            SELECT
                (
                    SELECT COUNT(*)
                    FROM users
                ) AS total_users,

                (
                    SELECT COUNT(*)
                    FROM users
                    WHERE status = 'active'
                ) AS active_users,

                (
                    SELECT COUNT(*)
                    FROM staff
                    WHERE status = 'active'
                      AND deleted_at IS NULL
                ) AS active_staff,

                (
                    SELECT COUNT(*)
                    FROM patients
                    WHERE deleted_at IS NULL
                ) AS total_patients,

                (
                    SELECT COUNT(*)
                    FROM appointments
                ) AS total_appointments,

                (
                    SELECT COUNT(*)
                    FROM prescriptions
                ) AS total_prescriptions
        ";

        $stmt = $this->db->query($sql);

        $result = $stmt->fetch();

        return [
            'total_users' =>
                (int) ($result['total_users'] ?? 0),

            'active_users' =>
                (int) ($result['active_users'] ?? 0),

            'active_staff' =>
                (int) ($result['active_staff'] ?? 0),

            'total_patients' =>
                (int) ($result['total_patients'] ?? 0),

            'total_appointments' =>
                (int) ($result['total_appointments'] ?? 0),

            'total_prescriptions' =>
                (int) ($result['total_prescriptions'] ?? 0)
        ];
    }
}
