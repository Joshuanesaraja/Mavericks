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

    /**
     * GET /patients
     */
    public function index(
        object $authUser
    ): array {
        return $this->service->getAll();
    }

    /**
     * GET /patients/{id}
     */
    public function show(
        int $patientId,
        object $authUser
    ): array {
        return $this->service->getById(
            $patientId
        );
    }

    /**
     * POST /patients
     */
    public function store(
        array $data,
        object $authUser
    ): array {
        $patientData =
            $data['encrypted_data'] ?? null;

        if (
            !is_string($patientData) ||
            trim($patientData) === ''
        ) {
            throw new RuntimeException(
                'encrypted_data is required.'
            );
        }

        /*
         * The authenticated user's ID is stored as the
         * patient record's user_id.
         *
         * Tenant isolation comes from the tenant DB
         * selected by AuthMiddleware.
         */
        $id = $this->service->create(
            isset($authUser->user_id)
                ? (int) $authUser->user_id
                : (
                    isset($authUser->sub)
                        ? (int) $authUser->sub
                        : null
                ),
            $patientData
        );

        return [
            'id' => $id,
            'message' =>
                'Patient created successfully.'
        ];
    }

    /**
     * PUT /patients/{id}
     */
    public function update(
        int $patientId,
        array $data,
        object $authUser
    ): array {
        $patientData =
            $data['encrypted_data'] ?? null;

        if (
            !is_string($patientData) ||
            trim($patientData) === ''
        ) {
            throw new RuntimeException(
                'encrypted_data is required.'
            );
        }

        $this->service->update(
            $patientId,
            $patientData
        );

        return [
            'message' =>
                'Patient updated successfully.'
        ];
    }

    /**
     * DELETE /patients/{id}
     */
    public function destroy(
        int $patientId,
        object $authUser
    ): array {
        $this->service->delete(
            $patientId
        );

        return [
            'message' =>
                'Patient deleted successfully.'
        ];
    }
}
