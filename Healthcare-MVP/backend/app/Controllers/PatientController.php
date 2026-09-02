<?php

namespace App\Controllers;

use App\Services\PatientService;
use RuntimeException;

class PatientController
{
    private PatientService $service;

    public function __construct(PatientService $service)
    {
        $this->service = $service;
    }

    public function index(array $authUser): array
    {
        return $this->service->getAll(
            (int) $authUser['tenant_id']
        );
    }

    public function show(
        int $patientId,
        array $authUser
    ): array {
        return $this->service->getById(
            $patientId,
            (int) $authUser['tenant_id']
        );
    }

    public function store(
        array $data,
        array $authUser
    ): array {
        $encryptedData = $data['encrypted_data'] ?? null;

        if (!is_string($encryptedData) || trim($encryptedData) === '') {
            throw new RuntimeException(
                'encrypted_data is required.'
            );
        }

        $userId = isset($data['user_id'])
            ? (int) $data['user_id']
            : null;

        $id = $this->service->create(
            (int) $authUser['tenant_id'],
            $userId,
            $encryptedData
        );

        return [
            'id' => $id,
            'message' => 'Patient created successfully.'
        ];
    }

    public function update(
        int $patientId,
        array $data,
        array $authUser
    ): array {
        $encryptedData = $data['encrypted_data'] ?? null;

        if (!is_string($encryptedData) || trim($encryptedData) === '') {
            throw new RuntimeException(
                'encrypted_data is required.'
            );
        }

        $userId = isset($data['user_id'])
            ? (int) $data['user_id']
            : null;

        $this->service->update(
            $patientId,
            (int) $authUser['tenant_id'],
            $userId,
            $encryptedData
        );

        return [
            'message' => 'Patient updated successfully.'
        ];
    }

    public function destroy(
        int $patientId,
        array $authUser
    ): array {
        $this->service->delete(
            $patientId,
            (int) $authUser['tenant_id']
        );

        return [
            'message' => 'Patient deleted successfully.'
        ];
    }
}