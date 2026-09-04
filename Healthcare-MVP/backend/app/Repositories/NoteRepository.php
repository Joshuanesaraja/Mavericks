<?php

require_once __DIR__ . '/../Config/database.php';

class NoteRepository
{
    public static function create(array $data): int
    {
        $db = Database::connect();

        $sql = 'INSERT INTO appointment_notes
                (tenant_id, appointment_id, user_id, encrypted_content)
                VALUES
                (:tenant_id, :appointment_id, :user_id, :encrypted_content)';

        $stmt = $db->prepare($sql);
        $stmt->execute([
            'tenant_id'         => $data['tenant_id'],
            'appointment_id'    => $data['appointment_id'],
            'user_id'           => $data['user_id'],
            'encrypted_content' => $data['encrypted_content'],
        ]);

        return (int) $db->lastInsertId();
    }

    public static function getByAppointment(int $appointmentId, int $tenantId): array
    {
        $db = Database::connect();

        $sql = 'SELECT n.*, u.name AS author_name, u.email AS author_email
                FROM appointment_notes n
                INNER JOIN users u ON u.id = n.user_id
                WHERE n.appointment_id = :appointment_id AND n.tenant_id = :tenant_id
                ORDER BY n.created_at ASC';

        $stmt = $db->prepare($sql);
        $stmt->execute([
            'appointment_id' => $appointmentId,
            'tenant_id'      => $tenantId,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
