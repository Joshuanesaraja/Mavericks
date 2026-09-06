<?php

namespace App\Services;

require_once __DIR__ . '/../Security/AES.php';

use App\Repositories\PatientRepository;
use RuntimeException;

class PatientService
{
    private PatientRepository $repository;

    public function __construct(
        PatientRepository $repository
    ) {
        $this->repository = $repository;
    }

    public function create(
        int $tenantId,
        int $userId,
        string $data
    ): int {
        if (trim($data) === '') {
            throw new RuntimeException(
                'Patient data is required.'
            );
        }

        /*
         * Encrypt patient data before storing it.
         *
         * The database will NEVER receive the
         * plaintext patient data.
         */
        $encryptedData = \AES::encrypt(
            $data
        );

        /*
         * Provider/Nurse is not the patient's
         * user account.
         *
         * Tenant architecture remains unchanged.
         */
        return $this->repository->create(
            $tenantId,
            $userId,
            $encryptedData
        );
    }

    public function getAll(
        int $tenantId
    ): array {
        $patients = $this->repository->findAll(
            $tenantId
        );

        /*
         * Decrypt patient data before returning
         * it to the controller/API.
         */
        foreach ($patients as &$patient) {

            if (
                isset($patient['encrypted_data']) &&
                $patient['encrypted_data'] !== ''
            ) {
                $patient['encrypted_data'] =
                    \AES::decrypt(
                        $patient['encrypted_data']
                    );
            }
        }

        unset($patient);

        return $patients;
    }

    public function getById(
        int $patientId,
        int $tenantId
    ): array {
        $patient = $this->repository->findById(
            $patientId,
            $tenantId
        );

        if (!$patient) {
            throw new RuntimeException(
                'Patient not found.'
            );
        }

        /*
         * Decrypt only after confirming that
         * the patient belongs to the tenant.
         */
        if (
            isset($patient['encrypted_data']) &&
            $patient['encrypted_data'] !== ''
        ) {
            $patient['encrypted_data'] =
                \AES::decrypt(
                    $patient['encrypted_data']
                );
        }

        return $patient;
    }

    public function update(
        int $patientId,
        int $tenantId,
        string $data
    ): bool {
        if (trim($data) === '') {
            throw new RuntimeException(
                'Patient data is required.'
            );
        }

        /*
         * First verify that the patient belongs
         * to the current tenant.
         */
        $this->getById(
            $patientId,
            $tenantId
        );

        /*
         * Encrypt the new patient data before
         * sending it to the repository.
         */
        $encryptedData = \AES::encrypt(
            $data
        );

        return $this->repository->update(
            $patientId,
            $tenantId,
            $encryptedData
        );
    }

    public function delete(
        int $patientId,
        int $tenantId
    ): bool {
        /*
         * Tenant architecture remains unchanged.
         */
        $this->getById(
            $patientId,
            $tenantId
        );

        return $this->repository->softDelete(
            $patientId,
            $tenantId
        );
    }
}