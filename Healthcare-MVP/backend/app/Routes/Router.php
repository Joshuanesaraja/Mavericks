<?php

require_once __DIR__ . '/../Controllers/AuthController.php';
require_once __DIR__ . '/../Middleware/CsrfMiddleware.php';
require_once __DIR__ . '/../Middleware/EncryptionMiddleware.php';
require_once __DIR__ . '/../Middleware/AuthMiddleware.php';
require_once __DIR__ . '/../Middleware/RoleMiddleware.php';
require_once __DIR__ . '/../Controllers/UserController.php';
require_once __DIR__ . '/../Middleware/RateLimit.php';

class Router
{
    public static function handle(
        string $method,
        string $request,
        array $input
    ): void {

        if (!CsrfMiddleware::handle($method, $input)) {
            return;
        }

        $decryptedInput = EncryptionMiddleware::handle(
            $method,
            $input
        );

        if ($decryptedInput === null) {
            return;
        }

        if ($method === 'GET' && $request === 'csrf-token') {
            AuthController::csrfToken();
            return;
        }

        if ($method === 'POST' && $request === 'register') {
            AuthController::register($decryptedInput);
            return;
        }

        if ($method === 'POST' && $request === 'login') {

            if (!RateLimit::handle('login')) {
                return;
            }

            AuthController::login($decryptedInput);
            return;
        }

        if ($method === 'POST' && $request === 'refresh') {
            AuthController::refresh($decryptedInput);
            return;
        }

        if ($method === 'POST' && $request === 'logout') {
            AuthController::logout($decryptedInput);
            return;
        }

        if ($method === 'GET' && $request === '') {
            Response::success(
                null,
                'Healthcare MVP API is running'
            );
            return;
        }

        if ($method === 'GET' && $request === 'profile') {

            $auth = AuthMiddleware::handle();

            if ($auth === null) {
                return;
            }

            if (!RoleMiddleware::handle(
                $auth,
                ['Admin', 'Provider', 'Nurse', 'Patient', 'Pharmacist']
            )) {
                return;
            }

            UserController::profile($auth);

            return;
        }

        if ($method === 'POST' && $request === 'change-password') {

            $auth = AuthMiddleware::handle();

            if ($auth === null) {
                return;
            }

            if (!RoleMiddleware::handle(
                $auth,
                ['Admin', 'Provider', 'Nurse', 'Patient', 'Pharmacist']
            )) {
                return;
            }

            UserController::changePassword(
                $auth,
                $decryptedInput
            );

            return;
        }

        Response::error(
            'Route not found',
            404
        );
    }
}
