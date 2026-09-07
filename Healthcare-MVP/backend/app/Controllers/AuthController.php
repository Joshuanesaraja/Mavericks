<?php

require_once __DIR__ . '/../Config/database.php';
require_once __DIR__ . '/../Repositories/TenantRepository.php';
require_once __DIR__ . '/../Services/TenantProvisioningService.php';
require_once __DIR__ . '/../Security/Hash.php';
require_once __DIR__ . '/../Security/JWT.php';
require_once __DIR__ . '/../Helpers/Response.php';
require_once __DIR__ . '/../Helpers/Cookie.php';
require_once __DIR__ . '/../Security/CSRF.php';

class AuthController
{
    public static function csrfToken(): void
    {
        $token = CSRF::generate();

        Response::success(
            [
                'csrf_token' => $token
            ],
            'CSRF token generated'
        );
    }

    // REGISTER

    public static function register(array $input): void
    {
        $name = trim($input['name'] ?? '');
        $email = trim($input['email'] ?? '');
        $password = $input['password'] ?? '';
        $subdomain = strtolower(trim($input['subdomain'] ?? ''));

        if (
            $name === '' ||
            $email === '' ||
            $password === '' ||
            $subdomain === ''
        ) {
            Response::error(
                'Name, email, password and subdomain are required',
                400
            );
            return;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Response::error(
                'Invalid email address',
                400
            );
            return;
        }

        if (strlen($password) < 8) {
            Response::error(
                'Password must be at least 8 characters',
                400
            );
            return;
        }

        /*
     * Subdomain rules:
     *
     * - lowercase letters
     * - numbers
     * - hyphen
     * - must start and end with a letter/number
     */
        if (
            preg_match(
                '/^[a-z0-9](?:[a-z0-9-]{0,98}[a-z0-9])?$/',
                $subdomain
            ) !== 1
        ) {
            Response::error(
                'Invalid subdomain',
                400
            );
            return;
        }

        $master = Database::master();

        try {
            /*
            * Check hospital email uniqueness in Master DB.
            */
            $stmt = $master->prepare(
                'SELECT id
                FROM tenants
                WHERE email = :email
                LIMIT 1'
            );

            $stmt->execute([
                ':email' => $email
            ]);

            if ($stmt->fetch()) {
                Response::error(
                    'Email already registered',
                    409
                );
                return;
            }

            /*
         * Check subdomain uniqueness.
         */
            $stmt = $master->prepare(
                'SELECT id
             FROM tenants
             WHERE subdomain = :subdomain
             LIMIT 1'
            );

            $stmt->execute([
                ':subdomain' => $subdomain
            ]);

            if ($stmt->fetch()) {
                Response::error(
                    'Subdomain already registered',
                    409
                );
                return;
            }

            /*
         * ---------------------------------------------------------
         * CREATE MASTER TENANT RECORD
         * ---------------------------------------------------------
         *
         * The tenant starts in provisioning state.
         */
            $trialEnd = date(
                'Y-m-d',
                strtotime('+14 days')
            );

            $stmt = $master->prepare(
                'INSERT INTO tenants(
                    name,
                    email,
                    subdomain,
                    status,
                    trial_end,
                    subscription_status,
                    db_name,
                    db_host
                )
             VALUES
                (
                :name,
                :email,
                :subdomain,
                :status,
                :trial_end,
                :subscription_status,
                NULL,
                NULL
                )'
            );

            $stmt->execute([
                ':name' => $name,
                ':email' => $email,
                ':subdomain' => $subdomain,
                ':status' => 'provisioning',
                ':trial_end' => $trialEnd,
                ':subscription_status' => 'trial'
            ]);

            $tenantId = (int) $master->lastInsertId();

            /*
         * Database name is generated from the Master DB tenant ID.
         *
         * Example:
         *
         * tenant ID 1 → heal_tenant_1
         */
            $dbName = 'heal_tenant_' . $tenantId;

            /*
         * Hash the registration password before passing it
         * to tenant provisioning.
         */
            $passwordHash = Hash::make($password);

            /*
         * ---------------------------------------------------------
         * PROVISION TENANT
         * ---------------------------------------------------------
         */
            $provisioned = TenantProvisioningService::provision(
                $tenantId,
                $dbName,
                $name,
                $email,
                $passwordHash
            );

            /*
         * ---------------------------------------------------------
         * ACTIVATE TENANT
         * ---------------------------------------------------------
         */
            $stmt = $master->prepare(
                'UPDATE tenants
                SET status = :status,
                db_name = :db_name,
                db_host = :db_host,
                db_user = :db_user,
                db_password = :db_password
                WHERE id = :id'
            );

            $stmt->execute([
                ':status' => 'active',
                ':db_name' => $provisioned['db_name'],
                ':db_host' => $provisioned['db_host'],
                ':db_user' => $provisioned['db_user'],
                ':db_password' => $provisioned['db_password'],
                ':id' => $tenantId
            ]);

            Response::success(
                [
                    'tenant_id' => $tenantId,
                    'tenant_name' => $name,
                    'subdomain' => $subdomain,
                    'status' => 'active',
                    'trial_end' => $trialEnd,
                    'subscription_status' => 'trial'
                ],
                'Tenant registration successful',
                201
            );
        } catch (Throwable $e) {

            /*
         * TenantProvisioningService removes the tenant database
         * if provisioning fails.
         *
         * Mark the Master DB record as failed rather than
         * pretending that registration succeeded.
         */
            if (isset($tenantId) && $tenantId > 0) {
                try {
                    $stmt = $master->prepare(
                        'UPDATE tenants
                     SET status = :status
                     WHERE id = :id'
                    );

                    $stmt->execute([
                        ':status' => 'provisioning_failed',
                        ':id' => $tenantId
                    ]);
                } catch (Throwable $ignore) {
                    // Preserve the original registration error.
                }
            }

            Response::error(
                'Registration failed',
                500
            );
        }
    }

    // LOGIN
    public static function login(array $input): void
    {
        $email = strtolower(trim($input['email'] ?? ''));
        $password = $input['password'] ?? '';

        if ($email === '' || $password === '') {
            Response::error(
                'Email and password are required',
                400
            );
            return;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Response::error(
                'Invalid email or password',
                401
            );
            return;
        }

        try {
            /*
         * ---------------------------------------------------------
         * 1. Resolve the tenant.
         * ---------------------------------------------------------
         *
         * The tenant must be identified before we can access
         * the tenant's users table.
         *
         * For the current API design, the tenant is resolved
         * from the request subdomain.
         */
            $host = $_SERVER['HTTP_HOST'] ?? '';

            /*
         * Remove port number if running locally.
         *
         * Example:
         * abc.heal.com:8000
         * becomes:
         * abc.heal.com
         */
            $host = preg_replace('/:\d+$/', '', $host);

            $subdomain = '';

            /*
         * Local development support:
         *
         * If the request is:
         * abc.localhost
         *
         * then "abc" becomes the tenant subdomain.
         *
         * If using the production-style domain:
         * abc.heal.com
         *
         * then "abc" is also extracted.
         */
            if (preg_match('/^([a-z0-9-]+)\.heal\.com$/i', $host, $matches)) {
                $subdomain = strtolower($matches[1]);
            } elseif (
                preg_match('/^([a-z0-9-]+)\.localhost$/i', $host, $matches)
            ) {
                $subdomain = strtolower($matches[1]);
            }

            /*
         * If a tenant subdomain could not be determined,
         * stop authentication.
         */
            if ($subdomain === '') {
                Response::error(
                    'Tenant subdomain is required',
                    400
                );
                return;
            }

            /*
         * ---------------------------------------------------------
         * 2. Find the tenant in Master DB.
         * ---------------------------------------------------------
         */
            $tenantRepository = new TenantRepository();

            $tenant = $tenantRepository->findActiveBySubdomain(
                $subdomain
            );

            if (!$tenant) {
                Response::error(
                    'Tenant is invalid or inactive',
                    403
                );
                return;
            }

            /*
         * ---------------------------------------------------------
         * 3. Check tenant database configuration.
         * ---------------------------------------------------------
         */
            if (
                empty($tenant['db_name']) ||
                empty($tenant['db_host']) ||
                empty($tenant['db_user']) ||
                empty($tenant['db_password'])
            ) {
                Response::error(
                    'Tenant database is not configured',
                    500
                );
                return;
            }

            /*
         * ---------------------------------------------------------
         * 4. Connect to THIS tenant's database.
         * ---------------------------------------------------------
         *
         * Authentication is performed against the tenant's own
         * users table.
         */
            $db = Database::tenant(
                $tenant['db_name'],
                $tenant['db_user'],
                $tenant['db_password']
            );

            /*
         * ---------------------------------------------------------
         * 5. Find the user inside the tenant DB.
         * ---------------------------------------------------------
         */
            $stmt = $db->prepare(
                'SELECT
                id,
                name,
                email,
                password_hash,
                status
             FROM users
             WHERE email = :email
             LIMIT 1'
            );

            $stmt->execute([
                ':email' => $email
            ]);

            $user = $stmt->fetch();

            /*
         * Do not reveal whether the email exists.
         */
            if (
                !$user ||
                !Hash::verify(
                    $password,
                    $user['password_hash']
                )
            ) {
                Response::error(
                    'Invalid email or password',
                    401
                );
                return;
            }

            /*
         * ---------------------------------------------------------
         * 6. Check user status.
         * ---------------------------------------------------------
         */
            if (($user['status'] ?? '') !== 'active') {
                Response::error(
                    'User account is inactive',
                    403
                );
                return;
            }

            /*
         * ---------------------------------------------------------
         * 7. Get roles from THIS tenant DB.
         * ---------------------------------------------------------
         */
            $stmt = $db->prepare(
                'SELECT r.name
             FROM roles r
             INNER JOIN user_roles ur
                 ON ur.role_id = r.id
             WHERE ur.user_id = :user_id
             ORDER BY r.name'
            );

            $stmt->execute([
                ':user_id' => $user['id']
            ]);

            $roles = $stmt->fetchAll(PDO::FETCH_COLUMN);

            /*
         * ---------------------------------------------------------
         * 8. Generate access token.
         * ---------------------------------------------------------
         *
         * tenant_id identifies the tenant in the Master DB.
         * It does NOT represent a row-level tenant_id inside
         * the tenant database.
         */
            $accessToken = JWT::generateAccessToken(
                (int) $user['id'],
                (int) $tenant['id'],
                $roles
            );

            /*
         * ---------------------------------------------------------
         * 9. Generate refresh token.
         * ---------------------------------------------------------
         */
            $refreshToken = JWT::generateRefreshToken(
                (int) $user['id'],
                (int) $tenant['id']
            );

            /*
         * ---------------------------------------------------------
         * 10. Hash refresh token before storing it.
         * ---------------------------------------------------------
         */
            $refreshTokenHash = Hash::token(
                $refreshToken
            );

            $refreshExpiresAt = date(
                'Y-m-d H:i:s',
                time() + (30 * 24 * 60 * 60)
            );

            /*
         * ---------------------------------------------------------
         * 11. Store hashed refresh token in THIS tenant DB.
         * ---------------------------------------------------------
         *
         * There is no tenant_id column because this database
         * belongs exclusively to this tenant.
         */
            $stmt = $db->prepare(
                'INSERT INTO refresh_tokens
                (
                    user_id,
                    token_hash,
                    expires_at,
                    revoked
                )
             VALUES
                (
                    :user_id,
                    :token_hash,
                    :expires_at,
                    FALSE
                )'
            );

            $stmt->execute([
                ':user_id' => $user['id'],
                ':token_hash' => $refreshTokenHash,
                ':expires_at' => $refreshExpiresAt
            ]);

            /*
         * ---------------------------------------------------------
         * 12. Store both tokens in HttpOnly cookies.
         * ---------------------------------------------------------
         */
            Cookie::set(
                'access_token',
                $accessToken,
                time() + (15 * 60)
            );

            Cookie::set(
                'refresh_token',
                $refreshToken,
                time() + (30 * 24 * 60 * 60)
            );

            /*
         * ---------------------------------------------------------
         * 13. Regenerate CSRF token after successful login.
         * ---------------------------------------------------------
         */
            $csrfToken = CSRF::regenerate();

            /*
         * ---------------------------------------------------------
         * 14. Return login response.
         * ---------------------------------------------------------
         */
            Response::success(
                [
                    'user_id' => (int) $user['id'],
                    'tenant_id' => (int) $tenant['id'],
                    'tenant_name' => $tenant['name'],
                    'subdomain' => $tenant['subdomain'],
                    'trial_end' => $tenant['trial_end'],
                    'subscription_status' => $tenant['subscription_status'],
                    'name' => $user['name'],
                    'email' => $user['email'],
                    'roles' => $roles,
                    'csrf_token' => $csrfToken
                ],
                'Login successful'
            );
        } catch (Throwable $e) {

            Response::error(
                'Login failed',
                500
            );
        }
    }

    // REFRESH
    public static function refresh(array $input): void
    {
        $refreshToken = $_COOKIE['refresh_token'] ?? '';

        if ($refreshToken === '') {
            Response::error(
                'Refresh token is required',
                401
            );
            return;
        }

        $db = null;

        try {
            /*
         * ---------------------------------------------------------
         * 1. Verify refresh token JWT.
         * ---------------------------------------------------------
         */
            $payload = JWT::decode($refreshToken);

            if (($payload->type ?? '') !== 'refresh') {
                Response::error(
                    'Invalid refresh token',
                    401
                );
                return;
            }

            $userId = (int) ($payload->sub ?? 0);
            $tenantId = (int) ($payload->tenant_id ?? 0);

            if ($userId <= 0 || $tenantId <= 0) {
                Response::error(
                    'Invalid refresh token',
                    401
                );
                return;
            }

            /*
         * ---------------------------------------------------------
         * 2. Resolve tenant from Master DB.
         * ---------------------------------------------------------
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
                return;
            }

            /*
         * ---------------------------------------------------------
         * 3. Make sure tenant DB is configured.
         * ---------------------------------------------------------
         */
            if (
                empty($tenant['db_name']) ||
                empty($tenant['db_host']) ||
                empty($tenant['db_user']) ||
                empty($tenant['db_password'])
            ) {
                Response::error(
                    'Tenant database is not configured',
                    500
                );
                return;
            }

            /*
         * ---------------------------------------------------------
         * 4. Connect to THIS tenant's database.
         * ---------------------------------------------------------
         */
            $db = Database::tenant(
                $tenant['db_name'],
                $tenant['db_user'],
                $tenant['db_password']
            );

            /*
         * ---------------------------------------------------------
         * 5. Hash supplied refresh token.
         * ---------------------------------------------------------
         *
         * The raw refresh token is never stored in the database.
         */
            $tokenHash = Hash::token(
                $refreshToken
            );

            /*
         * ---------------------------------------------------------
         * 6. Find refresh token in THIS tenant DB.
         * ---------------------------------------------------------
         */
            $stmt = $db->prepare(
                'SELECT
                id,
                user_id,
                expires_at,
                revoked
             FROM refresh_tokens
             WHERE token_hash = :token_hash
             LIMIT 1'
            );

            $stmt->execute([
                ':token_hash' => $tokenHash
            ]);

            $storedToken = $stmt->fetch();

            if (!$storedToken) {
                Response::error(
                    'Invalid refresh token',
                    401
                );
                return;
            }

            /*
         * ---------------------------------------------------------
         * 7. Verify user matches JWT.
         * ---------------------------------------------------------
         */
            if (
                (int) $storedToken['user_id'] !== $userId
            ) {
                Response::error(
                    'Invalid refresh token',
                    401
                );
                return;
            }

            /*
         * ---------------------------------------------------------
         * 8. Check whether token was revoked.
         * ---------------------------------------------------------
         */
            if ((bool) $storedToken['revoked']) {
                Response::error(
                    'Refresh token has been revoked',
                    401
                );
                return;
            }

            /*
         * ---------------------------------------------------------
         * 9. Check expiration.
         * ---------------------------------------------------------
         */
            if (
                strtotime($storedToken['expires_at']) <= time()
            ) {
                Response::error(
                    'Refresh token has expired',
                    401
                );
                return;
            }

            /*
         * ---------------------------------------------------------
         * 10. Verify user exists in THIS tenant DB.
         * ---------------------------------------------------------
         */
            $stmt = $db->prepare(
                'SELECT
                id,
                name,
                email,
                status
             FROM users
             WHERE id = :user_id
             LIMIT 1'
            );

            $stmt->execute([
                ':user_id' => $userId
            ]);

            $user = $stmt->fetch();

            if (!$user) {
                Response::error(
                    'User not found',
                    401
                );
                return;
            }

            /*
         * ---------------------------------------------------------
         * 11. Check user status.
         * ---------------------------------------------------------
         */
            if (($user['status'] ?? '') !== 'active') {
                Response::error(
                    'User account is inactive',
                    403
                );
                return;
            }

            /*
         * ---------------------------------------------------------
         * 12. Get current roles from THIS tenant DB.
         * ---------------------------------------------------------
         */
            $stmt = $db->prepare(
                'SELECT r.name
             FROM roles r
             INNER JOIN user_roles ur
                 ON ur.role_id = r.id
             WHERE ur.user_id = :user_id
             ORDER BY r.name'
            );

            $stmt->execute([
                ':user_id' => $userId
            ]);

            $roles = $stmt->fetchAll(
                PDO::FETCH_COLUMN
            );

            /*
         * ---------------------------------------------------------
         * 13. Start atomic token rotation.
         * ---------------------------------------------------------
         */
            $db->beginTransaction();

            try {
                /*
             * Revoke old refresh token.
             */
                $stmt = $db->prepare(
                    'UPDATE refresh_tokens
                 SET revoked = TRUE
                 WHERE id = :id'
                );

                $stmt->execute([
                    ':id' => $storedToken['id']
                ]);

                /*
             * Generate new access token.
             */
                $newAccessToken = JWT::generateAccessToken(
                    $userId,
                    $tenantId,
                    $roles
                );

                /*
             * Generate new refresh token.
             */
                $newRefreshToken = JWT::generateRefreshToken(
                    $userId,
                    $tenantId
                );

                /*
             * Hash new refresh token.
             */
                $newRefreshTokenHash = Hash::token(
                    $newRefreshToken
                );

                $newRefreshExpiresAt = date(
                    'Y-m-d H:i:s',
                    time() + (30 * 24 * 60 * 60)
                );

                /*
             * Store only the hash of the new refresh token.
             */
                $stmt = $db->prepare(
                    'INSERT INTO refresh_tokens
                    (
                        user_id,
                        token_hash,
                        expires_at,
                        revoked
                    )
                 VALUES
                    (
                        :user_id,
                        :token_hash,
                        :expires_at,
                        FALSE
                    )'
                );

                $stmt->execute([
                    ':user_id' => $userId,
                    ':token_hash' => $newRefreshTokenHash,
                    ':expires_at' => $newRefreshExpiresAt
                ]);

                /*
             * Commit token rotation.
             */
                $db->commit();
            } catch (Throwable $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }

                throw $e;
            }

            /*
         * ---------------------------------------------------------
         * 14. Replace HttpOnly cookies.
         * ---------------------------------------------------------
         */
            Cookie::set(
                'access_token',
                $newAccessToken,
                time() + (15 * 60)
            );

            Cookie::set(
                'refresh_token',
                $newRefreshToken,
                time() + (30 * 24 * 60 * 60)
            );

            /*
         * ---------------------------------------------------------
         * 15. Regenerate CSRF token.
         * ---------------------------------------------------------
         */
            CSRF::regenerate();

            /*
         * ---------------------------------------------------------
         * 16. Return success.
         * ---------------------------------------------------------
         */
            Response::success(
                null,
                'Token refreshed successfully'
            );
        } catch (Throwable $e) {

            if ($db instanceof PDO && $db->inTransaction()) {
                $db->rollBack();
            }

            Response::error(
                'Refresh failed',
                500
            );
        }
    }

    // LOGOUT
    public static function logout(array $input): void
    {
        $refreshToken = $_COOKIE['refresh_token'] ?? '';

        try {

            if ($refreshToken !== '') {

                /*
             * -----------------------------------------------------
             * 1. Decode the refresh token.
             * -----------------------------------------------------
             *
             * We only need the tenant_id so that we can locate
             * the correct tenant database.
             */
                $payload = JWT::decode($refreshToken);

                if (($payload->type ?? '') !== 'refresh') {
                    /*
                 * Even if the token is invalid, continue with
                 * cookie deletion below.
                 */
                    $payload = null;
                }

                if ($payload !== null) {

                    $tenantId = (int) ($payload->tenant_id ?? 0);

                    if ($tenantId > 0) {

                        /*
                     * -------------------------------------------------
                     * 2. Resolve tenant from Master DB.
                     * -------------------------------------------------
                     */
                        $tenantRepository = new TenantRepository();

                        $tenant = $tenantRepository->findActiveById(
                            $tenantId
                        );

                        if ($tenant) {

                            /*
                         * -------------------------------------------------
                         * 3. Connect to this tenant's database.
                         * -------------------------------------------------
                         */
                            if (
                                !empty($tenant['db_name']) &&
                                !empty($tenant['db_user']) &&
                                !empty($tenant['db_password'])
                            ) {
                                $db = Database::tenant(
                                    $tenant['db_name'],
                                    $tenant['db_user'],
                                    $tenant['db_password']
                                );

                                /*
                             * -------------------------------------------------
                             * 4. Hash supplied refresh token.
                             * -------------------------------------------------
                             */
                                $tokenHash = Hash::token(
                                    $refreshToken
                                );

                                /*
                             * -------------------------------------------------
                             * 5. Revoke refresh token.
                             * -------------------------------------------------
                             */
                                $stmt = $db->prepare(
                                    'UPDATE refresh_tokens
                                 SET revoked = TRUE
                                 WHERE token_hash = :token_hash'
                                );

                                $stmt->execute([
                                    ':token_hash' => $tokenHash
                                ]);
                            }
                        }
                    }
                }
            }

            /*
         * ---------------------------------------------------------
         * 6. Remove authentication cookies.
         * ---------------------------------------------------------
         */
            Cookie::delete('access_token');
            Cookie::delete('refresh_token');

            /*
         * ---------------------------------------------------------
         * 7. Clear session.
         * ---------------------------------------------------------
         */
            session_unset();
            session_regenerate_id(true);

            /*
         * ---------------------------------------------------------
         * 8. Generate a new CSRF token.
         * ---------------------------------------------------------
         */
            CSRF::regenerate();

            /*
         * ---------------------------------------------------------
         * 9. Return success.
         * ---------------------------------------------------------
         */
            Response::success(
                null,
                'Logged out successfully'
            );
        } catch (Throwable $e) {

            /*
         * Even if token processing fails, we should still
         * remove the authentication cookies.
         */
            Cookie::delete('access_token');
            Cookie::delete('refresh_token');

            Response::error(
                'Logout failed',
                500
            );
        }
    }
}
