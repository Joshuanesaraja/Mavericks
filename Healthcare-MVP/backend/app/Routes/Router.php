<?php

require_once __DIR__ . '/../Controllers/AuthController.php';

class Router
{
    public static function handle(string $method, string $request): void
    {
        if ($method === 'POST' && $request === 'register') {
            AuthController::register();
            return;
        }

        if ($method === 'POST' && $request === 'login') {
            AuthController::login();
            return;
        }

        if ($method === 'POST' && $request === 'refresh') {
            AuthController::refresh();
            return;
        }

        if ($method === 'POST' && $request === 'logout') {
            AuthController::logout();
            return;
        }

        if ($method === 'GET' && $request === '') {
            echo json_encode([
                'success' => true,
                'message' => 'Healthcare MVP API is running'
            ]);
            return;
        }

        http_response_code(404);

        echo json_encode([
            'success' => false,
            'message' => 'Route not found'
        ]);
    }
}