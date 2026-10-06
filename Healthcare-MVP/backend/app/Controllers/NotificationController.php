<?php

require_once __DIR__ . '/../Services/NotificationService.php';
require_once __DIR__ . '/../Helpers/Response.php';

class NotificationController
{
    /**
     * GET /notifications
     */
    public static function list(object|array $user): void
    {
        try {
            $userId = self::userId($user);

            $data = NotificationService::list(
                $user,
                $userId
            );

            Response::success(
                $data,
                'Notifications fetched successfully',
                200
            );
        } catch (Throwable $e) {
            Response::error(
                $e->getMessage(),
                400
            );
        }
    }

    /**
     * GET /notifications/unread
     */
    public static function unread(object|array $user): void
    {
        try {
            $userId = self::userId($user);

            $data = NotificationService::unread(
                $user,
                $userId
            );

            Response::success(
                $data,
                'Unread notifications fetched successfully',
                200
            );
        } catch (Throwable $e) {
            Response::error(
                $e->getMessage(),
                400
            );
        }
    }

    /**
     * GET /notifications/unread-count
     */
    public static function unreadCount(object|array $user): void
    {
        try {
            $userId = self::userId($user);

            $count = NotificationService::unreadCount(
                $user,
                $userId
            );

            Response::success(
                [
                    'count' => $count,
                ],
                'Unread notification count fetched successfully',
                200
            );
        } catch (Throwable $e) {
            Response::error(
                $e->getMessage(),
                400
            );
        }
    }

    /**
     * PUT /notifications/read
     */
    public static function markAsRead(object|array $user, array $input): void
    {
        $id = (int) (
            $_GET['id']
            ?? $input['id']
            ?? 0
        );

        if ($id <= 0) {
            Response::error(
                'Notification id is required',
                400
            );
            return;
        }

        try {
            $userId = self::userId($user);

            $updated = NotificationService::markAsRead(
                $user,
                $id,
                $userId
            );

            if (!$updated) {
                Response::error(
                    'Notification not found or access denied',
                    404
                );
                return;
            }

            Response::success(
                null,
                'Notification marked as read',
                200
            );
        } catch (Throwable $e) {
            Response::error(
                $e->getMessage(),
                400
            );
        }
    }

    /**
     * PUT /notifications/read-all
     */
    public static function markAllAsRead(object|array $user): void
    {
        try {
            $userId = self::userId($user);

            NotificationService::markAllAsRead(
                $user,
                $userId
            );

            Response::success(
                null,
                'All notifications marked as read',
                200
            );
        } catch (Throwable $e) {
            Response::error(
                $e->getMessage(),
                400
            );
        }
    }

    /**
     * Extract authenticated user ID.
     */
    private static function userId(object|array $user): int
    {
        $id = is_object($user)
            ? ($user->id ?? $user->user_id ?? null)
            : ($user['id'] ?? $user['user_id'] ?? null);

        $id = (int) $id;

        if ($id <= 0) {
            throw new RuntimeException(
                'Authenticated user id is missing.'
            );
        }

        return $id;
    }
}