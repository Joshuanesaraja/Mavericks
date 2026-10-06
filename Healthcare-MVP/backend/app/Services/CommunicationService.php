<?php

require_once __DIR__ . '/../Repositories/NoteRepository.php';
require_once __DIR__ . '/../Repositories/MessageRepository.php';
require_once __DIR__ . '/../Repositories/AppointmentRepository.php';
require_once __DIR__ . '/../Security/AES.php';

class CommunicationService
{
    private static function getUserId(
        object|array $user
    ): int {
        if (is_object($user)) {
            return (int) (
                $user->sub ??
                $user->id ??
                0
            );
        }

        return (int) (
            $user['userId'] ??
            $user['user_id'] ??
            $user['id'] ??
            0
        );
    }

    private static function getUserRoles(
        object|array $user
    ): array {
        if (is_object($user)) {
            return (array) (
                $user->roles ?? []
            );
        }

        return (array) (
            $user['roles'] ?? []
        );
    }

    /**
     * Communication is ONLY for Provider and Nurse.
     */
    private static function canUseCommunication(
        object|array $user
    ): bool {
        $roles =
            self::getUserRoles($user);

        return
            in_array(
                'Provider',
                $roles,
                true
            )
            ||
            in_array(
                'Nurse',
                $roles,
                true
            );
    }

    /**
     * Appointment communication access.
     *
     * Provider:
     *   Only appointments assigned to that Provider.
     *
     * Nurse:
     *   Current appointment model does not contain
     *   nurse_id. Existing AppointmentService treats
     *   Nurse as an authorized appointment staff role,
     *   therefore Nurse can access appointment
     *   communication.
     */
    private static function canAccessAppointment(
        object|array $user,
        array $appointment
    ): bool {
        $userId =
            self::getUserId($user);

        $roles =
            self::getUserRoles($user);

        if (
            in_array(
                'Provider',
                $roles,
                true
            )
        ) {
            return
                (int) $appointment['provider_id']
                === $userId;
        }

        if (
            in_array(
                'Nurse',
                $roles,
                true
            )
        ) {
            return true;
        }

        return false;
    }

    /**
     * Create an encrypted appointment note.
     *
     * ONLY Provider and Nurse.
     */
    public static function createNote(
        object|array $user,
        array $input
    ): array {
        if (
            !self::canUseCommunication(
                $user
            )
        ) {
            return [
                'success' => false,
                'code' => 403,
                'message' =>
                    'Forbidden: Only Providers and Nurses can use appointment communication'
            ];
        }

        $userId =
            self::getUserId($user);

        $appointmentId =
            (int) (
                $input['appointment_id'] ??
                0
            );

        $content =
            trim(
                $input['content'] ??
                ''
            );

        if (
            $appointmentId <= 0 ||
            $content === ''
        ) {
            return [
                'success' => false,
                'code' => 400,
                'message' =>
                    'appointment_id and content are required'
            ];
        }

        $appointment =
            AppointmentRepository::findById(
                $user,
                $appointmentId
            );

        if (!$appointment) {
            return [
                'success' => false,
                'code' => 404,
                'message' =>
                    'Appointment not found'
            ];
        }

        if (
            !self::canAccessAppointment(
                $user,
                $appointment
            )
        ) {
            return [
                'success' => false,
                'code' => 403,
                'message' =>
                    'Forbidden: You do not have access to communication for this appointment'
            ];
        }

        $encryptedContent =
            AES::encrypt(
                $content
            );

        $noteId =
            NoteRepository::create(
                $user,
                [
                    'appointment_id' =>
                        $appointmentId,

                    'user_id' =>
                        $userId,

                    'encrypted_content' =>
                        $encryptedContent
                ]
            );

        return [
            'success' => true,
            'code' => 201,

            'data' => [
                'id' =>
                    $noteId,

                'appointment_id' =>
                    $appointmentId,

                'user_id' =>
                    $userId,

                'content' =>
                    $content,

                'created_at' =>
                    date(
                        'Y-m-d H:i:s'
                    )
            ],

            'message' =>
                'Appointment note created successfully'
        ];
    }

    /**
     * Read appointment notes.
     *
     * ONLY Provider and Nurse.
     */
    public static function getNotes(
        object|array $user,
        int $appointmentId
    ): array {
        if (
            !self::canUseCommunication(
                $user
            )
        ) {
            return [
                'success' => false,
                'code' => 403,
                'message' =>
                    'Forbidden: Only Providers and Nurses can view appointment notes'
            ];
        }

        if ($appointmentId <= 0) {
            return [
                'success' => false,
                'code' => 400,
                'message' =>
                    'appointment_id is required'
            ];
        }

        $appointment =
            AppointmentRepository::findById(
                $user,
                $appointmentId
            );

        if (!$appointment) {
            return [
                'success' => false,
                'code' => 404,
                'message' =>
                    'Appointment not found'
            ];
        }

        if (
            !self::canAccessAppointment(
                $user,
                $appointment
            )
        ) {
            return [
                'success' => false,
                'code' => 403,
                'message' =>
                    'Forbidden: You do not have access to notes for this appointment'
            ];
        }

        $notes =
            NoteRepository::getByAppointment(
                $user,
                $appointmentId
            );

        foreach (
            $notes as &$note
        ) {
            try {
                $note['content'] =
                    AES::decrypt(
                        $note['encrypted_content']
                    );
            } catch (
                Throwable $e
            ) {
                $note['content'] =
                    '[Decryption Error]';
            }

            unset(
                $note['encrypted_content']
            );
        }

        return [
            'success' => true,
            'code' => 200,
            'data' => $notes,
            'message' =>
                'Appointment notes retrieved successfully'
        ];
    }

    /**
     * Send appointment message.
     *
     * Communication is Provider/Nurse only.
     *
     * Because the current appointment model has no
     * nurse_id, receiver_id must be explicitly supplied
     * for Provider/Nurse messaging.
     */
    public static function sendMessage(
        object|array $user,
        array $input
    ): array {
        if (
            !self::canUseCommunication(
                $user
            )
        ) {
            return [
                'success' => false,
                'code' => 403,
                'message' =>
                    'Forbidden: Only Providers and Nurses can send messages'
            ];
        }

        $senderId =
            self::getUserId($user);

        $appointmentId =
            (int) (
                $input['appointment_id'] ??
                0
            );

        $receiverId =
            (int) (
                $input['receiver_id'] ??
                0
            );

        $content =
            trim(
                $input['content'] ??
                ''
            );

        if (
            $appointmentId <= 0 ||
            $content === ''
        ) {
            return [
                'success' => false,
                'code' => 400,
                'message' =>
                    'appointment_id and content are required'
            ];
        }

        $appointment =
            AppointmentRepository::findById(
                $user,
                $appointmentId
            );

        if (!$appointment) {
            return [
                'success' => false,
                'code' => 404,
                'message' =>
                    'Appointment not found'
            ];
        }

        if (
            !self::canAccessAppointment(
                $user,
                $appointment
            )
        ) {
            return [
                'success' => false,
                'code' => 403,
                'message' =>
                    'Forbidden: You do not have access to messages for this appointment'
            ];
        }

        /*
         * Never silently send Provider/Nurse
         * communication to a Patient.
         */
        if ($receiverId <= 0) {
            return [
                'success' => false,
                'code' => 400,
                'message' =>
                    'receiver_id is required for Provider/Nurse communication'
            ];
        }

        if (
            $receiverId ===
            $senderId
        ) {
            return [
                'success' => false,
                'code' => 400,
                'message' =>
                    'You cannot send a message to yourself'
            ];
        }

        /*
         * Receiver must be Provider or Nurse.
         */
        $db = Database::tenant(
            (string) (
                is_object($user)
                    ? $user->tenant_db_name
                    : $user['tenant_db_name']
            ),
            is_object($user)
                ? ($user->tenant_db_user ?? null)
                : ($user['tenant_db_user'] ?? null),
            is_object($user)
                ? ($user->tenant_db_password ?? null)
                : ($user['tenant_db_password'] ?? null)
        );

        $receiverSql = "
            SELECT
                u.id,
                u.name,
                r.name AS role_name
            FROM users u
            INNER JOIN user_roles ur
                ON ur.user_id = u.id
            INNER JOIN roles r
                ON r.id = ur.role_id
            WHERE u.id = :receiver_id
              AND u.status = 'active'
              AND r.name IN ('Provider', 'Nurse')
            LIMIT 1
        ";

        $receiverStmt =
            $db->prepare(
                $receiverSql
            );

        $receiverStmt->execute([
            'receiver_id' =>
                $receiverId
        ]);

        $receiver =
            $receiverStmt->fetch(
                PDO::FETCH_ASSOC
            );

        if (!$receiver) {
            return [
                'success' => false,
                'code' => 400,
                'message' =>
                    'Receiver must be an active Provider or Nurse'
            ];
        }

        $encryptedContent =
            AES::encrypt(
                $content
            );

        $messageId =
            MessageRepository::create(
                $user,
                [
                    'appointment_id' =>
                        $appointmentId,

                    'sender_id' =>
                        $senderId,

                    'receiver_id' =>
                        $receiverId,

                    'encrypted_content' =>
                        $encryptedContent
                ]
            );

        return [
            'success' => true,
            'code' => 201,

            'data' => [
                'id' =>
                    $messageId,

                'appointment_id' =>
                    $appointmentId,

                'sender_id' =>
                    $senderId,

                'receiver_id' =>
                    $receiverId,

                'content' =>
                    $content,

                'created_at' =>
                    date(
                        'Y-m-d H:i:s'
                    )
            ],

            'message' =>
                'Message sent successfully'
        ];
    }

    /**
     * Get appointment message history.
     *
     * ONLY Provider and Nurse.
     */
    public static function getMessageHistory(
        object|array $user,
        int $appointmentId
    ): array {
        if (
            !self::canUseCommunication(
                $user
            )
        ) {
            return [
                'success' => false,
                'code' => 403,
                'message' =>
                    'Forbidden: Only Providers and Nurses can view appointment messages'
            ];
        }

        if ($appointmentId <= 0) {
            return [
                'success' => false,
                'code' => 400,
                'message' =>
                    'appointment_id is required'
            ];
        }

        $appointment =
            AppointmentRepository::findById(
                $user,
                $appointmentId
            );

        if (!$appointment) {
            return [
                'success' => false,
                'code' => 404,
                'message' =>
                    'Appointment not found'
            ];
        }

        if (
            !self::canAccessAppointment(
                $user,
                $appointment
            )
        ) {
            return [
                'success' => false,
                'code' => 403,
                'message' =>
                    'Forbidden: You do not have access to messages for this appointment'
            ];
        }

        $messages =
            MessageRepository::getByAppointment(
                $user,
                $appointmentId
            );

        foreach (
            $messages as &$message
        ) {
            try {
                $message['content'] =
                    AES::decrypt(
                        $message['encrypted_content']
                    );
            } catch (
                Throwable $e
            ) {
                $message['content'] =
                    '[Decryption Error]';
            }

            unset(
                $message['encrypted_content']
            );
        }

        return [
            'success' => true,
            'code' => 200,
            'data' => $messages,
            'message' =>
                'Message history retrieved successfully'
        ];
    }
}