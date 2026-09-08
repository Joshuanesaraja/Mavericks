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

        // Read CSRF token from request header
        $headers = getallheaders();

        $requestToken = $headers['X-CSRF-Token']
            ?? $headers['x-csrf-token']
            ?? '';

        if ($requestToken === '') {
            Response::error('CSRF token is required', 403);
            return false;
        }

        if (!CSRF::validate($requestToken)) {
            Response::error('Invalid CSRF token', 403);
            return false;
        }

        return true;
    }
}
