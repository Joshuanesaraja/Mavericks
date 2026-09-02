<?php

namespace App\Services;

use App\Repositories\PatientRepository;
use RuntimeException;

class PatientService
{
    private PatientRepository $repository;

    public function __construct(PatientRepository $repository)
    {
        $this->repository = $repository;
    }

    public function create(
        int $tenantId,
        ?int $userId,
        string $encryptedData
    ): int {
        if (trim($encryptedData) === '') {
            throw new RuntimeException(
                'Encrypted patient data is required.'
            );
        }

        return $this->repository->create(
            $tenantId,
            $userId,
            $encryptedData
        );
    }

    public function getAll(int $tenantId): array
    {
        return $this->repository->findAll($tenantId);
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
        ?int $userId,
        string $encryptedData
    ): bool {
        $this->getById(
            $patientId,
            $tenantId
        );

        return $this->repository->update(
            $patientId,
            $tenantId,
            $userId,
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