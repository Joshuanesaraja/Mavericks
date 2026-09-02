<?php

require_once __DIR__ . '/../Controllers/AuthController.php';
require_once __DIR__ . '/../Controllers/AppointmentController.php';

class Router
{
    public static function handle(string $method, string $request): void
    {
        // Auth Routes
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

        // Appointment Routes (Module 4)
        if (($method === 'POST' || $method === 'PUT') && ($request === 'appointments/create' || $request === 'appointments')) {
            AppointmentController::create();
            return;
        }

        if (($method === 'PUT' || $method === 'POST') && $request === 'appointments/update') {
            AppointmentController::update();
            return;
        }

        if (($method === 'POST' || $method === 'PUT') && $request === 'appointments/cancel') {
            AppointmentController::cancel();
            return;
        }

        if (($method === 'POST' || $method === 'PUT') && $request === 'appointments/status') {
            AppointmentController::updateStatus();
            return;
        }

        if ($method === 'GET' && $request === 'appointments/upcoming') {
            AppointmentController::upcoming();
            return;
        }

        if ($method === 'GET' && $request === 'appointments/detail') {
            AppointmentController::detail();
            return;
        }

        if ($method === 'GET' && ($request === 'appointments' || $request === 'appointments/list')) {
            AppointmentController::list();
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