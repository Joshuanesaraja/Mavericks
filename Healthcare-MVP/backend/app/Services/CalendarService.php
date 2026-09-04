<?php

require_once __DIR__ . '/../Repositories/AppointmentRepository.php';
require_once __DIR__ . '/../Repositories/NoteRepository.php';

class CalendarService
{
    /**
     * Get appointments for a single specific date.
     */
    public static function getAppointmentsByDate(object $auth, string $dateStr): array
    {
        $dateStr = trim($dateStr);
        if (empty($dateStr) || strtotime($dateStr) === false) {
            $dateStr = date('Y-m-d');
        }

        $startDate = $dateStr . ' 00:00:00';
        $endDate   = $dateStr . ' 23:59:59';

        return self::fetchCalendarGrid($auth, $startDate, $endDate);
    }

    /**
     * Get appointments for a date range (e.g. weekly or monthly view).
     */
    public static function getAppointmentsByRange(
        object $auth,
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

        return self::fetchCalendarGrid($auth, $startDate, $endDate);
    }

    /**
     * Core calendar query engine with tooltip construction & RBAC.
     */
    private static function fetchCalendarGrid(
        object $auth,
        string $startDate,
        string $endDate
    ): array {
        $tenantId = (int) $auth->tenant_id;
        $userId   = (int) $auth->sub;
        $roles    = (array) ($auth->roles ?? []);

        $filters = [
            'date_from' => $startDate,
            'date_to'   => $endDate,
        ];

        // RBAC Scoping
        if (in_array('Patient', $roles, true) && count($roles) === 1) {
            $filters['patient_id'] = $userId;
        } elseif (in_array('Provider', $roles, true) && !in_array('Admin', $roles, true)) {
            $filters['provider_id'] = $userId;
        }

        $appointments = AppointmentRepository::listAll($tenantId, $filters);

        $grid = [];
        foreach ($appointments as $item) {
            $startTs = strtotime($item['start_at']);
            $endTs   = strtotime($item['end_at']);
            $durationMinutes = max(0, (int) round(($endTs - $startTs) / 60));

            $notes = NoteRepository::getByAppointment((int) $item['id'], $tenantId);

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
}
