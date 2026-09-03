<?php

require_once __DIR__ . '/../Security/CSRF.php';
require_once __DIR__ . '/../Helpers/Response.php';

class CsrfMiddleware
{
    public static function handle(
        string $method,
        array $input
    ): bool {
        if (!in_array($method, ['POST', 'PUT', 'DELETE'], true)) {
            return true;
        }

        $token = $input['csrf_token'] ?? '';

        if (!CSRF::validate($token)) {
            Response::error('Invalid CSRF token', 403);
            return false;
        }

        return true;
    }
}
