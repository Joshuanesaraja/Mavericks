<?php

require_once __DIR__ . '/../Repositories/AppointmentRepository.php';
require_once __DIR__ . '/../Repositories/NoteRepository.php';

class CalendarService
{
    private static function getUserId(object|array $user): int
    {
        if (is_object($user)) {
            return (int) ($user->sub ?? $user->id ?? 0);
        }
        return (int) ($user['userId'] ?? $user['user_id'] ?? $user['id'] ?? 0);
    }

    private static function getUserRoles(object|array $user): array
    {
        if (is_object($user)) {
            return (array) ($user->roles ?? []);
        }
        return (array) ($user['roles'] ?? []);
    }

    /**
     * Get appointments for a single specific date.
     */
    public static function getAppointmentsByDate(object|array $user, string $dateStr): array
    {
        $dateStr = trim($dateStr);
        if (empty($dateStr) || strtotime($dateStr) === false) {
            $dateStr = date('Y-m-d');
        }

        $startDate = $dateStr . ' 00:00:00';
        $endDate   = $dateStr . ' 23:59:59';

        return self::fetchCalendarGrid($user, $startDate, $endDate);
    }

    /**
     * Get appointments for a date range (e.g. weekly or monthly view).
     */
    public static function getAppointmentsByRange(
        object|array $user,
        string $startDateStr,
        string $endDateStr
    ): array {
        $startTs = strtotime($startDateStr);
        $endTs   = strtotime($endDateStr);

        if ($startTs === false || $endTs === false) {
            return [
                'success' => false,
                'code'    => 400,
                'message' => 'Invalid start_date or end_date format. Use YYYY-MM-DD'
            ];
        }

        if ($endTs < $startTs) {
            return [
                'success' => false,
                'code'    => 400,
                'message' => 'end_date must be greater than or equal to start_date'
            ];
        }

        $startDate = date('Y-m-d', $startTs) . ' 00:00:00';
        $endDate   = date('Y-m-d', $endTs) . ' 23:59:59';

        return self::fetchCalendarGrid($user, $startDate, $endDate);
    }

    /**
     * Core calendar query engine with tooltip construction & RBAC.
     */
    private static function fetchCalendarGrid(
        object|array $user,
        string $startDate,
        string $endDate
    ): array {
        $userId = self::getUserId($user);
        $roles  = self::getUserRoles($user);

        $filters = [
            'date_from' => $startDate,
            'date_to'   => $endDate,
        ];

        if (in_array('Patient', $roles, true) && count($roles) === 1) {
            $filters['patient_id'] = $userId;
        } elseif (in_array('Provider', $roles, true) && !in_array('Admin', $roles, true)) {
            $filters['provider_id'] = $userId;
        }

        $appointments = AppointmentRepository::listAll($user, $filters);

        $grid = [];
        foreach ($appointments as $item) {
            $startTs = strtotime($item['start_at']);
            $endTs   = strtotime($item['end_at']);
            $durationMinutes = max(0, (int) round(($endTs - $startTs) / 60));

            $notes = NoteRepository::getByAppointment($user, (int) $item['id']);

            $tooltip = [
                'title'            => 'Appointment #' . $item['id'],
                'patient_name'     => $item['patient_name'] ?? 'Patient ID ' . $item['patient_id'],
                'provider_name'    => $item['provider_name'] ?? 'Provider ID ' . $item['provider_id'],
                'date'             => date('Y-m-d', $startTs),
                'start_time'       => date('h:i A', $startTs),
                'end_time'         => date('h:i A', $endTs),
                'duration_minutes' => $durationMinutes,
                'status'           => $item['status'],
                'reason'           => $item['reason'] ?? 'No reason provided',
                'notes_count'      => count($notes),
            ];

            $item['tooltip'] = $tooltip;
            $grid[] = $item;
        }

        return [
            'success' => true,
            'code'    => 200,
            'data'    => [
                'range'        => [
                    'start' => substr($startDate, 0, 10),
                    'end'   => substr($endDate, 0, 10),
                ],
                'count'        => count($grid),
                'appointments' => $grid,
            ],
            'message' => 'Calendar grid loaded successfully'
        ];
    }

    public static function getAvailability(object $auth): array
    {
        $providerId = isset($_GET['provider_id'])
            ? (int) $_GET['provider_id']
            : 0;

        $date = trim($_GET['date'] ?? '');

        $startTime = trim($_GET['start_time'] ?? '09:00');
        $endTime = trim($_GET['end_time'] ?? '17:00');

        $slotMinutes = isset($_GET['slot_minutes'])
            ? (int) $_GET['slot_minutes']
            : 30;

        if ($providerId <= 0) {
            return [
                'success' => false,
                'code' => 400,
                'message' => 'provider_id is required'
            ];
        }

        if (
            !$date ||
            !DateTime::createFromFormat('Y-m-d', $date)
        ) {
            return [
                'success' => false,
                'code' => 400,
                'message' => 'Valid date is required'
            ];
        }

        if ($slotMinutes <= 0) {
            return [
                'success' => false,
                'code' => 400,
                'message' => 'slot_minutes must be greater than 0'
            ];
        }

        /*
     * RBAC:
     * Admin can view availability for any provider.
     * Provider can view only their own availability.
     */
        $userId = self::getUserId($auth);
        $roles = self::getUserRoles($auth);

        $isAdmin = in_array('Admin', $roles, true);
        $isProvider = in_array('Provider', $roles, true);

        if ($isProvider && !$isAdmin && $providerId !== $userId) {
            return [
                'success' => false,
                'code' => 403,
                'message' => 'You can only view your own availability'
            ];
        }

        $appointments = AppointmentRepository::listAll(
            $auth,
            [
                'provider_id' => $providerId,
                'date_from' => $date . ' 00:00:00',
                'date_to' => $date . ' 23:59:59'
            ]
        );

        $availableSlots = [];

        $current = new DateTime($date . ' ' . $startTime);
        $end = new DateTime($date . ' ' . $endTime);

        while ($current < $end) {

            $slotStart = clone $current;
            $slotEnd = clone $current;
            $slotEnd->modify("+{$slotMinutes} minutes");

            if ($slotEnd > $end) {
                break;
            }

            $isAvailable = true;

            foreach ($appointments as $appointment) {

                if (
                    isset($appointment['status']) &&
                    in_array(
                        strtolower($appointment['status']),
                        ['cancelled', 'completed'],
                        true
                    )
                ) {
                    continue;
                }

                $appointmentStart = new DateTime($appointment['start_at']);
                $appointmentEnd = new DateTime($appointment['end_at']);

                if (
                    $slotStart < $appointmentEnd &&
                    $slotEnd > $appointmentStart
                ) {
                    $isAvailable = false;
                    break;
                }
            }

            if ($isAvailable) {
                $availableSlots[] = [
                    'start_time' => $slotStart->format('H:i'),
                    'end_time' => $slotEnd->format('H:i')
                ];
            }

            $current = $slotEnd;
        }

        return [
            'success' => true,
            'code' => 200,
            'data' => [
                'provider_id' => $providerId,
                'date' => $date,
                'working_hours' => [
                    'start_time' => $startTime,
                    'end_time' => $endTime
                ],
                'slot_minutes' => $slotMinutes,
                'available_slots' => $availableSlots
            ],
            'message' => 'Provider availability retrieved successfully'
        ];
    }
}
