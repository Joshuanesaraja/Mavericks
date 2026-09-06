<?php

namespace App\Services;

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
        string $encryptedData
    ): int {
        if (trim($encryptedData) === '') {
            throw new RuntimeException(
                'Encrypted patient data is required.'
            );
        }

        // Provider/Nurse is not the patient's user account.
        return $this->repository->create(
            $tenantId,
            null,
            $encryptedData
        );
    }

    public function getAll(
        int $tenantId
    ): array {
        return $this->repository->findAll(
            $tenantId
        );
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

        return $patient;
    }

    public function update(
        int $patientId,
        int $tenantId,
        string $encryptedData
    ): bool {
        if (trim($encryptedData) === '') {
            throw new RuntimeException(
                'Encrypted patient data is required.'
            );
        }

        $this->getById(
            $patientId,
            $tenantId
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