<?php

require_once __DIR__ . '/../Security/JWT.php';

class AuthMiddleware
{
    /**
     * Authenticate request via JWT Bearer token.
     * Returns decoded user array if valid, or sends 401 response and returns null.
     */
    public static function authenticate(): ?array
    {
        $authHeader = self::getAuthorizationHeader();

        if (empty($authHeader)) {
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'message' => 'Authorization header missing'
            ]);
            return null;
        }

        if (!preg_match('/Bearer\s(\S+)/i', $authHeader, $matches)) {
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'message' => 'Invalid Authorization header format'
            ]);
            return null;
        }

        $jwtToken = $matches[1];

        try {
            $decoded = JWT::decode($jwtToken);

            if (($decoded->type ?? '') !== 'access') {
                http_response_code(401);
                echo json_encode([
                    'success' => false,
                    'message' => 'Invalid token type'
                ]);
                return null;
            }

            return [
                'userId'   => (int) ($decoded->sub ?? 0),
                'tenantId' => (int) ($decoded->tenant_id ?? 0),
                'roles'    => (array) ($decoded->roles ?? [])
            ];
        } catch (Throwable $e) {
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'message' => 'Unauthorized: ' . $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Helper to extract Authorization header safely across server setups.
     */
    private static function getAuthorizationHeader(): string
    {
        if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            return trim($_SERVER['HTTP_AUTHORIZATION']);
        }
        if (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            return trim($_SERVER['REDIRECT_HTTP_AUTHORIZATION']);
        }
        if (function_exists('apache_request_headers')) {
            $requestHeaders = apache_request_headers();
            $requestHeaders = array_combine(
                array_map('ucwords', array_keys($requestHeaders)),
                array_values($requestHeaders)
            );
            if (isset($requestHeaders['Authorization'])) {
                return trim($requestHeaders['Authorization']);
            }
        }
        return '';
    }
}
