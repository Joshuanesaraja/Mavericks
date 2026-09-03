<?php

require_once __DIR__ . '/../Controllers/AuthController.php';
require_once __DIR__ . '/../Controllers/UserController.php';
require_once __DIR__ . '/../Controllers/AppointmentController.php';
require_once __DIR__ . '/../Controllers/PrescriptionController.php';
require_once __DIR__ . '/../Controllers/CommunicationController.php';
require_once __DIR__ . '/../Controllers/CalendarController.php';

require_once __DIR__ . '/../Middleware/CsrfMiddleware.php';
require_once __DIR__ . '/../Middleware/EncryptionMiddleware.php';
require_once __DIR__ . '/../Middleware/AuthMiddleware.php';
require_once __DIR__ . '/../Middleware/RoleMiddleware.php';
require_once __DIR__ . '/../Middleware/RateLimit.php';
require_once __DIR__ . '/../Helpers/Response.php';

class Router
{
    public static function handle(
        string $method,
        string $request,
        array $input = []
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

        // --- PUBLIC & AUTH ROUTES ---
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
            Response::success(null, 'Healthcare MVP API is running');
            return;
        }

        if ($method === 'GET' && $request === 'profile') {
            $auth = AuthMiddleware::handle();
            if ($auth === null) return;
            if (!RoleMiddleware::handle($auth, ['Admin', 'Provider', 'Nurse', 'Patient', 'Pharmacist'])) return;
            UserController::profile($auth);
            return;
        }

        if ($method === 'POST' && $request === 'change-password') {
            $auth = AuthMiddleware::handle();
            if ($auth === null) return;
            if (!RoleMiddleware::handle($auth, ['Admin', 'Provider', 'Nurse', 'Patient', 'Pharmacist'])) return;
            UserController::changePassword($auth, $decryptedInput);
            return;
        }

        // --- MODULE 4: APPOINTMENT ROUTES ---
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

        // --- MODULE 5: PRESCRIPTION & PHARMACY ROUTES ---
        if (($method === 'POST' || $method === 'PUT') && ($request === 'prescriptions/create' || $request === 'prescriptions')) {
            PrescriptionController::create();
            return;
        }

        if (($method === 'POST' || $method === 'PUT') && ($request === 'prescriptions/verify' || $request === 'prescriptions/status')) {
            PrescriptionController::updateStatus();
            return;
        }

        if ($method === 'GET' && $request === 'prescriptions/detail') {
            PrescriptionController::detail();
            return;
        }

        if ($method === 'GET' && ($request === 'prescriptions' || $request === 'prescriptions/list')) {
            PrescriptionController::list();
            return;
        }

        // --- MODULE 7: COMMUNICATION ROUTES (NOTES & MESSAGES) ---
        if ($method === 'POST' && ($request === 'notes/create' || $request === 'notes')) {
            CommunicationController::createNote();
            return;
        }

        if ($method === 'GET' && $request === 'notes') {
            CommunicationController::getNotes();
            return;
        }

        if ($method === 'POST' && ($request === 'messages/send' || $request === 'messages')) {
            CommunicationController::sendMessage();
            return;
        }

        if ($method === 'GET' && ($request === 'messages/history' || $request === 'messages')) {
            CommunicationController::getMessageHistory();
            return;
        }

        // --- MODULE 10: CALENDAR ROUTES ---
        if ($method === 'GET' && $request === 'calendar/date') {
            CalendarController::getByDate();
            return;
        }

        if ($method === 'GET' && ($request === 'calendar/range' || $request === 'calendar')) {
            CalendarController::getByRange();
            return;
        }

        Response::error('Route not found', 404);
    }
}
