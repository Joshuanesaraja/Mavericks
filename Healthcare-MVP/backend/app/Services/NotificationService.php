<?php

require_once __DIR__ . '/../Repositories/NotificationRepository.php';

class NotificationService
{
    /**
     * Create an appointment notification for the assigned provider.
     */
    public static function appointmentCreated(
        object|array $auth,
        array $appointment
    ): int {
        $providerId = (int) ($appointment['provider_id'] ?? 0);
        $appointmentId = (int) ($appointment['id'] ?? 0);

        if ($providerId <= 0) {
            throw new InvalidArgumentException(
                'Provider id is required for appointment notification.'
            );
        }

        if ($appointmentId <= 0) {
            throw new InvalidArgumentException(
                'Appointment id is required for appointment notification.'
            );
        }

        $providerName = trim(
            (string) ($appointment['provider_name'] ?? 'Provider')
        );

        $startAt = $appointment['start_at'] ?? null;

        if ($startAt) {
            $dateTime = date(
                'F j, Y \a\t g:i A',
                strtotime($startAt)
            );
        } else {
            $dateTime = 'the scheduled time';
        }

        $title = 'New Appointment Assigned';

        $message =
            'A new patient appointment has been assigned to you' .
            ($providerName !== ''
                ? ''
                : '') .
            ' for ' .
            $dateTime .
            '. Please review the appointment details.';

        return NotificationRepository::create(
            $auth,
            [
                'recipient_id'   => $providerId,
                'type'           => 'appointment',
                'title'          => $title,
                'message'        => $message,
                'reference_type' => 'appointment',
                'reference_id'   => $appointmentId,
            ]
        );
    }

    public static function list(
        object|array $auth,
        int $userId
    ): array {
        return NotificationRepository::getForUser(
            $auth,
            $userId
        );
    }

    public static function unread(
        object|array $auth,
        int $userId
    ): array {
        return NotificationRepository::getUnread(
            $auth,
            $userId
        );
    }

    public static function markAsRead(
        object|array $auth,
        int $notificationId,
        int $userId
    ): bool {
        return NotificationRepository::markAsRead(
            $auth,
            $notificationId,
            $userId
        );
    }

    public static function markAllAsRead(
        object|array $auth,
        int $userId
    ): bool {
        return NotificationRepository::markAllAsRead(
            $auth,
            $userId
        );
    }

    public static function unreadCount(
        object|array $auth,
        int $userId
    ): int {
        return NotificationRepository::countUnread(
            $auth,
            $userId
        );
    }
}