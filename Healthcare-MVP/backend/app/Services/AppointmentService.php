<?php

require_once __DIR__ . '/../Repositories/AppointmentRepository.php';

class AppointmentService
{
    private static array $allowedStatuses = [
        'scheduled',
        'confirmed',
        'completed',
        'cancelled',
        'no-show'
    ];

    /**
     * Create a new appointment with validation and conflict checking.
     */
    public static function createAppointment(array $user, array $input): array
    {
        $tenantId   = $user['tenantId'];
        $patientId  = (int) ($input['patient_id'] ?? 0);
        $providerId = (int) ($input['provider_id'] ?? 0);
        $startAt    = trim($input['start_at'] ?? '');
        $endAt      = trim($input['end_at'] ?? '');
        $reason     = trim($input['reason'] ?? '');

        // Validation: Required fields
        if ($patientId <= 0 || $providerId <= 0 || empty($startAt) || empty($endAt)) {
            return [
                'success' => false,
                'code'    => 400,
                'message' => 'patient_id, provider_id, start_at, and end_at are required'
            ];
        }

        // Validation: Datetime parsing
        $startTs = strtotime($startAt);
        $endTs   = strtotime($endAt);

        if ($startTs === false || $endTs === false) {
            return [
                'success' => false,
                'code'    => 400,
                'message' => 'Invalid datetime format for start_at or end_at. Use YYYY-MM-DD HH:MM:SS'
            ];
        }

        if ($endTs <= $startTs) {
            return [
                'success' => false,
                'code'    => 400,
                'message' => 'end_at must be later than start_at'
            ];
        }

        // Format datetimes consistently
        $formattedStart = date('Y-m-d H:i:s', $startTs);
        $formattedEnd   = date('Y-m-d H:i:s', $endTs);

        // RBAC: If user is Patient, they must book for themselves
        if (self::isPatientOnly($user['roles']) && $patientId !== $user['userId']) {
            return [
                'success' => false,
                'code'    => 403,
                'message' => 'Patients can only book appointments for themselves'
            ];
        }

        // Time Conflict Check: Ensure provider has no overlapping appointments
        if (AppointmentRepository::hasOverlappingAppointment($tenantId, $providerId, $formattedStart, $formattedEnd)) {
            return [
                'success' => false,
                'code'    => 409,
                'message' => 'Time conflict: The selected provider already has an appointment during this time slot'
            ];
        }

        // Insert Record
        $appointmentId = AppointmentRepository::create([
            'tenant_id'   => $tenantId,
            'patient_id'  => $patientId,
            'provider_id' => $providerId,
            'start_at'    => $formattedStart,
            'end_at'      => $formattedEnd,
            'status'      => 'scheduled',
            'reason'      => $reason ?: null,
        ]);

        $appointment = AppointmentRepository::findById($appointmentId, $tenantId);

        return [
            'success' => true,
            'code'    => 201,
            'data'    => $appointment,
            'message' => 'Appointment created successfully'
        ];
    }

    /**
     * Update an existing appointment (reschedule, change provider/reason).
     */
    public static function updateAppointment(array $user, int $appointmentId, array $input): array
    {
        $tenantId = $user['tenantId'];

        $existing = AppointmentRepository::findById($appointmentId, $tenantId);
        if (!$existing) {
            return [
                'success' => false,
                'code'    => 404,
                'message' => 'Appointment not found'
            ];
        }

        // Authorization check
        if (!self::canModifyAppointment($user, $existing)) {
            return [
                'success' => false,
                'code'    => 403,
                'message' => 'Forbidden: You do not have permission to update this appointment'
            ];
        }

        $newPatientId  = isset($input['patient_id']) ? (int) $input['patient_id'] : (int) $existing['patient_id'];
        $newProviderId = isset($input['provider_id']) ? (int) $input['provider_id'] : (int) $existing['provider_id'];
        $newStartAt    = !empty($input['start_at']) ? trim($input['start_at']) : $existing['start_at'];
        $newEndAt      = !empty($input['end_at']) ? trim($input['end_at']) : $existing['end_at'];
        $newReason     = array_key_exists('reason', $input) ? trim($input['reason']) : $existing['reason'];
        $newStatus     = !empty($input['status']) ? trim($input['status']) : $existing['status'];

        $startTs = strtotime($newStartAt);
        $endTs   = strtotime($newEndAt);

        if ($startTs === false || $endTs === false || $endTs <= $startTs) {
            return [
                'success' => false,
                'code'    => 400,
                'message' => 'Invalid datetime values: end_at must be later than start_at'
            ];
        }

        $formattedStart = date('Y-m-d H:i:s', $startTs);
        $formattedEnd   = date('Y-m-d H:i:s', $endTs);

        if (!in_array($newStatus, self::$allowedStatuses, true)) {
            return [
                'success' => false,
                'code'    => 400,
                'message' => 'Invalid status. Allowed values: ' . implode(', ', self::$allowedStatuses)
            ];
        }

        // Time Conflict check when timing or provider changes (excluding current appointment ID)
        if (
            ($formattedStart !== $existing['start_at'] || $formattedEnd !== $existing['end_at'] || $newProviderId !== (int)$existing['provider_id']) &&
            $newStatus !== 'cancelled'
        ) {
            if (AppointmentRepository::hasOverlappingAppointment($tenantId, $newProviderId, $formattedStart, $formattedEnd, $appointmentId)) {
                return [
                    'success' => false,
                    'code'    => 409,
                    'message' => 'Time conflict: Provider is not available during the new time slot'
                ];
            }
        }

        $updateData = [
            'patient_id'  => $newPatientId,
            'provider_id' => $newProviderId,
            'start_at'    => $formattedStart,
            'end_at'      => $formattedEnd,
            'status'      => $newStatus,
            'reason'      => $newReason,
        ];

        AppointmentRepository::update($appointmentId, $tenantId, $updateData);

        $updated = AppointmentRepository::findById($appointmentId, $tenantId);

        return [
            'success' => true,
            'code'    => 200,
            'data'    => $updated,
            'message' => 'Appointment updated successfully'
        ];
    }

    /**
     * Cancel an appointment.
     */
    public static function cancelAppointment(array $user, int $appointmentId, ?string $reason = null): array
    {
        $tenantId = $user['tenantId'];

        $existing = AppointmentRepository::findById($appointmentId, $tenantId);
        if (!$existing) {
            return [
                'success' => false,
                'code'    => 404,
                'message' => 'Appointment not found'
            ];
        }

        if ($existing['status'] === 'cancelled') {
            return [
                'success' => false,
                'code'    => 400,
                'message' => 'Appointment is already cancelled'
            ];
        }

        // Authorization check
        if (!self::canModifyAppointment($user, $existing)) {
            return [
                'success' => false,
                'code'    => 403,
                'message' => 'Forbidden: You do not have permission to cancel this appointment'
            ];
        }

        AppointmentRepository::cancel($appointmentId, $tenantId, $reason);

        $updated = AppointmentRepository::findById($appointmentId, $tenantId);

        return [
            'success' => true,
            'code'    => 200,
            'data'    => $updated,
            'message' => 'Appointment cancelled successfully'
        ];
    }

    /**
     * Update appointment status specifically (e.g., confirmed, completed, no-show).
     */
    public static function updateStatus(array $user, int $appointmentId, string $status): array
    {
        $tenantId = $user['tenantId'];
        $status   = trim($status);

        if (!in_array($status, self::$allowedStatuses, true)) {
            return [
                'success' => false,
                'code'    => 400,
                'message' => 'Invalid status. Allowed values: ' . implode(', ', self::$allowedStatuses)
            ];
        }

        $existing = AppointmentRepository::findById($appointmentId, $tenantId);
        if (!$existing) {
            return [
                'success' => false,
                'code'    => 404,
                'message' => 'Appointment not found'
            ];
        }

        // Authorization check (Providers, Nurses, Admins can update status)
        if (self::isPatientOnly($user['roles'])) {
            return [
                'success' => false,
                'code'    => 403,
                'message' => 'Patients cannot directly change appointment status (except cancelling)'
            ];
        }

        AppointmentRepository::updateStatus($appointmentId, $tenantId, $status);

        $updated = AppointmentRepository::findById($appointmentId, $tenantId);

        return [
            'success' => true,
            'code'    => 200,
            'data'    => $updated,
            'message' => 'Appointment status updated to ' . $status
        ];
    }

    /**
     * Get upcoming appointments scoped by user role.
     */
    public static function getUpcomingAppointments(array $user): array
    {
        $tenantId  = $user['tenantId'];
        $userId    = $user['userId'];
        $roles     = $user['roles'];

        $patientFilter  = null;
        $providerFilter = null;

        if (in_array('Patient', $roles, true) && count($roles) === 1) {
            $patientFilter = $userId;
        } elseif (in_array('Provider', $roles, true) && !in_array('Admin', $roles, true)) {
            $providerFilter = $userId;
        }

        $appointments = AppointmentRepository::getUpcoming($tenantId, $patientFilter, $providerFilter);

        return [
            'success' => true,
            'code'    => 200,
            'data'    => $appointments,
            'message' => 'Upcoming appointments retrieved successfully'
        ];
    }

    /**
     * Get appointment details by ID.
     */
    public static function getAppointmentDetail(array $user, int $appointmentId): array
    {
        $tenantId = $user['tenantId'];

        $appointment = AppointmentRepository::findById($appointmentId, $tenantId);

        if (!$appointment) {
            return [
                'success' => false,
                'code'    => 404,
                'message' => 'Appointment not found'
            ];
        }

        // Authorization check
        if (
            self::isPatientOnly($user['roles']) && 
            (int) $appointment['patient_id'] !== $user['userId']
        ) {
            return [
                'success' => false,
                'code'    => 403,
                'message' => 'Forbidden: You cannot view another patient\'s appointment'
            ];
        }

        return [
            'success' => true,
            'code'    => 200,
            'data'    => $appointment,
            'message' => 'Appointment details retrieved'
        ];
    }

    /**
     * List appointments with optional filters.
     */
    public static function listAppointments(array $user, array $filters): array
    {
        $tenantId = $user['tenantId'];
        $userId   = $user['userId'];
        $roles    = $user['roles'];

        if (self::isPatientOnly($roles)) {
            $filters['patient_id'] = $userId;
        } elseif (in_array('Provider', $roles, true) && !in_array('Admin', $roles, true)) {
            if (empty($filters['patient_id'])) {
                $filters['provider_id'] = $userId;
            }
        }

        $appointments = AppointmentRepository::listAll($tenantId, $filters);

        return [
            'success' => true,
            'code'    => 200,
            'data'    => $appointments,
            'message' => 'Appointments retrieved successfully'
        ];
    }

    /**
     * Helper: Check if user is only a Patient.
     */
    private static function isPatientOnly(array $roles): bool
    {
        return in_array('Patient', $roles, true) && 
               !in_array('Admin', $roles, true) && 
               !in_array('Provider', $roles, true) && 
               !in_array('Nurse', $roles, true);
    }

    /**
     * Helper: Check if user has permission to modify an appointment.
     */
    private static function canModifyAppointment(array $user, array $appointment): bool
    {
        $userId = $user['userId'];
        $roles  = $user['roles'];

        if (in_array('Admin', $roles, true) || in_array('Nurse', $roles, true)) {
            return true;
        }

        if (in_array('Provider', $roles, true) && (int) $appointment['provider_id'] === $userId) {
            return true;
        }

        if (in_array('Patient', $roles, true) && (int) $appointment['patient_id'] === $userId) {
            return true;
        }

        return false;
    }
}
