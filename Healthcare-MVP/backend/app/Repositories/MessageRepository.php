<?php

require_once __DIR__ . '/../Config/database.php';

class MessageRepository
{
    public static function create(array $data): int
    {
        $db = Database::connect();

        $sql = 'INSERT INTO messages 
                (tenant_id, appointment_id, sender_id, receiver_id, encrypted_content)
                VALUES 
                (:tenant_id, :appointment_id, :sender_id, :receiver_id, :encrypted_content)';

        $stmt = $db->prepare($sql);
        $stmt->execute([
            'tenant_id'         => $data['tenant_id'],
            'appointment_id'    => $data['appointment_id'],
            'sender_id'         => $data['sender_id'],
            'receiver_id'       => $data['receiver_id'],
            'encrypted_content' => $data['encrypted_content'],
        ]);

        return (int) $db->lastInsertId();
    }

    public static function getByAppointment(int $appointmentId, int $tenantId): array
    {
        $db = Database::connect();

        $sql = 'SELECT m.*, 
                       su.name AS sender_name, su.email AS sender_email,
                       ru.name AS receiver_name, ru.email AS receiver_email
                FROM messages m
                LEFT JOIN users su ON su.id = m.sender_id
                LEFT JOIN users ru ON ru.id = m.receiver_id
                WHERE m.appointment_id = :appointment_id AND m.tenant_id = :tenant_id
                ORDER BY m.created_at ASC';

        $stmt = $db->prepare($sql);
        $stmt->execute([
            'appointment_id' => $appointmentId,
            'tenant_id'      => $tenantId,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
