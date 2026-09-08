<?php

require_once __DIR__ . '/../Repositories/NoteRepository.php';
require_once __DIR__ . '/../Repositories/MessageRepository.php';
require_once __DIR__ . '/../Repositories/AppointmentRepository.php';
require_once __DIR__ . '/../Security/AES.php';

class CommunicationService
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
     * Create an encrypted appointment note (Provider/Nurse/Admin).
     */
    public static function createNote(object|array $user, array $input): array
    {
        $userId        = self::getUserId($user);
        $roles         = self::getUserRoles($user);
        $appointmentId = (int) ($input['appointment_id'] ?? 0);
        $content       = trim($input['content'] ?? '');

        if ($appointmentId <= 0 || empty($content)) {
            return [
                'success' => false,
                'code'    => 400,
                'message' => 'appointment_id and content are required'
            ];
        }

        if (!in_array('Provider', $roles, true) && !in_array('Nurse', $roles, true) && !in_array('Admin', $roles, true)) {
            return [
                'success' => false,
                'code'    => 403,
                'message' => 'Forbidden: Only Providers, Nurses, and Admins can create appointment notes'
            ];
        }

        $appointment = AppointmentRepository::findById($user, $appointmentId);
        if (!$appointment) {
            return [
                'success' => false,
                'code'    => 404,
                'message' => 'Appointment not found'
            ];
        }

        $encryptedContent = AES::encrypt($content);

        $noteId = NoteRepository::create($user, [
            'appointment_id'    => $appointmentId,
            'user_id'           => $userId,
            'encrypted_content' => $encryptedContent,
        ]);

        return [
            'success' => true,
            'code'    => 201,
            'data'    => [
                'id'             => $noteId,
                'appointment_id' => $appointmentId,
                'content'        => $content,
                'created_at'     => date('Y-m-d H:i:s')
            ],
            'message' => 'Appointment note created and encrypted successfully'
        ];
    }

    /**
     * Get notes for an appointment with decrypted content.
     */
    public static function getNotes(object|array $user, int $appointmentId): array
    {
        if ($appointmentId <= 0) {
            return [
                'success' => false,
                'code'    => 400,
                'message' => 'appointment_id is required'
            ];
        }

        $appointment = AppointmentRepository::findById($user, $appointmentId);
        if (!$appointment) {
            return [
                'success' => false,
                'code'    => 404,
                'message' => 'Appointment not found'
            ];
        }

        $notes = NoteRepository::getByAppointment($user, $appointmentId);

        foreach ($notes as &$note) {
            try {
                $note['content'] = AES::decrypt($note['encrypted_content']);
            } catch (Throwable $e) {
                $note['content'] = '[Decryption Error]';
            }
            unset($note['encrypted_content']);
        }

        return [
            'success' => true,
            'code'    => 200,
            'data'    => $notes,
            'message' => 'Appointment notes retrieved'
        ];
    }

    /**
     * Send an encrypted message linked to an appointment.
     * If you are logged in as User 2, then user2 is the sender
     * receiver_id  ← taken from the request
     */
    public static function sendMessage(object|array $user, array $input): array
    {
        $senderId      = self::getUserId($user);
        $appointmentId = (int) ($input['appointment_id'] ?? 0);
        $receiverId    = (int) ($input['receiver_id'] ?? 0);
        $content       = trim($input['content'] ?? '');

        if ($appointmentId <= 0 || empty($content)) {
            return [
                'success' => false,
                'code'    => 400,
                'message' => 'appointment_id and content are required'
            ];
        }

        $appointment = AppointmentRepository::findById($user, $appointmentId);
        if (!$appointment) {
            return [
                'success' => false,
                'code'    => 404,
                'message' => 'Appointment not found'
            ];
        }

        if ($receiverId <= 0) {
            if ($senderId === (int) $appointment['patient_id']) {
                $receiverId = (int) $appointment['provider_id'];
            } else {
                $receiverId = (int) $appointment['patient_id'];
            }
        }

        $encryptedContent = AES::encrypt($content);

        $messageId = MessageRepository::create($user, [
            'appointment_id'    => $appointmentId,
            'sender_id'         => $senderId,
            'receiver_id'       => $receiverId,
            'encrypted_content' => $encryptedContent,
        ]);

        return [
            'success' => true,
            'code'    => 201,
            'data'    => [
                'id'             => $messageId,
                'appointment_id' => $appointmentId,
                'sender_id'      => $senderId,
                'receiver_id'    => $receiverId,
                'content'        => $content,
                'created_at'     => date('Y-m-d H:i:s')
            ],
            'message' => 'Message sent successfully'
        ];
    }

    /**
     * Get message history for an appointment with decrypted content.
     */
    public static function getMessageHistory(object|array $user, int $appointmentId): array
    {
        $userId = self::getUserId($user);
        $roles  = self::getUserRoles($user);

        if ($appointmentId <= 0) {
            return [
                'success' => false,
                'code'    => 400,
                'message' => 'appointment_id is required'
            ];
        }

        $appointment = AppointmentRepository::findById($user, $appointmentId);
        if (!$appointment) {
            return [
                'success' => false,
                'code'    => 404,
                'message' => 'Appointment not found'
            ];
        }

        if (
            !in_array('Admin', $roles, true) &&
            $userId !== (int) $appointment['patient_id'] &&
            $userId !== (int) $appointment['provider_id']
        ) {
            return [
                'success' => false,
                'code'    => 403,
                'message' => 'Forbidden: You do not have access to messages for this appointment'
            ];
        }

        $messages = MessageRepository::getByAppointment($user, $appointmentId);

        foreach ($messages as &$msg) {
            try {
                $msg['content'] = AES::decrypt($msg['encrypted_content']);
            } catch (Throwable $e) {
                $msg['content'] = '[Decryption Error]';
            }
            unset($msg['encrypted_content']);
        }

        return [
            'success' => true,
            'code'    => 200,
            'data'    => $messages,
            'message' => 'Message history retrieved'
        ];
    }
}
