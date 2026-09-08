<?php

require_once __DIR__ . '/../Config/database.php';
require_once __DIR__ . '/../Security/Hash.php';
require_once __DIR__ . '/../Security/JWT.php';

require_once __DIR__ . '/../Controllers/AuthController.php';
require_once __DIR__ . '/../Controllers/UserController.php';
require_once __DIR__ . '/../Controllers/StaffController.php';
require_once __DIR__ . '/../Controllers/PatientController.php';

require_once __DIR__ . '/../Controllers/AppointmentController.php';
require_once __DIR__ . '/../Controllers/PrescriptionController.php';
require_once __DIR__ . '/../Controllers/CommunicationController.php';
require_once __DIR__ . '/../Controllers/CalendarController.php';

require_once __DIR__ . '/../Services/PatientService.php';
require_once __DIR__ . '/../Repositories/PatientRepository.php';

require_once __DIR__ . '/../Middleware/CsrfMiddleware.php';
require_once __DIR__ . '/../Middleware/EncryptionMiddleware.php';
require_once __DIR__ . '/../Middleware/AuthMiddleware.php';
require_once __DIR__ . '/../Middleware/RoleMiddleware.php';
require_once __DIR__ . '/../Middleware/RateLimit.php';

require_once __DIR__ . '/../Controllers/BillingController.php';
require_once __DIR__ . '/../Controllers/DashboardController.php';

use App\Controllers\PatientController;
use App\Services\PatientService;
use App\Repositories\PatientRepository;

use App\Controllers\BillingController;
use App\Controllers\DashboardController;

class Router
{

    private static function patientController(
        object $auth
    ): PatientController {
        if (
            empty($auth->tenant_db_name) ||
            empty($auth->tenant_db_user) ||
            empty($auth->tenant_db_password)
        ) {
            throw new RuntimeException(
                'Tenant database credentials are missing.'
            );
        }

        /*
        * IMPORTANT:
        * Do NOT use Database::connect() here.
        * Database::connect() would use the default/master
        * database connection.
        * The authenticated tenant database must be selected
        * from the credentials attached by AuthMiddleware.
        */
        $tenantDb = Database::tenant(
            (string) $auth->tenant_db_name,
            (string) $auth->tenant_db_user,
            (string) $auth->tenant_db_password
        );

        $repository = new PatientRepository(
            $tenantDb
        );

        $service = new PatientService(
            $repository
        );

        return new PatientController(
            $service
        );
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

        // =========================================================
        // MODULE 9: STAFF MANAGEMENT
        // Admin only
        // =========================================================

        // GET /staff
        if (
            $method === 'GET' &&
            $request === 'staff'
        ) {
            $auth = AuthMiddleware::handle();

            if ($auth === null) {
                return;
            }

            if (!RoleMiddleware::handle(
                $auth,
                ['Admin']
            )) {
                return;
            }

            StaffController::index($auth);

            return;
        }

        // GET /staff/{id}
        if (
            $method === 'GET' &&
            preg_match(
                '#^staff/([0-9]+)$#',
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
                ['Admin']
            )) {
                return;
            }

            StaffController::show(
                $auth,
                (int) $matches[1]
            );

            return;
        }

        // POST /staff
        if (
            $method === 'POST' &&
            $request === 'staff'
        ) {
            $auth = AuthMiddleware::handle();

            if ($auth === null) {
                return;
            }

            if (!RoleMiddleware::handle(
                $auth,
                ['Admin']
            )) {
                return;
            }

            StaffController::store(
                $auth,
                $decryptedInput
            );

            return;
        }

        // PUT /staff/{id}
        if (
            $method === 'PUT' &&
            preg_match(
                '#^staff/([0-9]+)$#',
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
                ['Admin']
            )) {
                return;
            }

            StaffController::update(
                $auth,
                (int) $matches[1],
                $decryptedInput
            );

            return;
        }

        // PUT /staff/{id}/status
        if (
            $method === 'PUT' &&
            preg_match(
                '#^staff/([0-9]+)/status$#',
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
                ['Admin']
            )) {
                return;
            }

            StaffController::updateStatus(
                $auth,
                (int) $matches[1],
                $decryptedInput
            );

            return;
        }

        // DELETE /staff/{id}
        if (
            $method === 'DELETE' &&
            preg_match(
                '#^staff/([0-9]+)$#',
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
                ['Admin']
            )) {
                return;
            }

            StaffController::delete(
                $auth,
                (int) $matches[1]
            );

            return;
        }

        // =========================================================
        // MODULE 3: PATIENT MANAGEMENT
        // Provider + Nurse
        // =========================================================

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
                $result = self::patientController($auth)->index($auth);

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
                '#^patients/([0-9]+)$#',
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

            try {
                $result = self::patientController($auth)->show(
                    (int) $matches[1],
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
        if (
            $method === 'POST' &&
            $request === 'patients'
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

            try {
                $result = self::patientController($auth)->store(
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
                '#^patients/([0-9]+)$#',
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

            try {
                $result = self::patientController($auth)->update(
                    (int) $matches[1],
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
                    404
                );
            }

            return;
        }

        // DELETE /patients/{id}
        if (
            $method === 'DELETE' &&
            preg_match(
                '#^patients/([0-9]+)$#',
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

            try {
                $result = self::patientController($auth)->destroy(
                    (int) $matches[1],
                    $auth
                );

                Response::success(
                    $result,
                    'Patient deleted successfully.'
                );
            } catch (Throwable $e) {
                Response::error(
                    $e->getMessage(),
                    404
                );
            }

            return;
        }

        // =========================================================
        // MODULE 4: APPOINTMENTS
        // =========================================================

        if (
            ($method === 'POST') &&
            ($request === 'appointments/create' || $request === 'appointments')
        ) {
            $auth = AuthMiddleware::handle();
            if ($auth === null) {
                return;
            }

            if (!RoleMiddleware::handle(
                $auth,
                ['Provider', 'Nurse', 'Patient']
            )) {
                return;
            }

            AppointmentController::create(
                $auth,
                $decryptedInput
            );

            return;
        }

        if (
            ($method === 'POST' || $method === 'PUT') &&
            ($request === 'appointments/update')
        ) {
            $auth = AuthMiddleware::handle();
            if ($auth === null) {
                return;
            }

            if (!RoleMiddleware::handle(
                $auth,
                ['Provider', 'Nurse', 'Patient']
            )) {
                return;
            }

            AppointmentController::update(
                $auth,
                $decryptedInput
            );

            return;
        }

        if (
            ($method === 'POST' || $method === 'PUT') &&
            ($request === 'appointments/cancel')
        ) {
            $auth = AuthMiddleware::handle();
            if ($auth === null) {
                return;
            }

            if (!RoleMiddleware::handle(
                $auth,
                ['Provider', 'Nurse', 'Patient']
            )) {
                return;
            }

            AppointmentController::cancel(
                $auth,
                $decryptedInput
            );

            return;
        }

        if (
            ($method === 'POST' || $method === 'PUT') &&
            ($request === 'appointments/status')
        ) {
            $auth = AuthMiddleware::handle();
            if ($auth === null) {
                return;
            }

            if (!RoleMiddleware::handle(
                $auth,
                ['Provider', 'Nurse', 'Patient']
            )) {
                return;
            }

            AppointmentController::updateStatus(
                $auth,
                $decryptedInput
            );

            return;
        }

        if (
            $method === 'GET' &&
            $request === 'appointments/upcoming'
        ) {
            $auth = AuthMiddleware::handle();
            if ($auth === null) {
                return;
            }

            if (!RoleMiddleware::handle(
                $auth,
                ['Provider', 'Nurse', 'Patient']
            )) {
                return;
            }

            AppointmentController::upcoming($auth);

            return;
        }

        if (
            $method === 'GET' &&
            $request === 'appointments/detail'
        ) {
            $auth = AuthMiddleware::handle();
            if ($auth === null) {
                return;
            }

            if (!RoleMiddleware::handle(
                $auth,
                ['Provider', 'Nurse', 'Patient']
            )) {
                return;
            }

            AppointmentController::detail($auth);

            return;
        }

        if (
            $method === 'GET' &&
            ($request === 'appointments' || $request === 'appointments/list')
        ) {
            $auth = AuthMiddleware::handle();
            if ($auth === null) {
                return;
            }

            if (!RoleMiddleware::handle(
                $auth,
                ['Admin', 'Provider', 'Nurse', 'Patient']
            )) {
                return;
            }

            AppointmentController::list($auth);

            return;
        }


        // =========================================================
        // MODULE 5: PRESCRIPTIONS
        // =========================================================

        if (
            ($method === 'POST') &&
            ($request === 'prescriptions/create' || $request === 'prescriptions')
        ) {
            $auth = AuthMiddleware::handle();
            if ($auth === null) {
                return;
            }

            if (!RoleMiddleware::handle($auth, ['Provider', 'Admin'])) {
                return;
            }

            PrescriptionController::create(
                $auth,
                $decryptedInput
            );

            return;
        }

        if (
            ($method === 'PUT') &&
            ($request === 'prescriptions/verify' || $request === 'prescriptions/status')
        ) {
            $auth = AuthMiddleware::handle();
            if ($auth === null) {
                return;
            }

            if (!RoleMiddleware::handle($auth, ['Pharmacist'])) {
                return;
            }

            PrescriptionController::updateStatus(
                $auth,
                $decryptedInput
            );

            return;
        }

        if (
            $method === 'GET' &&
            $request === 'prescriptions/detail'
        ) {
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

            PrescriptionController::detail($auth);

            return;
        }

        if (
            $method === 'GET' &&
            ($request === 'prescriptions' || $request === 'prescriptions/list')
        ) {
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

            PrescriptionController::list($auth);

            return;
        }

        // =========================================================
        // MODULE 6: DASHBOARD & REPORTS
        // Admin + Provider
        // =========================================================

        // GET /dashboard
        if (
            $method === 'GET' &&
            $request === 'dashboard'
        ) {
            $auth = AuthMiddleware::handle();

            if ($auth === null) {
                return;
            }

            if (!RoleMiddleware::handle(
                $auth,
                ['Admin', 'Provider']
            )) {
                return;
            }

            DashboardController::dashboard(
                $auth
            );

            return;
        }

        // GET /reports/appointments
        if (
            $method === 'GET' &&
            $request === 'reports/appointments'
        ) {
            $auth = AuthMiddleware::handle();

            if ($auth === null) {
                return;
            }

            if (!RoleMiddleware::handle(
                $auth,
                ['Admin', 'Provider']
            )) {
                return;
            }

            DashboardController::appointmentReport(
                $auth
            );

            return;
        }

        // GET /reports/prescriptions
        if (
            $method === 'GET' &&
            $request === 'reports/prescriptions'
        ) {
            $auth = AuthMiddleware::handle();

            if ($auth === null) {
                return;
            }

            if (!RoleMiddleware::handle(
                $auth,
                ['Admin', 'Provider']
            )) {
                return;
            }

            DashboardController::prescriptionReport(
                $auth
            );

            return;
        }

        // GET /analytics/tenant
        if (
            $method === 'GET' &&
            $request === 'analytics/tenant'
        ) {
            $auth = AuthMiddleware::handle();

            if ($auth === null) {
                return;
            }

            if (!RoleMiddleware::handle(
                $auth,
                ['Admin', 'Provider']
            )) {
                return;
            }

            DashboardController::tenantAnalytics(
                $auth
            );

            return;
        }

        // =========================================================
        // MODULE 7: COMMUNICATION
        // =========================================================

        if (
            $method === 'POST' &&
            ($request === 'notes/create' || $request === 'notes')
        ) {
            $auth = AuthMiddleware::handle();
            if ($auth === null) {
                return;
            }

            if (!RoleMiddleware::handle(
                $auth,
                ['Provider', 'Nurse', 'Admin']
            )) {
                return;
            }

            CommunicationController::createNote(
                $auth,
                $decryptedInput
            );

            return;
        }

        if (
            $method === 'GET' &&
            $request === 'notes'
        ) {
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

            CommunicationController::getNotes($auth);

            return;
        }

        if (
            $method === 'POST' &&
            ($request === 'messages/send' || $request === 'messages')
        ) {
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

            CommunicationController::sendMessage(
                $auth,
                $decryptedInput
            );

            return;
        }

        if (
            $method === 'GET' &&
            ($request === 'messages/history' || $request === 'messages')
        ) {
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

            CommunicationController::getMessageHistory($auth);

            return;
        }


        // =========================================================
        // MODULE 10: CALENDAR
        // =========================================================

        if (
            $method === 'GET' &&
            $request === 'calendar/date'
        ) {
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

            CalendarController::getByDate($auth);

            return;
        }

        if (
            $method === 'GET' &&
            $request === 'calendar/range'
        ) {
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

            CalendarController::getByRange($auth);

            return;
        }

        // =========================================================
        // MODULE 8: BILLING & PAYMENT
        // =========================================================

        // POST /billing/invoices
        if (
            $method === 'POST' &&
            ($request === 'billing/invoices' || $request === 'invoices')
        ) {
            $auth = AuthMiddleware::handle();

            if ($auth === null) {
                return;
            }

            if (!RoleMiddleware::handle($auth, ['Admin', 'Provider'])) {
                return;
            }

            BillingController::createInvoice(
                $auth,
                $decryptedInput
            );

            return;
        }

        // GET /billing/invoices
        if (
            $method === 'GET' &&
            ($request === 'billing/invoices' || $request === 'billing/invoices/list' || $request === 'invoices')
        ) {
            $auth = AuthMiddleware::handle();

            if ($auth === null) {
                return;
            }

            if (!RoleMiddleware::handle($auth, ['Admin', 'Provider', 'Nurse'])) {
                return;
            }

            BillingController::getInvoices($auth);

            return;
        }

        // GET /billing/invoices/{id}
        if (
            $method === 'GET' &&
            preg_match(
                '#^billing/invoices/([0-9]+)$#',
                $request,
                $matches
            )
        ) {
            $auth = AuthMiddleware::handle();

            if ($auth === null) {
                return;
            }

            if (!RoleMiddleware::handle($auth, ['Admin', 'Provider', 'Nurse'])) {
                return;
            }

            BillingController::getInvoice(
                $auth,
                (int) $matches[1]
            );

            return;
        }

        // PUT /billing/invoices/{id}/status
        if (
            ($method === 'PUT' || $method === 'POST') &&
            preg_match(
                '#^billing/invoices/([0-9]+)/status$#',
                $request,
                $matches
            )
        ) {
            $auth = AuthMiddleware::handle();

            if ($auth === null) {
                return;
            }

            if (!RoleMiddleware::handle($auth, ['Admin'])) {
                return;
            }

            BillingController::updateInvoiceStatus(
                $auth,
                (int) $matches[1],
                $decryptedInput
            );

            return;
        }

        // POST /billing/payments
        if (
            $method === 'POST' &&
            $request === 'billing/payments'
        ) {
            $auth = AuthMiddleware::handle();

            if ($auth === null) {
                return;
            }

            if (!RoleMiddleware::handle($auth, ['Admin'])) {
                return;
            }

            BillingController::createPayment(
                $auth,
                $decryptedInput
            );

            return;
        }

        // GET /billing/invoices/{id}/payments
        if (
            $method === 'GET' &&
            preg_match(
                '#^billing/invoices/([0-9]+)/payments$#',
                $request,
                $matches
            )
        ) {
            $auth = AuthMiddleware::handle();

            if ($auth === null) {
                return;
            }

            if (!RoleMiddleware::handle($auth, ['Admin', 'Provider', 'Nurse'])) {
                return;
            }

            BillingController::getPayments(
                $auth,
                (int) $matches[1]
            );

            return;
        }

        // GET /billing/summary
        if (
            $method === 'GET' &&
            $request === 'billing/summary'
        ) {
            $auth = AuthMiddleware::handle();

            if ($auth === null) {
                return;
            }

            if (!RoleMiddleware::handle($auth, ['Admin'])) {
                return;
            }

            BillingController::getBillingSummary($auth);

            return;
        }
        Response::error(
            'Route not found',
            404
        );
    }
}
