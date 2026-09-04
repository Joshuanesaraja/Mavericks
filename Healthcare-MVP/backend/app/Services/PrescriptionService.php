<?php

require_once __DIR__ . '/../Repositories/PrescriptionRepository.php';
require_once __DIR__ . '/../Security/AES.php';

class PrescriptionService
{
    private static array $allowedStatuses = [
        'pending',
        'verified',
        'dispensed',
        'cancelled'
    ];

    /**
     * Provider creates prescription (encrypting data via AES-256).
     */
    public static function createPrescription(object $auth, array $input): array
    {
        $tenantId   = (int) $auth->tenant_id;
        $providerId = (int) $auth->sub;
        $roles      = (array) ($auth->roles ?? []);

        // RBAC: Only Providers and Admins can write prescriptions
        if (!in_array('Provider', $roles, true) && !in_array('Admin', $roles, true)) {
            return [
                'success' => false,
                'code'    => 403,
                'message' => 'Forbidden: Only Providers and Admins can create prescriptions'
            ];
        }

        $patientId  = (int) ($input['patient_id'] ?? 0);
        $details    = $input['details'] ?? $input['medications'] ?? null;

        if ($patientId <= 0 || empty($details)) {
            return [
                'success' => false,
                'code'    => 400,
                'message' => 'patient_id and details (medications/dosage/instructions) are required'
            ];
        }

        $jsonPayload = is_string($details) ? $details : json_encode($details);
        $encryptedData = AES::encrypt($jsonPayload);

        $id = PrescriptionRepository::create([
            'tenant_id'      => $tenantId,
            'patient_id'     => $patientId,
            'provider_id'    => $providerId,
            'pharmacist_id'  => null,
            'encrypted_data' => $encryptedData,
            'status'         => 'pending',
        ]);

        $record = self::getPrescriptionDetail($auth, $id);

        return [
            'success' => true,
            'code'    => 201,
            'data'    => $record['data'] ?? null,
            'message' => 'Prescription created successfully and encrypted'
        ];
    }

    /**
     * Pharmacist or Admin verifies/updates prescription status.
     */
    public static function updateStatus(object $auth, int $id, string $status): array
    {
        $tenantId = (int) $auth->tenant_id;
        $userId   = (int) $auth->sub;
        $roles    = (array) ($auth->roles ?? []);
        $status   = trim(strtolower($status));

        if (!in_array($status, self::$allowedStatuses, true)) {
            return [
                'success' => false,
                'code'    => 400,
                'message' => 'Invalid status. Allowed: ' . implode(', ', self::$allowedStatuses)
            ];
        }

        // RBAC: Only Pharmacist or Admin can verify/dispense
        if (!in_array('Pharmacist', $roles, true) && !in_array('Admin', $roles, true)) {
            return [
                'success' => false,
                'code'    => 403,
                'message' => 'Forbidden: Only Pharmacists or Admins can verify or update prescription status'
            ];
        }

        $existing = PrescriptionRepository::findById($id, $tenantId);
        if (!$existing) {
            return [
                'success' => false,
                'code'    => 404,
                'message' => 'Prescription not found'
            ];
        }

        $pharmacistId = in_array('Pharmacist', $roles, true) ? $userId : null;
        PrescriptionRepository::updateStatus($id, $tenantId, $pharmacistId, $status);

        $record = self::getPrescriptionDetail($auth, $id);

        return [
            'success' => true,
            'code'    => 200,
            'data'    => $record['data'] ?? null,
            'message' => 'Prescription status updated to ' . $status
        ];
    }

    /**
     * Get single prescription with decrypted payload.
     */
    public static function getPrescriptionDetail(object $auth, int $id): array
    {
        $tenantId = (int) $auth->tenant_id;
        $userId   = (int) $auth->sub;
        $roles    = (array) ($auth->roles ?? []);

        $prescription = PrescriptionRepository::findById($id, $tenantId);
        if (!$prescription) {
            return [
                'success' => false,
                'code'    => 404,
                'message' => 'Prescription not found'
            ];
        }

        // RBAC Scoping
        if (in_array('Patient', $roles, true) && count($roles) === 1) {
            if ((int) $prescription['patient_id'] !== $userId) {
                return [
                    'success' => false,
                    'code'    => 403,
                    'message' => 'Forbidden: You cannot view another patient\'s prescription'
                ];
            }
        }

        // Decrypt AES data
        try {
            $decrypted = AES::decrypt($prescription['encrypted_data']);
            $decodedJson = json_decode($decrypted, true);
            $prescription['details'] = $decodedJson !== null ? $decodedJson : $decrypted;
        } catch (Throwable $e) {
            $prescription['details'] = '[Decryption Failed]';
        }

        unset($prescription['encrypted_data']);

        return [
            'success' => true,
            'code'    => 200,
            'data'    => $prescription,
            'message' => 'Prescription retrieved successfully'
        ];
    }

    /**
     * List prescriptions with filters and decrypted payloads.
     */
    public static function listPrescriptions(object $auth, array $filters): array
    {
        $tenantId = (int) $auth->tenant_id;
        $userId   = (int) $auth->sub;
        $roles    = (array) ($auth->roles ?? []);

        if (in_array('Patient', $roles, true) && count($roles) === 1) {
            $filters['patient_id'] = $userId;
        } elseif (in_array('Provider', $roles, true) && !in_array('Admin', $roles, true) && !in_array('Pharmacist', $roles, true)) {
            if (empty($filters['patient_id'])) {
                $filters['provider_id'] = $userId;
            }
        }

        $list = PrescriptionRepository::listAll($tenantId, $filters);

        foreach ($list as &$item) {
            try {
                $decrypted = AES::decrypt($item['encrypted_data']);
                $decodedJson = json_decode($decrypted, true);
                $item['details'] = $decodedJson !== null ? $decodedJson : $decrypted;
            } catch (Throwable $e) {
                $item['details'] = '[Decryption Failed]';
            }
            unset($item['encrypted_data']);
        }

        return [
            'success' => true,
            'code'    => 200,
            'data'    => $list,
            'message' => 'Prescriptions list retrieved'
        ];
    }
}
