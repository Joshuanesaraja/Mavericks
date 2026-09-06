<?php

namespace App\Repositories;

use PDO;

class PatientRepository
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function create(
        int $tenantId,
        ?int $userId,
        string $encryptedData
    ): int {
        $stmt = $this->db->prepare("
            INSERT INTO patients (
                tenant_id,
                user_id,
                encrypted_data
            )
            VALUES (
                :tenant_id,
                :user_id,
                :encrypted_data
            )
        ");

        $stmt->execute([
            ':tenant_id' => $tenantId,
            ':user_id' => $userId,
            ':encrypted_data' => $encryptedData
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function findAll(
        int $tenantId
    ): array {
        $stmt = $this->db->prepare("
            SELECT
                id,
                tenant_id,
                user_id,
                encrypted_data,
                created_at,
                updated_at
            FROM patients
            WHERE tenant_id = :tenant_id
              AND deleted_at IS NULL
            ORDER BY id DESC
        ");

        $stmt->execute([
            ':tenant_id' => $tenantId
        ]);

        return $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );
    }

    public function findById(
        int $patientId,
        int $tenantId
    ): ?array {
        $stmt = $this->db->prepare("
            SELECT
                id,
                tenant_id,
                user_id,
                encrypted_data,
                created_at,
                updated_at
            FROM patients
            WHERE id = :patient_id
              AND tenant_id = :tenant_id
              AND deleted_at IS NULL
            LIMIT 1
        ");

        $stmt->execute([
            ':patient_id' => $patientId,
            ':tenant_id' => $tenantId
        ]);

        $patient = $stmt->fetch(
            PDO::FETCH_ASSOC
        );

        return $patient ?: null;
    }

    public function update(
        int $patientId,
        int $tenantId,
        string $encryptedData
    ): bool {
        $stmt = $this->db->prepare("
            UPDATE patients
            SET
                encrypted_data = :encrypted_data,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :patient_id
              AND tenant_id = :tenant_id
              AND deleted_at IS NULL
        ");

        $stmt->execute([
            ':encrypted_data' => $encryptedData,
            ':patient_id' => $patientId,
            ':tenant_id' => $tenantId
        ]);

        return $stmt->rowCount() > 0;
    }

    public function softDelete(
        int $patientId,
        int $tenantId
    ): bool {
        $stmt = $this->db->prepare("
            UPDATE patients
            SET deleted_at = CURRENT_TIMESTAMP
            WHERE id = :patient_id
              AND tenant_id = :tenant_id
              AND deleted_at IS NULL
        ");

        $stmt->execute([
            ':patient_id' => $patientId,
            ':tenant_id' => $tenantId
        ]);

        return $stmt->rowCount() > 0;
    }
}