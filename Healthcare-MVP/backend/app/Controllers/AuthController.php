<?php

require_once __DIR__ . '/../Config/database.php';
require_once __DIR__ . '/../Repositories/TenantRepository.php';
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
        $tenantCode = trim($input['tenant_code'] ?? '');

        if (
            $name === '' ||
            $email === '' ||
            $password === '' ||
            $tenantCode === ''
        ) {
            Response::error(
                'Name, email, password and tenant code are required',
                400
            );
            return;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Response::error('Invalid email address', 400);
            return;
        }

        try {
            /*
             * Tenant information is controlled by master_db.
             * Registration is allowed only for an active tenant.
             */
            $tenantRepository = new TenantRepository();

            $tenant = $tenantRepository->findActiveByCode($tenantCode);

            if (!$tenant) {
                Response::error(
                    'Invalid or inactive tenant',
                    403
                );
                return;
            }

            /*
             * User data belongs to the shared EHR database.
             */
            $db = Database::connect();

            // Check whether the email already exists.
            $stmt = $db->prepare(
                'SELECT id
                 FROM users
                 WHERE email = :email
                 LIMIT 1'
            );

            $stmt->execute([
                'email' => $email
            ]);

            if ($stmt->fetch()) {
                Response::error(
                    'Email already registered',
                    409
                );
                return;
            }

            $db->beginTransaction();

            // Hash password before storing it.
            $passwordHash = Hash::make($password);

            // Create user inside the EHR database.
            $stmt = $db->prepare(
                'INSERT INTO users
                    (
                        tenant_id,
                        name,
                        email,
                        password_hash,
                        status
                    )
                 VALUES
                    (
                        :tenant_id,
                        :name,
                        :email,
                        :password_hash,
                        :status
                    )'
            );

            $stmt->execute([
                'tenant_id' => $tenant['id'],
                'name' => $name,
                'email' => $email,
                'password_hash' => $passwordHash,
                'status' => 'active'
            ]);

            $userId = (int) $db->lastInsertId();

            /*
             * New registrations become Admins.
             */
            $stmt = $db->prepare(
                "SELECT id
                 FROM roles
                 WHERE name = 'Admin'
                 LIMIT 1"
            );

            $stmt->execute();

            $role = $stmt->fetch();

            if (!$role) {
                throw new Exception('Admin role not found');
            }

            $stmt = $db->prepare(
                'INSERT INTO user_roles
                    (
                        user_id,
                        role_id
                    )
                 VALUES
                    (
                        :user_id,
                        :role_id
                    )'
            );

            $stmt->execute([
                'user_id' => $userId,
                'role_id' => $role['id']
            ]);

            $db->commit();

            Response::success(
                [
                    'user_id' => $userId,
                    'tenant_id' => (int) $tenant['id'],
                    'tenant_code' => $tenant['tenant_code'],
                    'tenant_name' => $tenant['name']
                ],
                'Registration successful',
                201
            );
        } catch (Throwable $e) {

            if (isset($db) && $db->inTransaction()) {
                $db->rollBack();
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
        $email = trim($input['email'] ?? '');
        $password = $input['password'] ?? '';

        if ($email === '' || $password === '') {
            Response::error(
                'Email and password are required',
                400
            );
            return;
        }

        try {
            /*
             * User is stored in the EHR database.
             */
            $db = Database::connect();

            // Find the user.
            $stmt = $db->prepare(
                'SELECT
                    id,
                    tenant_id,
                    name,
                    email,
                    password_hash,
                    status
                 FROM users
                 WHERE email = :email
                 LIMIT 1'
            );

            $stmt->execute([
                'email' => $email
            ]);

            $user = $stmt->fetch();

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

            if ($user['status'] !== 'active') {
                Response::error(
                    'User account is inactive',
                    403
                );
                return;
            }

            /*
             * Validate tenant against master_db.
             */
            $tenantRepository = new TenantRepository();

            $tenant = $tenantRepository->findActiveById(
                (int) $user['tenant_id']
            );

            if (!$tenant) {
                Response::error(
                    'Tenant is invalid or inactive',
                    403
                );
                return;
            }

            // Get user's roles.
            $stmt = $db->prepare(
                'SELECT r.name
                 FROM roles r
                 INNER JOIN user_roles ur
                     ON ur.role_id = r.id
                 WHERE ur.user_id = :user_id'
            );

            $stmt->execute([
                'user_id' => $user['id']
            ]);

            $roles = $stmt->fetchAll(PDO::FETCH_COLUMN);

            // Generate access token.
            $accessToken = JWT::generateAccessToken(
                (int) $user['id'],
                (int) $user['tenant_id'],
                $roles
            );

            // Generate refresh token.
            $refreshToken = JWT::generateRefreshToken(
                (int) $user['id'],
                (int) $user['tenant_id']
            );

            /*
             * Store only the hash of the refresh token.
             */
            $refreshTokenHash = Hash::token($refreshToken);

            $refreshExpiresAt = date(
                'Y-m-d H:i:s',
                time() + (30 * 24 * 60 * 60)
            );

            $stmt = $db->prepare(
                'INSERT INTO refresh_tokens
                    (
                        user_id,
                        tenant_id,
                        token_hash,
                        expires_at,
                        revoked
                    )
                 VALUES
                    (
                        :user_id,
                        :tenant_id,
                        :token_hash,
                        :expires_at,
                        FALSE
                    )'
            );

            $stmt->execute([
                'user_id' => $user['id'],
                'tenant_id' => $user['tenant_id'],
                'token_hash' => $refreshTokenHash,
                'expires_at' => $refreshExpiresAt
            ]);

            /*
             * Store both tokens in HttpOnly cookies.
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

            // Regenerate CSRF token after successful login.
            CSRF::regenerate();

            Response::success(
                [
                    'user_id' => (int) $user['id'],
                    'tenant_id' => (int) $user['tenant_id'],
                    'tenant_code' => $tenant['tenant_code'],
                    'tenant_name' => $tenant['name'],
                    'name' => $user['name'],
                    'email' => $user['email'],
                    'roles' => $roles
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

        try {
            // Verify refresh token JWT.
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
             * Validate tenant against master_db.
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

            // Hash supplied refresh token.
            $tokenHash = Hash::token($refreshToken);

            $db = Database::connect();

            /*
             * Find stored refresh token.
             */
            $stmt = $db->prepare(
                'SELECT
                    id,
                    user_id,
                    tenant_id,
                    expires_at,
                    revoked
                 FROM refresh_tokens
                 WHERE token_hash = :token_hash
                 LIMIT 1'
            );

            $stmt->execute([
                'token_hash' => $tokenHash
            ]);

            $storedToken = $stmt->fetch();

            if (!$storedToken) {
                Response::error(
                    'Invalid refresh token',
                    401
                );
                return;
            }

            // Verify user matches JWT.
            if (
                (int) $storedToken['user_id'] !== $userId
            ) {
                Response::error(
                    'Invalid refresh token',
                    401
                );
                return;
            }

            // Verify tenant matches JWT.
            if (
                (int) $storedToken['tenant_id'] !== $tenantId
            ) {
                Response::error(
                    'Invalid refresh token',
                    401
                );
                return;
            }

            // Check whether token was revoked.
            if ((bool) $storedToken['revoked']) {
                Response::error(
                    'Refresh token has been revoked',
                    401
                );
                return;
            }

            // Check expiration.
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
             * Verify the user still belongs to the same tenant
             * and is still active.
             */
            $stmt = $db->prepare(
                'SELECT
                    id,
                    tenant_id,
                    name,
                    email,
                    status
                 FROM users
                 WHERE id = :user_id
                 AND tenant_id = :tenant_id
                 LIMIT 1'
            );

            $stmt->execute([
                'user_id' => $userId,
                'tenant_id' => $tenantId
            ]);

            $user = $stmt->fetch();

            if (!$user) {
                Response::error(
                    'User not found',
                    401
                );
                return;
            }

            if ($user['status'] !== 'active') {
                Response::error(
                    'User account is inactive',
                    403
                );
                return;
            }

            // Get current roles.
            $stmt = $db->prepare(
                'SELECT r.name
                 FROM roles r
                 INNER JOIN user_roles ur
                     ON ur.role_id = r.id
                 WHERE ur.user_id = :user_id'
            );

            $stmt->execute([
                'user_id' => $userId
            ]);

            $roles = $stmt->fetchAll(PDO::FETCH_COLUMN);

            /*
             * Token rotation must happen atomically.
             */
            $db->beginTransaction();

            // Revoke old refresh token.
            $stmt = $db->prepare(
                'UPDATE refresh_tokens
                 SET revoked = TRUE
                 WHERE id = :id'
            );

            $stmt->execute([
                'id' => $storedToken['id']
            ]);

            // Generate new access token.
            $newAccessToken = JWT::generateAccessToken(
                $userId,
                $tenantId,
                $roles
            );

            // Generate new refresh token.
            $newRefreshToken = JWT::generateRefreshToken(
                $userId,
                $tenantId
            );

            // Hash new refresh token.
            $newRefreshTokenHash = Hash::token(
                $newRefreshToken
            );

            $newRefreshExpiresAt = date(
                'Y-m-d H:i:s',
                time() + (30 * 24 * 60 * 60)
            );

            // Store new refresh token hash.
            $stmt = $db->prepare(
                'INSERT INTO refresh_tokens
                    (
                        user_id,
                        tenant_id,
                        token_hash,
                        expires_at,
                        revoked
                    )
                 VALUES
                    (
                        :user_id,
                        :tenant_id,
                        :token_hash,
                        :expires_at,
                        FALSE
                    )'
            );

            $stmt->execute([
                'user_id' => $userId,
                'tenant_id' => $tenantId,
                'token_hash' => $newRefreshTokenHash,
                'expires_at' => $newRefreshExpiresAt
            ]);

            $db->commit();

            /*
             * Replace both cookies.
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

            // Regenerate CSRF token.
            CSRF::regenerate();

            Response::success(
                null,
                'Token refreshed successfully'
            );
        } catch (Throwable $e) {

            if (isset($db) && $db->inTransaction()) {
                $db->rollBack();
            }

            Response::error(
                'Invalid refresh token',
                401
            );
        }
    }

    // LOGOUT
    public static function logout(array $input): void
    {
        $refreshToken = $_COOKIE['refresh_token'] ?? '';

        try {

            if ($refreshToken !== '') {

                $tokenHash = Hash::token(
                    $refreshToken
                );

                $db = Database::connect();

                /*
                 * Revoke the refresh token stored in EHR DB.
                 */
                $stmt = $db->prepare(
                    'UPDATE refresh_tokens
                     SET revoked = TRUE
                     WHERE token_hash = :token_hash'
                );

                $stmt->execute([
                    'token_hash' => $tokenHash
                ]);
            }

            /*
             * Remove authentication cookies.
             */
            Cookie::delete('access_token');
            Cookie::delete('refresh_token');

            session_unset();
            session_regenerate_id(true);

            // Generate a new CSRF token.
            CSRF::regenerate();

            Response::success(
                null,
                'Logged out successfully'
            );
        } catch (Throwable $e) {

            Response::error(
                'Logout failed',
                500
            );
        }
    }
}
