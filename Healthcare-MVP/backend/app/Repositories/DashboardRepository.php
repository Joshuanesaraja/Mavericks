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
     * appointments or prescriptions with that provider are counted.
     */
    public function getTotalPatients(
        int $tenantId,
        ?int $providerId = null
    ): int {
        if ($providerId !== null) {
            $sql = "
                SELECT COUNT(*) AS total_patients
                FROM patients p
                WHERE p.tenant_id = :tenant_id
                  AND p.deleted_at IS NULL
                  AND (
                      EXISTS (
                          SELECT 1
                          FROM appointments a
                          WHERE a.patient_id = p.id
                            AND a.tenant_id = :appointment_tenant_id
                            AND a.provider_id = :appointment_provider_id
                      )
                      OR EXISTS (
                          SELECT 1
                          FROM prescriptions pr
                          WHERE pr.patient_id = p.id
                            AND pr.tenant_id = :prescription_tenant_id
                            AND pr.provider_id = :prescription_provider_id
                      )
                  )
            ";

            $stmt = $this->db->prepare($sql);

            $stmt->execute([
                ':tenant_id' => $tenantId,
                ':appointment_tenant_id' => $tenantId,
                ':appointment_provider_id' => $providerId,
                ':prescription_tenant_id' => $tenantId,
                ':prescription_provider_id' => $providerId
            ]);

            return (int) $stmt->fetchColumn();
        }

        $sql = "
            SELECT COUNT(*) AS total_patients
            FROM patients
            WHERE tenant_id = :tenant_id
              AND deleted_at IS NULL
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            ':tenant_id' => $tenantId
        ]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Get appointment statistics.
     *
     * Provider users only see their own appointments.
     * Admin users see all appointments in their tenant.
     */
    public function getAppointmentStatistics(
        int $tenantId,
        ?int $providerId = null
    ): array {
        $sql = "
            SELECT
                COUNT(*) AS total_appointments,

                COALESCE(
                    SUM(
                        CASE
                            WHEN status = 'scheduled' THEN 1
                            ELSE 0
                        END
                    ),
                    0
                ) AS scheduled,

                COALESCE(
                    SUM(
                        CASE
                            WHEN status = 'confirmed' THEN 1
                            ELSE 0
                        END
                    ),
                    0
                ) AS confirmed,

                COALESCE(
                    SUM(
                        CASE
                            WHEN status = 'completed' THEN 1
                            ELSE 0
                        END
                    ),
                    0
                ) AS completed,

                COALESCE(
                    SUM(
                        CASE
                            WHEN status = 'cancelled' THEN 1
                            ELSE 0
                        END
                    ),
                    0
                ) AS cancelled,

                COALESCE(
                    SUM(
                        CASE
                            WHEN status = 'no-show' THEN 1
                            ELSE 0
                        END
                    ),
                    0
                ) AS no_show,

                COALESCE(
                    SUM(
                        CASE
                            WHEN start_at >= NOW()
                             AND status IN ('scheduled', 'confirmed')
                            THEN 1
                            ELSE 0
                        END
                    ),
                    0
                ) AS upcoming

            FROM appointments
            WHERE tenant_id = :tenant_id
        ";

        $params = [
            ':tenant_id' => $tenantId
        ];

        if ($providerId !== null) {
            $sql .= "
                AND provider_id = :provider_id
            ";

            $params[':provider_id'] = $providerId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        $result = $stmt->fetch();

        return [
            'total_appointments' => (int) ($result['total_appointments'] ?? 0),
            'scheduled' => (int) ($result['scheduled'] ?? 0),
            'confirmed' => (int) ($result['confirmed'] ?? 0),
            'completed' => (int) ($result['completed'] ?? 0),
            'cancelled' => (int) ($result['cancelled'] ?? 0),
            'no_show' => (int) ($result['no_show'] ?? 0),
            'upcoming' => (int) ($result['upcoming'] ?? 0)
        ];
    }

    /**
     * Get appointment status breakdown.
     *
     * Useful for dashboard charts/reports.
     */
    public function getAppointmentStatusBreakdown(
        int $tenantId,
        ?int $providerId = null
    ): array {
        $sql = "
            SELECT
                status,
                COUNT(*) AS total
            FROM appointments
            WHERE tenant_id = :tenant_id
        ";

        $params = [
            ':tenant_id' => $tenantId
        ];

        if ($providerId !== null) {
            $sql .= "
                AND provider_id = :provider_id
            ";

            $params[':provider_id'] = $providerId;
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
     *
     * Provider users only see their own prescriptions.
     * Admin users see all prescriptions in their tenant.
     */
    public function getPrescriptionSummary(
        int $tenantId,
        ?int $providerId = null
    ): array {
        $sql = "
            SELECT
                COUNT(*) AS total_prescriptions,

                COALESCE(
                    SUM(
                        CASE
                            WHEN status = 'pending' THEN 1
                            ELSE 0
                        END
                    ),
                    0
                ) AS pending,

                COALESCE(
                    SUM(
                        CASE
                            WHEN status = 'verified' THEN 1
                            ELSE 0
                        END
                    ),
                    0
                ) AS verified,

                COALESCE(
                    SUM(
                        CASE
                            WHEN status = 'dispensed' THEN 1
                            ELSE 0
                        END
                    ),
                    0
                ) AS dispensed,

                COALESCE(
                    SUM(
                        CASE
                            WHEN status = 'cancelled' THEN 1
                            ELSE 0
                        END
                    ),
                    0
                ) AS cancelled

            FROM prescriptions
            WHERE tenant_id = :tenant_id
        ";

        $params = [
            ':tenant_id' => $tenantId
        ];

        if ($providerId !== null) {
            $sql .= "
                AND provider_id = :provider_id
            ";

            $params[':provider_id'] = $providerId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        $result = $stmt->fetch();

        return [
            'total_prescriptions' => (int) ($result['total_prescriptions'] ?? 0),
            'pending' => (int) ($result['pending'] ?? 0),
            'verified' => (int) ($result['verified'] ?? 0),
            'dispensed' => (int) ($result['dispensed'] ?? 0),
            'cancelled' => (int) ($result['cancelled'] ?? 0)
        ];
    }

    /**
     * Get prescription status breakdown.
     *
     * Useful for dashboard charts/reports.
     */
    public function getPrescriptionStatusBreakdown(
        int $tenantId,
        ?int $providerId = null
    ): array {
        $sql = "
            SELECT
                status,
                COUNT(*) AS total
            FROM prescriptions
            WHERE tenant_id = :tenant_id
        ";

        $params = [
            ':tenant_id' => $tenantId
        ];

        if ($providerId !== null) {
            $sql .= "
                AND provider_id = :provider_id
            ";

            $params[':provider_id'] = $providerId;
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
     * Get tenant-wide analytics.
     *
     * This endpoint returns aggregate information only.
     * No encrypted medical/prescription data is returned.
     */
    public function getTenantAnalytics(
        int $tenantId
    ): array {
        $sql = "
            SELECT
                (
                    SELECT COUNT(*)
                    FROM users
                    WHERE tenant_id = :users_tenant_id
                ) AS total_users,

                (
                    SELECT COUNT(*)
                    FROM users
                    WHERE tenant_id = :active_users_tenant_id
                      AND status = 'active'
                ) AS active_users,

                (
                    SELECT COUNT(*)
                    FROM staff
                    WHERE tenant_id = :staff_tenant_id
                      AND status = 'active'
                      AND deleted_at IS NULL
                ) AS active_staff,

                (
                    SELECT COUNT(*)
                    FROM patients
                    WHERE tenant_id = :patients_tenant_id
                      AND deleted_at IS NULL
                ) AS total_patients,

                (
                    SELECT COUNT(*)
                    FROM appointments
                    WHERE tenant_id = :appointments_tenant_id
                ) AS total_appointments,

                (
                    SELECT COUNT(*)
                    FROM prescriptions
                    WHERE tenant_id = :prescriptions_tenant_id
                ) AS total_prescriptions
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            ':users_tenant_id' => $tenantId,
            ':active_users_tenant_id' => $tenantId,
            ':staff_tenant_id' => $tenantId,
            ':patients_tenant_id' => $tenantId,
            ':appointments_tenant_id' => $tenantId,
            ':prescriptions_tenant_id' => $tenantId
        ]);

        $result = $stmt->fetch();

        return [
            'tenant_id' => $tenantId,
            'total_users' => (int) ($result['total_users'] ?? 0),
            'active_users' => (int) ($result['active_users'] ?? 0),
            'active_staff' => (int) ($result['active_staff'] ?? 0),
            'total_patients' => (int) ($result['total_patients'] ?? 0),
            'total_appointments' => (int) ($result['total_appointments'] ?? 0),
            'total_prescriptions' => (int) ($result['total_prescriptions'] ?? 0)
        ];
    }
}