<?php

require_once __DIR__ . '/../Config/database.php';
require_once __DIR__ . '/../Security/Hash.php';
require_once __DIR__ . '/../Security/JWT.php';

class AuthController
{
    // REGISTER
    
    public static function register(): void
    {
        $input = json_decode(file_get_contents('php://input'), true);

        $name = trim($input['name'] ?? '');
        $email = trim($input['email'] ?? '');
        $password = $input['password'] ?? '';
        $tenantName = trim($input['tenant_name'] ?? '');

        if (
            $name === '' ||
            $email === '' ||
            $password === '' ||
            $tenantName === ''
        ) {
            http_response_code(400);

            echo json_encode([
                'success' => false,
                'message' => 'Name, email, password and tenant name are required'
            ]);
            return;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);

            echo json_encode([
                'success' => false,
                'message' => 'Invalid email address'
            ]);
            return;
        }

        try {
            $db = Database::connect();

            // Check whether the email already exists.
            $stmt = $db->prepare(
                'SELECT id FROM users WHERE email = :email LIMIT 1'
            );

            $stmt->execute([
                'email' => $email
            ]);

            if ($stmt->fetch()) {
                http_response_code(409);

                echo json_encode([
                    'success' => false,
                    'message' => 'Email already registered'
                ]);
                return;
            }

            $db->beginTransaction();

            // Create tenant.
            $stmt = $db->prepare(
                'INSERT INTO tenants (name, type)
                 VALUES (:name, :type)'
            );

            $stmt->execute([
                'name' => $tenantName,
                'type' => 'clinic'
            ]);

            $tenantId = (int) $db->lastInsertId();

            // Hash password before storing it.
            $passwordHash = Hash::make($password);

            // Create user.
            $stmt = $db->prepare(
                'INSERT INTO users
                    (tenant_id, name, email, password_hash)
                 VALUES
                    (:tenant_id, :name, :email, :password_hash)'
            );

            $stmt->execute([
                'tenant_id' => $tenantId,
                'name' => $name,
                'email' => $email,
                'password_hash' => $passwordHash
            ]);

            $userId = (int) $db->lastInsertId();

            // New registrations become Admins.
            $stmt = $db->prepare(
                "SELECT id FROM roles WHERE name = 'Admin' LIMIT 1"
            );

            $stmt->execute();

            $role = $stmt->fetch();

            if (!$role) {
                throw new Exception('Admin role not found');
            }

            $stmt = $db->prepare(
                'INSERT INTO user_roles (user_id, role_id)
                 VALUES (:user_id, :role_id)'
            );

            $stmt->execute([
                'user_id' => $userId,
                'role_id' => $role['id']
            ]);

            $db->commit();

            http_response_code(201);

            echo json_encode([
                'success' => true,
                'data' => [
                    'user_id' => $userId,
                    'tenant_id' => $tenantId
                ],
                'message' => 'Registration successful'
            ]);
        } catch (Throwable $e) {
            if (isset($db) && $db->inTransaction()) {
                $db->rollBack();
            }

            http_response_code(500);

            echo json_encode([
                'success' => false,
                'message' => 'Registration failed'
            ]);
        }
    }

    // LOGIN

    public static function login(): void
    {
        $input = json_decode(file_get_contents('php://input'), true);

        $email = trim($input['email'] ?? '');
        $password = $input['password'] ?? '';

        if ($email === '' || $password === '') {
            http_response_code(400);

            echo json_encode([
                'success' => false,
                'message' => 'Email and password are required'
            ]);
            return;
        }

        try {
            $db = Database::connect();

            // Find the user.
            $stmt = $db->prepare(
                'SELECT id, tenant_id, name, email, password_hash, status
             FROM users
             WHERE email = :email
             LIMIT 1'
            );

            $stmt->execute([
                'email' => $email
            ]);

            $user = $stmt->fetch();

            if (!$user || !Hash::verify($password, $user['password_hash'])) {
                http_response_code(401);

                echo json_encode([
                    'success' => false,
                    'message' => 'Invalid email or password'
                ]);
                return;
            }

            if ($user['status'] !== 'active') {
                http_response_code(403);

                echo json_encode([
                    'success' => false,
                    'message' => 'User account is inactive'
                ]);
                return;
            }

            // Get the user's roles.
            $stmt = $db->prepare(
                'SELECT r.name
             FROM roles r
             INNER JOIN user_roles ur ON ur.role_id = r.id
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

            http_response_code(200);

            echo json_encode([
                'success' => true,
                'data' => [
                    'user_id' => (int) $user['id'],
                    'tenant_id' => (int) $user['tenant_id'],
                    'name' => $user['name'],
                    'email' => $user['email'],
                    'roles' => $roles,
                    'access_token' => $accessToken
                ],
                'message' => 'Login successful'
            ]);
        } catch (Throwable $e) {
            http_response_code(500);

            echo json_encode([
                'success' => false,
                'message' => 'Login failed'
            ]);
        }
    }

    public static function refresh(): void
    {
        echo json_encode([
            'success' => true,
            'message' => 'Refresh endpoint reached'
        ]);
    }

    public static function logout(): void
    {
        echo json_encode([
            'success' => true,
            'message' => 'Logout endpoint reached'
        ]);
    }
}
