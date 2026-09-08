<?php

namespace App\Repositories;

use PDO;
use RuntimeException;

class PatientRepository
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Create a patient inside the current tenant database.
     *
     * Tenant isolation is provided by the database connection itself.
     */
    public function create(
        ?int $userId,
        string $encryptedData
    ): int {
        $stmt = $this->db->prepare("
            INSERT INTO patients (
                user_id,
                encrypted_data
            )
            VALUES (
                :user_id,
                :encrypted_data
            )
        ");

        $stmt->execute([
            ':user_id' => $userId,
            ':encrypted_data' => $encryptedData
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * Get all active patients from the current tenant database.
     */
    public function findAll(): array
    {
        $stmt = $this->db->query("
            SELECT
                id,
                user_id,
                encrypted_data,
                created_at,
                updated_at
            FROM patients
            WHERE deleted_at IS NULL
            ORDER BY id DESC
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Find one active patient in the current tenant database.
     */
    public function findById(
        int $patientId
    ): ?array {
        $stmt = $this->db->prepare("
            SELECT
                id,
                user_id,
                encrypted_data,
                created_at,
                updated_at
            FROM patients
            WHERE id = :patient_id
              AND deleted_at IS NULL
            LIMIT 1
        ");

        $stmt->execute([
            ':patient_id' => $patientId
        ]);

        $patient = $stmt->fetch(PDO::FETCH_ASSOC);

        return $patient ?: null;
    }

    /**
     * Update an active patient.
     */
    public function update(
        int $patientId,
        string $encryptedData
    ): bool {
        $stmt = $this->db->prepare("
            UPDATE patients
            SET
                encrypted_data = :encrypted_data,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :patient_id
              AND deleted_at IS NULL
        ");

        $stmt->execute([
            ':encrypted_data' => $encryptedData,
            ':patient_id' => $patientId
        ]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Soft-delete a patient.
     */
    public function softDelete(
        int $patientId
    ): bool {
        $stmt = $this->db->prepare("
            UPDATE patients
            SET
                deleted_at = CURRENT_TIMESTAMP,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :patient_id
              AND deleted_at IS NULL
        ");

        $stmt->execute([
            ':patient_id' => $patientId
        ]);

        return $stmt->rowCount() > 0;
    }
}
