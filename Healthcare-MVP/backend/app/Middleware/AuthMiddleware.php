<?php

require_once __DIR__ . '/../Security/JWT.php';
require_once __DIR__ . '/../Repositories/UserRepository.php';
require_once __DIR__ . '/../Repositories/TenantRepository.php';
require_once __DIR__ . '/../Helpers/Response.php';

class AuthMiddleware
{
    public static function handle(): object
    {
        /*
         * Access token is normally stored in an HttpOnly cookie.
         * Bearer token is also supported for API testing.
         */
        $token = $_COOKIE['access_token'] ?? '';

        if ($token === '') {
            $authorization = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

            if (
                $authorization !== '' &&
                preg_match(
                    '/Bearer\s+(.+)/i',
                    $authorization,
                    $matches
                )
            ) {
                $token = trim($matches[1]);
            }
        }

        if ($token === '') {
            Response::error(
                'Authentication required',
                401
            );
            exit;
        }

        try {
            /*
             * Decode and verify JWT signature.
             */
            $payload = JWT::decode($token);

        } catch (Throwable $e) {

            Response::error(
                'Invalid or expired access token',
                401
            );
            exit;
        }

        /*
         * Only access tokens are allowed here.
         */
        if (($payload->type ?? '') !== 'access') {
            Response::error(
                'Invalid access token',
                401
            );
            exit;
        }

        $userId = (int) ($payload->sub ?? 0);
        $tenantId = (int) ($payload->tenant_id ?? 0);

        if ($userId <= 0 || $tenantId <= 0) {
            Response::error(
                'Invalid authentication claims',
                401
            );
            exit;
        }

        /*
         * ---------------------------------------------------------
         * TENANT VALIDATION
         * ---------------------------------------------------------
         *
         * Tenant information is controlled by master_db.
         *
         * We MUST validate the tenant before allowing access
         * to any tenant-owned EHR data.
         */
        $tenantRepository = new TenantRepository();

        $tenant = $tenantRepository->findActiveById(
            $tenantId
        );

        if (!$tenant) {
            Response::error(
                'Tenant is invalid or inactive',
                403
            );
            exit;
        }

        /*
         * ---------------------------------------------------------
         * USER VALIDATION
         * ---------------------------------------------------------
         *
         * User information is stored in ehr_db.
         *
         * The tenant_id from the JWT is used together with the
         * user ID so that a user cannot access another tenant's
         * account.
         */
        $userRepository = new UserRepository();

        $user = $userRepository->findById(
            $userId,
            $tenantId
        );

        if (!$user) {
            Response::error(
                'User not found',
                401
            );
            exit;
        }

        /*
         * User must still be active.
         */
        if (($user['status'] ?? '') !== 'active') {
            Response::error(
                'User account is inactive',
                403
            );
            exit;
        }

        /*
         * Verify that the user's tenant matches the tenant
         * represented by the JWT.
         */
        if ((int) $user['tenant_id'] !== $tenantId) {
            Response::error(
                'Tenant access denied',
                403
            );
            exit;
        }

        /*
         * Store useful authenticated-user information in the
         * decoded JWT object.
         *
         * Controllers and RoleMiddleware can use these values.
         */
        $payload->user_id = $userId;
        $payload->tenant_id = $tenantId;
        $payload->tenant = $tenant;
        $payload->user = $user;

        /*
         * Roles are already present in the JWT, but make sure
         * the authenticated user's current roles are available
         * from the database as well.
         */
        $payload->roles = $user['roles'] ?? [];

        return $payload;
    }
}
