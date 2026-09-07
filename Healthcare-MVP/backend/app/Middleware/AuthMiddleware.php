<?php

require_once __DIR__ . '/../Config/database.php';
require_once __DIR__ . '/../Config/jwt.php';
require_once __DIR__ . '/../Repositories/TenantRepository.php';
require_once __DIR__ . '/../Repositories/UserRepository.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class AuthMiddleware
{
    /**
     * Authenticate the request and attach
     * authenticated user + tenant information
     * to the JWT payload.
     */
    public static function authenticate(): object
    {
        /*
         * 1. Get access token.
         *
         * Primary source:
         *     HttpOnly access_token cookie
         *
         * Fallback:
         *     Authorization: Bearer <token>
         */
        $token = $_COOKIE['access_token'] ?? null;

        if (!$token) {
            $authorization = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

            if (preg_match('/^Bearer\s+(.+)$/i', $authorization, $matches)) {
                $token = trim($matches[1]);
            }
        }

        if (!$token) {
            Response::error('Authentication required', 401);
            exit;
        }

        /*
         * 2. Decode and verify JWT.
         */
        try {
            $payload = JWT::decode(
                $token,
                new Key($_ENV['JWT_SECRET'], 'HS256')
            );
        } catch (Throwable $e) {
            Response::error('Invalid or expired access token', 401);
            exit;
        }

        /*
         * 3. Make sure this is an access token.
         */
        if (($payload->type ?? null) !== 'access') {
            Response::error('Invalid token type', 401);
            exit;
        }

        /*
         * 4. Validate required JWT claims.
         */
        $userId = isset($payload->sub)
            ? (int) $payload->sub
            : 0;

        $tenantId = isset($payload->tenant_id)
            ? (int) $payload->tenant_id
            : 0;

        if ($userId <= 0 || $tenantId <= 0) {
            Response::error('Invalid authentication data', 401);
            exit;
        }

        /*
         * 5. Find the tenant from Master DB.
         *
         * Master DB is used only to resolve:
         *     tenant ID
         *     tenant status
         *     tenant database
         *     tenant database credentials
         */
        try {
            $tenantRepository = new TenantRepository();

            $tenant = $tenantRepository->findActiveById($tenantId);
        } catch (Throwable $e) {
            Response::error('Unable to resolve tenant', 500);
            exit;
        }

        if (!$tenant) {
            Response::error('Tenant not found or inactive', 403);
            exit;
        }

        /*
         * 6. Make sure the tenant has been provisioned.
         */
        if (
            empty($tenant['db_name']) ||
            empty($tenant['db_host']) ||
            empty($tenant['db_user']) ||
            empty($tenant['db_password'])
        ) {
            Response::error('Tenant database is not configured', 500);
            exit;
        }

        /*
         * 7. Put tenant database information into
         * the request-local JWT payload object.
         *
         * IMPORTANT:
         * These values are NOT added to or regenerated
         * into the JWT itself.
         */
        $payload->tenant_db_name = $tenant['db_name'];
        $payload->tenant_db_host = $tenant['db_host'];
        $payload->tenant_db_user = $tenant['db_user'];
        $payload->tenant_db_password = $tenant['db_password'];

        /*
         * 8. Find the authenticated user inside
         * the tenant's own database.
         */
        try {
            $userRepository = new UserRepository();

            $user = $userRepository->findById(
                $payload,
                $userId
            );
        } catch (Throwable $e) {
            Response::error('Unable to access tenant database', 500);
            exit;
        }

        if (!$user) {
            Response::error('User not found', 401);
            exit;
        }

        /*
         * 9. Check user status.
         */
        if (($user['status'] ?? '') !== 'active') {
            Response::error('User account is inactive', 403);
            exit;
        }

        /*
         * 10. Attach authenticated user information.
         */
        $payload->user = $user;

        /*
         * 11. Attach tenant information.
         */
        $payload->tenant = [
            'id' => (int) $tenant['id'],
            'name' => $tenant['name'],
            'subdomain' => $tenant['subdomain'],
            'status' => $tenant['status'],
            'trial_end' => $tenant['trial_end'],
            'subscription_status' => $tenant['subscription_status'],
            'db_name' => $tenant['db_name'],
            'db_host' => $tenant['db_host']
        ];

        /*
         * 12. Attach roles.
         */
        $payload->roles = $user['roles'] ?? [];

        return $payload;
    }
}
