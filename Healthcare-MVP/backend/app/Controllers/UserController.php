<?php

require_once __DIR__ . '/../Services/UserService.php';
require_once __DIR__ . '/../Helpers/Response.php';

class UserController
{
    // Get current profile
    public static function profile(object $auth): void
    {
        try {
            $user = UserService::getProfile($auth);

            if (!$user) {
                Response::error('User not found', 404);
                return;
            }

            Response::success($user, 'Profile fetched successfully');
        } catch (Exception $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    // Get all users
    public static function index(object $auth): void
    {
        try {
            $users = UserService::getUsers($auth);

            Response::success($users, 'Users fetched successfully');
        } catch (Exception $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    // Get one user
    public static function show(object $auth, int $userId): void
    {
        try {
            $user = UserService::getUser($auth, $userId);

            if (!$user) {
                Response::error('User not found', 404);
                return;
            }

            Response::success($user, 'User fetched successfully');
        } catch (Exception $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    // Create user
    public static function store(object $auth, array $input): void
    {
        $name = trim($input['name'] ?? '');
        $email = trim($input['email'] ?? '');
        $password = $input['password'] ?? '';
        $role = trim($input['role'] ?? '');

        if ($name === '' || $email === '' || $password === '' || $role === '') {
            Response::error(
                'Name, email, password and role are required',
                400
            );
            return;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Response::error('Invalid email address', 400);
            return;
        }

        try {
            $user = UserService::createUser(
                $auth,
                $name,
                $email,
                $password,
                $role
            );

            Response::success($user, 'User created successfully', 201);
        } catch (Exception $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    // Update user
    public static function update(
        object $auth,
        int $userId,
        array $input
    ): void {
        $name = trim($input['name'] ?? '');
        $email = trim($input['email'] ?? '');

        if ($name === '' || $email === '') {
            Response::error('Name and email are required', 400);
            return;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Response::error('Invalid email address', 400);
            return;
        }

        try {
            $user = UserService::updateUser(
                $auth,
                $userId,
                $name,
                $email
            );

            Response::success($user, 'User updated successfully');
        } catch (Exception $e) {
            $status = $e->getMessage() === 'User not found' ? 404 : 400;

            Response::error($e->getMessage(), $status);
        }
    }

    // Assign role
    public static function assignRole(
        object $auth,
        int $userId,
        array $input
    ): void {
        $role = trim($input['role'] ?? '');

        if ($role === '') {
            Response::error('Role is required', 400);
            return;
        }

        try {
            $user = UserService::assignRole(
                $auth,
                $userId,
                $role
            );

            Response::success($user, 'Role assigned successfully');
        } catch (Exception $e) {
            $status = $e->getMessage() === 'User not found' ? 404 : 400;

            Response::error($e->getMessage(), $status);
        }
    }

    // Update status
    public static function updateStatus(
        object $auth,
        int $userId,
        array $input
    ): void {
        $status = strtolower(trim($input['status'] ?? ''));

        if ($status === '') {
            Response::error('Status is required', 400);
            return;
        }

        try {
            $user = UserService::updateStatus(
                $auth,
                $userId,
                $status
            );

            Response::success(
                $user,
                'User status updated successfully'
            );
        } catch (Exception $e) {
            $statusCode = $e->getMessage() === 'User not found' ? 404 : 400;

            Response::error($e->getMessage(), $statusCode);
        }
    }

    // Change own password
    public static function changePassword(
        object $auth,
        array $input
    ): void {
        $currentPassword = $input['current_password'] ?? '';
        $newPassword = $input['new_password'] ?? '';

        if ($currentPassword === '' || $newPassword === '') {
            Response::error(
                'Current password and new password are required',
                400
            );
            return;
        }

        try {
            UserService::changePassword(
                $auth,
                $currentPassword,
                $newPassword
            );

            Response::success(null, 'Password changed successfully');
        } catch (Exception $e) {
            Response::error($e->getMessage(), 400);
        }
    }
}
