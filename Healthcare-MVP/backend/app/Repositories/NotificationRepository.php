<?php

require_once __DIR__ . '/../Config/database.php';

class NotificationRepository
{
    /**
     * Get the tenant database connection.
     */
    private static function db(object|array $auth): PDO
    {
        if (is_object($auth)) {
            $dbName = $auth->tenant_db_name ?? null;
            $dbUser = $auth->tenant_db_user ?? null;
            $dbPass = $auth->tenant_db_password ?? null;
        } else {
            $dbName = $auth['tenant_db_name'] ?? null;
            $dbUser = $auth['tenant_db_user'] ?? null;
            $dbPass = $auth['tenant_db_password'] ?? null;
        }

        if (empty($dbName)) {
            throw new RuntimeException(
                'Tenant database is not configured.'
            );
        }

        return Database::tenant(
            (string) $dbName,
            $dbUser,
            $dbPass
        );
    }

    /**
     * Create a notification.
     */
    public static function create(
        object|array $auth,
        array $data
    ): int {
        $db = self::db($auth);

        $sql = 'INSERT INTO notifications (
                    recipient_id,
                    type,
                    title,
                    message,
                    reference_type,
                    reference_id,
                    is_read
                ) VALUES (
                    :recipient_id,
                    :type,
                    :title,
                    :message,
                    :reference_type,
                    :reference_id,
                    0
                )';

        $stmt = $db->prepare($sql);

        $stmt->execute([
            'recipient_id'   => (int) $data['recipient_id'],
            'type'           => $data['type'] ?? 'system',
            'title'          => $data['title'],
            'message'        => $data['message'],
            'reference_type' => $data['reference_type'] ?? null,
            'reference_id'   => $data['reference_id'] ?? null,
        ]);

        return (int) $db->lastInsertId();
    }

    /**
     * Get notifications for the logged-in user.
     */
    public static function getForUser(
        object|array $auth,
        int $recipientId
    ): array {
        $db = self::db($auth);

        $sql = 'SELECT
                    id,
                    recipient_id,
                    type,
                    title,
                    message,
                    reference_type,
                    reference_id,
                    is_read,
                    created_at,
                    read_at
                FROM notifications
                WHERE recipient_id = :recipient_id
                ORDER BY created_at DESC';

        $stmt = $db->prepare($sql);

        $stmt->execute([
            'recipient_id' => $recipientId,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get unread notifications for the logged-in user.
     */
    public static function getUnread(
        object|array $auth,
        int $recipientId
    ): array {
        $db = self::db($auth);

        $sql = 'SELECT
                    id,
                    recipient_id,
                    type,
                    title,
                    message,
                    reference_type,
                    reference_id,
                    is_read,
                    created_at,
                    read_at
                FROM notifications
                WHERE recipient_id = :recipient_id
                  AND is_read = 0
                ORDER BY created_at DESC';

        $stmt = $db->prepare($sql);

        $stmt->execute([
            'recipient_id' => $recipientId,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Mark one notification as read.
     *
     * Ownership is checked using recipient_id.
     */
    public static function markAsRead(
        object|array $auth,
        int $notificationId,
        int $recipientId
    ): bool {
        $db = self::db($auth);

        $sql = 'UPDATE notifications
                SET is_read = 1,
                    read_at = NOW()
                WHERE id = :id
                  AND recipient_id = :recipient_id';

        $stmt = $db->prepare($sql);

        $stmt->execute([
            'id'           => $notificationId,
            'recipient_id' => $recipientId,
        ]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Mark all notifications as read for a user.
     */
    public static function markAllAsRead(
        object|array $auth,
        int $recipientId
    ): bool {
        $db = self::db($auth);

        $sql = 'UPDATE notifications
                SET is_read = 1,
                    read_at = NOW()
                WHERE recipient_id = :recipient_id
                  AND is_read = 0';

        $stmt = $db->prepare($sql);

        return $stmt->execute([
            'recipient_id' => $recipientId,
        ]);
    }

    /**
     * Count unread notifications.
     */
    public static function countUnread(
        object|array $auth,
        int $recipientId
    ): int {
        $db = self::db($auth);

        $sql = 'SELECT COUNT(*)
                FROM notifications
                WHERE recipient_id = :recipient_id
                  AND is_read = 0';

        $stmt = $db->prepare($sql);

        $stmt->execute([
            'recipient_id' => $recipientId,
        ]);

        return (int) $stmt->fetchColumn();
    }
}