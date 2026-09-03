<?php

require_once __DIR__ . '/../Security/JWT.php';
require_once __DIR__ . '/../Helpers/Response.php';

class AuthMiddleware
{
    public static function handle(): ?object
    {
        $token = $_COOKIE['access_token'] ?? '';

        if ($token === '') {
            Response::error('Authentication required', 401);
            return null;
        }

        try {
            $decoded = JWT::decode($token);

            // Only access tokens are accepted.
            if (($decoded->type ?? '') !== 'access') {
                Response::error('Invalid access token', 401);
                return null;
            }

            // Tenant ID must be present in the token.
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
}
