<?php

require_once __DIR__ . '/../Config/database.php';
require_once __DIR__ . '/../Security/Hash.php';
require_once __DIR__ . '/../Security/JWT.php';

require_once __DIR__ . '/../Controllers/AuthController.php';
require_once __DIR__ . '/../Controllers/UserController.php';
require_once __DIR__ . '/../Controllers/StaffController.php';
require_once __DIR__ . '/../Controllers/PatientController.php';

require_once __DIR__ . '/../Services/PatientService.php';
require_once __DIR__ . '/../Repositories/PatientRepository.php';

require_once __DIR__ . '/../Middleware/CsrfMiddleware.php';
require_once __DIR__ . '/../Middleware/EncryptionMiddleware.php';
require_once __DIR__ . '/../Middleware/AuthMiddleware.php';
require_once __DIR__ . '/../Middleware/RoleMiddleware.php';
require_once __DIR__ . '/../Middleware/RateLimit.php';

use App\Controllers\PatientController;
use App\Services\PatientService;
use App\Repositories\PatientRepository;

class Router
{
    private static ?PatientController $patientController = null;

    private static function patientController(): PatientController
    {
        if (self::$patientController === null) {
            $repository = new PatientRepository(
                Database::connect()
            );

            $service = new PatientService(
                $repository
            );

            self::$patientController = new PatientController(
                $service
            );
        }

        return self::$patientController;
    }

    public static function handle(
        string $method,
        string $request,
        array $input
    ): void {

        // CSRF validation
        if (!CsrfMiddleware::handle($method, $input)) {
            return;
        }
        // Decrypt request payload
        if ($method === 'GET' || $method === 'DELETE') {
            $decryptedInput = $input;
        } else {
            $decryptedInput = EncryptionMiddleware::handle(
            $method,
            $input
        );

        if ($decryptedInput === null) {
            return;
        }
    }


        // Get CSRF token
        if ($method === 'GET' && $request === 'csrf-token') {
            AuthController::csrfToken();
            return;
        }

        // Register
        if ($method === 'POST' && $request === 'register') {
            AuthController::register($decryptedInput);
            return;
        }

        // Login
        if ($method === 'POST' && $request === 'login') {

            if (!RateLimit::handle('login')) {
                return;
            }

            AuthController::login($decryptedInput);
            return;
        }

        // Refresh token
        if ($method === 'POST' && $request === 'refresh') {
            AuthController::refresh($decryptedInput);
            return;
        }

        // Logout
        if ($method === 'POST' && $request === 'logout') {
            AuthController::logout($decryptedInput);
            return;
        }

        // Health check
        if ($method === 'GET' && $request === '') {
            Response::success(
                null,
                'Healthcare MVP API is running'
            );
            return;
        }

        // Get current profile
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

        // Change password
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

        // Admin: get all users
        if ($method === 'GET' && $request === 'users') {

            $auth = AuthMiddleware::handle();

            if ($auth === null) {
                return;
            }

            if (!RoleMiddleware::handle($auth, ['Admin'])) {
                return;
            }

            UserController::index($auth);
            return;
        }

        // Admin: get one user
        if (
            $method === 'GET' &&
            preg_match('#^users/([0-9]+)$#', $request, $matches)
        ) {

            $auth = AuthMiddleware::handle();

            if ($auth === null) {
                return;
            }

            if (!RoleMiddleware::handle($auth, ['Admin'])) {
                return;
            }

            UserController::show(
                $auth,
                (int) $matches[1]
            );

            return;
        }

        // Admin: create user
        if ($method === 'POST' && $request === 'users') {

            $auth = AuthMiddleware::handle();

            if ($auth === null) {
                return;
            }

            if (!RoleMiddleware::handle($auth, ['Admin'])) {
                return;
            }

            UserController::store(
                $auth,
                $decryptedInput
            );

            return;
        }

        // Admin: update user
        if (
            $method === 'PUT' &&
            preg_match('#^users/([0-9]+)$#', $request, $matches)
        ) {

            $auth = AuthMiddleware::handle();

            if ($auth === null) {
                return;
            }

            if (!RoleMiddleware::handle($auth, ['Admin'])) {
                return;
            }

            UserController::update(
                $auth,
                (int) $matches[1],
                $decryptedInput
            );

            return;
        }

        // Admin: assign role
        if (
            $method === 'PUT' &&
            preg_match('#^users/([0-9]+)/role$#', $request, $matches)
        ) {

            $auth = AuthMiddleware::handle();

            if ($auth === null) {
                return;
            }

            if (!RoleMiddleware::handle($auth, ['Admin'])) {
                return;
            }

            UserController::assignRole(
                $auth,
                (int) $matches[1],
                $decryptedInput
            );

            return;
        }

        // Admin: update status
        if (
            $method === 'PUT' &&
            preg_match('#^users/([0-9]+)/status$#', $request, $matches)
        ) {

            $auth = AuthMiddleware::handle();

            if ($auth === null) {
                return;
            }

            if (!RoleMiddleware::handle($auth, ['Admin'])) {
                return;
            }

            UserController::updateStatus(
                $auth,
                (int) $matches[1],
                $decryptedInput
            );

            return;
        }

    // Admin: get all active staff
    if ($method === 'GET' && $request === 'staff') {

        $auth = AuthMiddleware::handle();

        if ($auth === null) {
            return;
        }

        if (!RoleMiddleware::handle($auth, ['Admin'])) {
            return;
        }

        StaffController::index($auth);
        return;
    }

    // Admin: assign staff role
    if (
        $method === 'PUT' &&
        preg_match('#^staff/([0-9]+)/role$#', $request, $matches)
    ) {

        $auth = AuthMiddleware::handle();

        if ($auth === null) {
            return;
        }

        if (!RoleMiddleware::handle($auth, ['Admin'])) {
            return;
        }

        StaffController::assignRole(
            $auth,
            (int) $matches[1],
            $decryptedInput
        );

        return;
    }

    // Admin: deactivate staff
    if (
        $method === 'DELETE' &&
        preg_match('#^staff/([0-9]+)$#', $request, $matches)
    ) {

        $auth = AuthMiddleware::handle();

        if ($auth === null) {
            return;
        }

        if (!RoleMiddleware::handle($auth, ['Admin'])) {
            return;
        }

        StaffController::delete(
            $auth,
            (int) $matches[1]
        );

        return;
    }

    // PATIENT ROUTES
    // Provider + Nurse only

    // GET /patients
    
    if ($method === 'GET' && $request === 'patients') {
        $auth = AuthMiddleware::handle();
        if ($auth === null) {
            return;
        }
        if (!RoleMiddleware::handle(
            $auth,
            ['Provider', 'Nurse']
        )) {
            return;
        }
        try {
            $result = self::patientController()->index($auth);
            Response::success(
                $result,
                'Patients retrieved successfully.'
            );
        } catch (Throwable $e) {
            Response::error(
                $e->getMessage(),
                400
            );
        }
        return;
    }
    // GET /patients/{id}
    if (
        $method === 'GET' &&
        preg_match(
            '#^patients/(\d+)$#',
            $request,
            $matches
        )
    ) {
        $auth = AuthMiddleware::handle();
        if ($auth === null) {
            return;
        }
        if (!RoleMiddleware::handle(
            $auth,
            ['Provider', 'Nurse']
        )) {
            return;
        }
        $patientId = (int) $matches[1];
        try {
            $result = self::patientController()->show(
                $patientId,
                $auth
            );
            Response::success(
                $result,
                'Patient retrieved successfully.'
            );
        } catch (Throwable $e) {
            Response::error(
                $e->getMessage(),
                404
            );
        }
        return;
    }
    // POST /patients
    if ($method === 'POST' && $request === 'patients') {
        $auth = AuthMiddleware::handle();
        if ($auth === null) {
            return;
        }
        if (!RoleMiddleware::handle(
            $auth,
            ['Provider', 'Nurse']
        )) {
            return;
        }
        try {
            $result = self::patientController()->store(
                $decryptedInput,
                $auth
            );
            Response::success(
                $result,
                'Patient created successfully.',
                201
            );
        } catch (Throwable $e) {
            Response::error(
                $e->getMessage(),
                400
            );
        }
        return;
    }
    // PUT /patients/{id}
    if (
        $method === 'PUT' &&
        preg_match(
            '#^patients/(\d+)$#',
            $request,
            $matches
        )
    ) {
        $auth = AuthMiddleware::handle();
        if ($auth === null) {
            return;
        }
        if (!RoleMiddleware::handle(
            $auth,
            ['Provider', 'Nurse']
        )) {
            return;
        }
        $patientId = (int) $matches[1];
        try {
            $result = self::patientController()->update(
                $patientId,
                $decryptedInput,
                $auth
            );
            Response::success(
                $result,
                'Patient updated successfully.'
            );
        } catch (Throwable $e) {
            Response::error(
                $e->getMessage(),
                400
            );
        }
        return;
    }
    // DELETE /patients/{id}
    if (
        $method === 'DELETE' &&
        preg_match(
            '#^patients/(\d+)$#',
            $request,
            $matches
        )
    ) {
        $auth = AuthMiddleware::handle();
        if ($auth === null) {
            return;
        }
        if (!RoleMiddleware::handle(
            $auth,
            ['Provider', 'Nurse']
        )) {
            return;
        }
        $patientId = (int) $matches[1];
        try {
            $result = self::patientController()->destroy(
                $patientId,
                $auth
            );
            Response::success(
                $result,
                'Patient deleted successfully.'
            );
        } catch (Throwable $e) {
            Response::error(
                $e->getMessage(),
                400
            );
        }
        return;
    }

        Response::error(
            'Route not found',
            404
        );
    }
}