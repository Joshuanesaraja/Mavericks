<?php

require_once __DIR__ . '/../Repositories/NoteRepository.php';
require_once __DIR__ . '/../Repositories/MessageRepository.php';
require_once __DIR__ . '/../Repositories/AppointmentRepository.php';
require_once __DIR__ . '/../Security/AES.php';

class CommunicationService
{
    /**
     * Create an encrypted appointment note (Provider/Nurse/Admin).
     */
    public static function createNote(object $auth, array $input): array
    {
        $tenantId      = (int) $auth->tenant_id;
        $userId        = (int) $auth->sub;
        $roles         = (array) ($auth->roles ?? []);
        $appointmentId = (int) ($input['appointment_id'] ?? 0);
        $content       = trim($input['content'] ?? '');

        if ($appointmentId <= 0 || empty($content)) {
            return [
                'success' => false,
                'code'    => 400,
                'message' => 'appointment_id and content are required'
            ];
        }

        // RBAC Check
        if (!in_array('Provider', $roles, true) && !in_array('Nurse', $roles, true) && !in_array('Admin', $roles, true)) {
            return [
                'success' => false,
                'code'    => 403,
                'message' => 'Forbidden: Only Providers, Nurses, and Admins can create appointment notes'
            ];
        }

        $appointment = AppointmentRepository::findById($appointmentId, $tenantId);
        if (!$appointment) {
            return [
                'success' => false,
                'code'    => 404,
                'message' => 'Appointment not found'
            ];
        }

        $encryptedContent = AES::encrypt($content);

        $noteId = NoteRepository::create([
            'tenant_id'         => $tenantId,
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
    public static function getNotes(object $auth, int $appointmentId): array
    {
        $tenantId = (int) $auth->tenant_id;
        $roles    = (array) ($auth->roles ?? []);

        if ($appointmentId <= 0) {
            return [
                'success' => false,
                'code'    => 400,
                'message' => 'appointment_id is required'
            ];
        }

        $appointment = AppointmentRepository::findById($appointmentId, $tenantId);
        if (!$appointment) {
            return [
                'success' => false,
                'code'    => 404,
                'message' => 'Appointment not found'
            ];
        }

        $notes = NoteRepository::getByAppointment($appointmentId, $tenantId);

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
     */
    public static function sendMessage(object $auth, array $input): array
    {
        $tenantId      = (int) $auth->tenant_id;
        $senderId      = (int) $auth->sub;
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

        $appointment = AppointmentRepository::findById($appointmentId, $tenantId);
        if (!$appointment) {
            return [
                'success' => false,
                'code'    => 404,
                'message' => 'Appointment not found'
            ];
        }

        // Auto-resolve receiver if not supplied
        if ($receiverId <= 0) {
            if ($senderId === (int) $appointment['patient_id']) {
                $receiverId = (int) $appointment['provider_id'];
            } else {
                $receiverId = (int) $appointment['patient_id'];
            }
        }

        $encryptedContent = AES::encrypt($content);

        $messageId = MessageRepository::create([
            'tenant_id'         => $tenantId,
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
    public static function getMessageHistory(object $auth, int $appointmentId): array
    {
        $tenantId = (int) $auth->tenant_id;
        $userId   = (int) $auth->sub;
        $roles    = (array) ($auth->roles ?? []);

        if ($appointmentId <= 0) {
            return [
                'success' => false,
                'code'    => 400,
                'message' => 'appointment_id is required'
            ];
        }

        $appointment = AppointmentRepository::findById($appointmentId, $tenantId);
        if (!$appointment) {
            return [
                'success' => false,
                'code'    => 404,
                'message' => 'Appointment not found'
            ];
        }

        // RBAC Visibility Check
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

        $messages = MessageRepository::getByAppointment($appointmentId, $tenantId);

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
