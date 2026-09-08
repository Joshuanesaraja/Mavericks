<?php

require_once __DIR__ . '/../Config/database.php';

class NoteRepository
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

        $sql = 'INSERT INTO appointment_notes
                (appointment_id, user_id, encrypted_content)
                VALUES
                (:appointment_id, :user_id, :encrypted_content)';

        $stmt = $db->prepare($sql);
        $stmt->execute([
            'appointment_id'    => $data['appointment_id'],
            'user_id'           => $data['user_id'],
            'encrypted_content' => $data['encrypted_content'],
        ]);

        return (int) $db->lastInsertId();
    }

    public static function getByAppointment(object|array $auth, int $appointmentId): array
    {
        $db = self::db($auth);

        $sql = 'SELECT n.*, u.name AS author_name, u.email AS author_email
                FROM appointment_notes n
                INNER JOIN users u ON u.id = n.user_id
                WHERE n.appointment_id = :appointment_id
                ORDER BY n.created_at ASC';

        $stmt = $db->prepare($sql);
        $stmt->execute(['appointment_id' => $appointmentId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
