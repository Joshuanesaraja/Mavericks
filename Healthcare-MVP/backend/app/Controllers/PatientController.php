<?php

namespace App\Controllers;

use App\Services\PatientService;
use RuntimeException;

class PatientController
{
    private PatientService $service;

    public function __construct(
        PatientService $service
    ) {
        $this->service = $service;
    }

    public function index(
        object $authUser
    ): array {
        return $this->service->getAll(
            (int) $authUser->tenant_id
        );
    }

    public function show(
        int $patientId,
        object $authUser
    ): array {
        return $this->service->getById(
            $patientId,
            (int) $authUser->tenant_id
        );
    }

    public function store(
        array $data,
        object $authUser
    ): array {
        $encryptedData =
            $data['encrypted_data'] ?? null;

        if (
            !is_string($encryptedData) ||
            trim($encryptedData) === ''
        ) {
            throw new RuntimeException(
                'encrypted_data is required.'
            );
        }

        $id = $this->service->create(
            (int) $authUser->tenant_id,
            $encryptedData
        );

        return [
            'id' => $id,
            'message' =>
                'Patient created successfully.'
        ];
    }

    public function update(
        int $patientId,
        array $data,
        object $authUser
    ): array {
        $encryptedData =
            $data['encrypted_data'] ?? null;

        if (
            !is_string($encryptedData) ||
            trim($encryptedData) === ''
        ) {
            throw new RuntimeException(
                'encrypted_data is required.'
            );
        }

        $this->service->update(
            $patientId,
            (int) $authUser->tenant_id,
            $encryptedData
        );

        return [
            'message' =>
                'Patient updated successfully.'
        ];
    }

    public function destroy(
        int $patientId,
        object $authUser
    ): array {
        $this->service->delete(
            $patientId,
            (int) $authUser->tenant_id
        );

        return [
            'message' =>
                'Patient deleted successfully.'
        ];
    }
}