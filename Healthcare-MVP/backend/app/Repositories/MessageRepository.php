<?php

require_once __DIR__ . '/../Config/database.php';

class MessageRepository
{
    /**
     * Get dynamic tenant database connection.
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

    public static function create(object|array $auth, array $data): int
    {
        $db = self::db($auth);

        $sql = 'INSERT INTO messages
                (appointment_id, sender_id, receiver_id, encrypted_content)
                VALUES
                (:appointment_id, :sender_id, :receiver_id, :encrypted_content)';

        $stmt = $db->prepare($sql);
        $stmt->execute([
            'appointment_id'    => $data['appointment_id'],
            'sender_id'         => $data['sender_id'],
            'receiver_id'       => $data['receiver_id'],
            'encrypted_content' => $data['encrypted_content'],
        ]);

        return (int) $db->lastInsertId();
    }

    public static function getByAppointment(object|array $auth, int $appointmentId): array
    {
        $db = self::db($auth);

        $sql = 'SELECT m.*,
                       su.name AS sender_name, su.email AS sender_email,
                       ru.name AS receiver_name, ru.email AS receiver_email
                FROM messages m
                LEFT JOIN users su ON su.id = m.sender_id
                LEFT JOIN users ru ON ru.id = m.receiver_id
                WHERE m.appointment_id = :appointment_id
                ORDER BY m.created_at ASC';

        $stmt = $db->prepare($sql);
        $stmt->execute(['appointment_id' => $appointmentId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
