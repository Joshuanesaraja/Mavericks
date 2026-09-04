<?php

require_once __DIR__ . '/../Services/CommunicationService.php';
require_once __DIR__ . '/../Helpers/Response.php';

class CommunicationController
{
    /**
     * POST /notes/create
     * POST /notes
     *
     * Router handles:
     * - CSRF validation
     * - AES decryption
     * - JWT authentication
     * - Role validation
     */
    public static function createNote(object $auth, array $input): void
    {
        try {
            $result = CommunicationService::createNote(
                $auth,
                $input
            );

            if ($result['success']) {
                Response::success(
                    $result['data'] ?? null,
                    $result['message'] ?? 'Appointment note created successfully',
                    $result['code'] ?? 201
                );
            } else {
                Response::error(
                    $result['message'] ?? 'Failed to create appointment note',
                    $result['code'] ?? 400
                );
            }
        } catch (Throwable $e) {
            Response::error(
                $e->getMessage(),
                400
            );
        }
    }

    /**
     * GET /notes?appointment_id=X
     */
    public static function getNotes(object $auth): void
    {
        $appointmentId = (int) (
            $_GET['appointment_id']
            ?? 0
        );

        try {
            $result = CommunicationService::getNotes(
                $auth,
                $appointmentId
            );

            if ($result['success']) {
                Response::success(
                    $result['data'] ?? null,
                    $result['message'] ?? 'Appointment notes retrieved',
                    $result['code'] ?? 200
                );
            } else {
                Response::error(
                    $result['message'] ?? 'Failed to retrieve appointment notes',
                    $result['code'] ?? 400
                );
            }
        } catch (Throwable $e) {
            Response::error(
                $e->getMessage(),
                400
            );
        }
    }

    /**
     * POST /messages/send
     * POST /messages
     *
     * Router handles:
     * - CSRF validation
     * - AES decryption
     * - JWT authentication
     */
    public static function sendMessage(object $auth, array $input): void
    {
        try {
            $result = CommunicationService::sendMessage(
                $auth,
                $input
            );

            if ($result['success']) {
                Response::success(
                    $result['data'] ?? null,
                    $result['message'] ?? 'Message sent successfully',
                    $result['code'] ?? 201
                );
            } else {
                Response::error(
                    $result['message'] ?? 'Failed to send message',
                    $result['code'] ?? 400
                );
            }
        } catch (Throwable $e) {
            Response::error(
                $e->getMessage(),
                400
            );
        }
    }

    /**
     * GET /messages/history?appointment_id=X
     */
    public static function getMessageHistory(object $auth): void
    {
        $appointmentId = (int) (
            $_GET['appointment_id']
            ?? 0
        );

        try {
            $result = CommunicationService::getMessageHistory(
                $auth,
                $appointmentId
            );

            if ($result['success']) {
                Response::success(
                    $result['data'] ?? null,
                    $result['message'] ?? 'Message history retrieved',
                    $result['code'] ?? 200
                );
            } else {
                Response::error(
                    $result['message'] ?? 'Failed to retrieve message history',
                    $result['code'] ?? 400
                );
            }
        } catch (Throwable $e) {
            Response::error(
                $e->getMessage(),
                400
            );
        }
    }
}
