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

    /**
     * Create a patient.
     *
     * The authenticated tenant database is already selected
     * before this service is called.
     */
    public function create(
        ?int $userId,
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
         * Plaintext patient data must never be stored
         * in the database.
         */
        $encryptedData = \AES::encrypt($data);

        return $this->repository->create(
            $userId,
            $encryptedData
        );
    }

    /**
     * Get one API page of active patients.
     *
     * The API batch is intentionally fixed at 10 records.
     * The frontend handles the 5-record visual pages.
     */
    public function getPage(
        int $page = 1,
        int $limit = 10
    ): array {
        $page = max(1, $page);

        /*
        * Keep the server-side batch size fixed at 10.
        *
        * Even if somebody manually sends ?limit=100,
        * this endpoint will still return only 10.
        */
        $limit = 10;

        $offset =
            ($page - 1) *
            $limit;

        $patients =
            $this->repository->findPage(
                $limit,
                $offset
            );

        $total =
            $this->repository->countAll();

        /*
        * Decrypt patient data only when returning
        * it to the API.
        */
        foreach (
            $patients as &$patient
        ) {
            if (
                isset(
                    $patient['encrypted_data']
                ) &&
                $patient['encrypted_data'] !== ''
            ) {
                $patient['encrypted_data'] =
                    \AES::decrypt(
                        $patient['encrypted_data']
                    );
            }
        }

        unset($patient);

        return [
            'patients' => $patients,

            'pagination' => [
                'page' => $page,

                'limit' => $limit,

                'total' => $total,

                'has_more' =>
                    (
                        $offset +
                        count($patients)
                    ) < $total
            ]
        ];
    }

    /**
     * Get one patient.
     */
    public function getById(
        int $patientId
    ): array {
        $patient = $this->repository->findById(
            $patientId
        );

        if (!$patient) {
            throw new RuntimeException(
                'Patient not found.'
            );
        }

        /*
         * Decrypt only after the patient has been
         * successfully found in the current tenant DB.
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

    /**
     * Update a patient.
     */
    public function update(
        int $patientId,
        string $data
    ): bool {
        if (trim($data) === '') {
            throw new RuntimeException(
                'Patient data is required.'
            );
        }

        /*
         * Verify the patient exists in the current
         * tenant database before updating.
         */
        $this->getById($patientId);

        $encryptedData = \AES::encrypt($data);

        return $this->repository->update(
            $patientId,
            $encryptedData
        );
    }

    /**
     * Soft-delete a patient.
     */
    public function delete(
        int $patientId
    ): bool {
        /*
         * Verify the patient exists in the current
         * tenant database before deleting.
         */
        $this->getById($patientId);

        return $this->repository->softDelete(
            $patientId
        );
    }
}
