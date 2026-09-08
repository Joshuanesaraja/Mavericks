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
     * Helper to extract user ID safely from object or array.
     */
    private static function getUserId(object|array $user): int
    {
        if (is_object($user)) {
            return (int) ($user->sub ?? $user->id ?? 0);
        }
        return (int) ($user['userId'] ?? $user['user_id'] ?? $user['id'] ?? 0);
    }

    /**
     * Helper to extract user roles safely from object or array.
     */
    private static function getUserRoles(object|array $user): array
    {
        if (is_object($user)) {
            return (array) ($user->roles ?? []);
        }
        return (array) ($user['roles'] ?? []);
    }

    /**
     * Create a new appointment with validation and conflict checking.
     */
    public static function createAppointment(object|array $user, array $input): array
    {
        $userId     = self::getUserId($user);
        $roles      = self::getUserRoles($user);

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

        $formattedStart = date('Y-m-d H:i:s', $startTs);
        $formattedEnd   = date('Y-m-d H:i:s', $endTs);

        // RBAC: If user is Patient, they must book for themselves
        if (self::isPatientOnly($roles) && $patientId !== $userId) {
            return [
                'success' => false,
                'code'    => 403,
                'message' => 'Patients can only book appointments for themselves'
            ];
        }

        // Time Conflict Check: Ensure provider has no overlapping appointments
        if (AppointmentRepository::hasOverlappingAppointment($user, $providerId, $formattedStart, $formattedEnd)) {
            return [
                'success' => false,
                'code'    => 409,
                'message' => 'Time conflict: The selected provider already has an appointment during this time slot'
            ];
        }

        // Insert Record
        $appointmentId = AppointmentRepository::create($user, [
            'patient_id'  => $patientId,
            'provider_id' => $providerId,
            'start_at'    => $formattedStart,
            'end_at'      => $formattedEnd,
            'status'      => 'scheduled',
            'reason'      => $reason ?: null,
        ]);

        $appointment = AppointmentRepository::findById($user, $appointmentId);

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
    public static function updateAppointment(object|array $user, int $appointmentId, array $input): array
    {
        $existing = AppointmentRepository::findById($user, $appointmentId);
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

        // Time Conflict check when timing or provider changes
        if (
            ($formattedStart !== $existing['start_at'] || $formattedEnd !== $existing['end_at'] || $newProviderId !== (int)$existing['provider_id']) &&
            $newStatus !== 'cancelled'
        ) {
            if (AppointmentRepository::hasOverlappingAppointment($user, $newProviderId, $formattedStart, $formattedEnd, $appointmentId)) {
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

        AppointmentRepository::update($user, $appointmentId, $updateData);

        $updated = AppointmentRepository::findById($user, $appointmentId);

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
    public static function cancelAppointment(object|array $user, int $appointmentId, ?string $reason = null): array
    {
        $existing = AppointmentRepository::findById($user, $appointmentId);
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

        if (!self::canModifyAppointment($user, $existing)) {
            return [
                'success' => false,
                'code'    => 403,
                'message' => 'Forbidden: You do not have permission to cancel this appointment'
            ];
        }

        AppointmentRepository::cancel($user, $appointmentId, $reason);

        $updated = AppointmentRepository::findById($user, $appointmentId);

        return [
            'success' => true,
            'code'    => 200,
            'data'    => $updated,
            'message' => 'Appointment cancelled successfully'
        ];
    }

    /**
     * Update appointment status specifically.
     */
    public static function updateStatus(object|array $user, int $appointmentId, string $status): array
    {
        $status = trim($status);

        if (!in_array($status, self::$allowedStatuses, true)) {
            return [
                'success' => false,
                'code'    => 400,
                'message' => 'Invalid status. Allowed values: ' . implode(', ', self::$allowedStatuses)
            ];
        }

        $existing = AppointmentRepository::findById($user, $appointmentId);
        if (!$existing) {
            return [
                'success' => false,
                'code'    => 404,
                'message' => 'Appointment not found'
            ];
        }

        $roles = self::getUserRoles($user);
        if (self::isPatientOnly($roles)) {
            return [
                'success' => false,
                'code'    => 403,
                'message' => 'Patients cannot directly change appointment status (except cancelling)'
            ];
        }

        AppointmentRepository::updateStatus($user, $appointmentId, $status);

        $updated = AppointmentRepository::findById($user, $appointmentId);

        return [
            'success' => true,
            'code'    => 200,
            'data'    => $updated,
            'message' => 'Appointment status updated to ' . $status
        ];
    }

    /**
     * Get upcoming appointments.
     */
    public static function getUpcomingAppointments(object|array $user): array
    {
        $userId = self::getUserId($user);
        $roles  = self::getUserRoles($user);

        $patientFilter  = null;
        $providerFilter = null;

        if (in_array('Patient', $roles, true) && count($roles) === 1) {
            $patientFilter = $userId;
        } elseif (in_array('Provider', $roles, true) && !in_array('Admin', $roles, true)) {
            $providerFilter = $userId;
        }

        $appointments = AppointmentRepository::getUpcoming($user, $patientFilter, $providerFilter);

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
    public static function getAppointmentDetail(object|array $user, int $appointmentId): array
    {
        $appointment = AppointmentRepository::findById($user, $appointmentId);

        if (!$appointment) {
            return [
                'success' => false,
                'code'    => 404,
                'message' => 'Appointment not found'
            ];
        }

        $userId = self::getUserId($user);
        $roles  = self::getUserRoles($user);

        if (self::isPatientOnly($roles) && (int) $appointment['patient_id'] !== $userId) {
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
    public static function listAppointments(object|array $user, array $filters): array
    {
        $userId = self::getUserId($user);
        $roles  = self::getUserRoles($user);

        if (self::isPatientOnly($roles)) {
            $filters['patient_id'] = $userId;
        } elseif (in_array('Provider', $roles, true) && !in_array('Admin', $roles, true)) {
            if (empty($filters['patient_id'])) {
                $filters['provider_id'] = $userId;
            }
        }

        $appointments = AppointmentRepository::listAll($user, $filters);

        return [
            'success' => true,
            'code'    => 200,
            'data'    => $appointments,
            'message' => 'Appointments retrieved successfully'
        ];
    }

    private static function isPatientOnly(array $roles): bool
    {
        return in_array('Patient', $roles, true) &&
               !in_array('Admin', $roles, true) &&
               !in_array('Provider', $roles, true) &&
               !in_array('Nurse', $roles, true);
    }

    private static function canModifyAppointment(object|array $user, array $appointment): bool
    {
        $userId = self::getUserId($user);
        $roles  = self::getUserRoles($user);

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
