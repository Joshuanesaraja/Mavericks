<?php

require_once __DIR__ . '/../Security/JWT.php';
require_once __DIR__ . '/../Helpers/Response.php';

class AuthMiddleware
{
    /**
     * Authenticate request via Cookie or Authorization Bearer token header.
     * Returns decoded JWT object ($decoded->sub, $decoded->tenant_id, $decoded->roles) or null on failure.
     */
    public static function handle(): ?object
    {
        $token = $_COOKIE['access_token'] ?? self::getBearerToken();

        if (empty($token)) {
            Response::error('Authentication required', 401);
            return null;
        }

        try {
            $decoded = JWT::decode($token);

            if (($decoded->type ?? '') !== 'access') {
                Response::error('Invalid access token', 401);
                return null;
            }

            if (!isset($decoded->tenant_id)) {
                Response::error('Tenant information missing', 401);
                return null;
            }

            return $decoded;
        } catch (Throwable $e) {
            Response::error('Invalid or expired access token', 401);
            return null;
        }
    }

    /**
     * Legacy helper method for backward compatibility.
     */
    public static function authenticate(): ?array
    {
        $decoded = self::handle();
        if (!$decoded) {
            return null;
        }

        return [
            'userId'   => (int) ($decoded->sub ?? 0),
            'tenantId' => (int) ($decoded->tenant_id ?? 0),
            'roles'    => (array) ($decoded->roles ?? [])
        ];
    }

    private static function getBearerToken(): string
    {
        $authHeader = '';
        if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $authHeader = trim($_SERVER['HTTP_AUTHORIZATION']);
        } elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            $authHeader = trim($_SERVER['REDIRECT_HTTP_AUTHORIZATION']);
        } elseif (function_exists('apache_request_headers')) {
            $headers = apache_request_headers();
            $headers = array_combine(array_map('ucwords', array_keys($headers)), array_values($headers));
            if (isset($headers['Authorization'])) {
                $authHeader = trim($headers['Authorization']);
            }
        }

        if (preg_match('/Bearer\s(\S+)/i', $authHeader, $matches)) {
            return $matches[1];
        }

        return '';
    }
}
