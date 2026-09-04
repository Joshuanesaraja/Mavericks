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
        if (in_array($method, ['POST', 'PUT'], true)) {
            $requestToken = $input['csrf_token'] ?? '';
        }

        if ($method === 'DELETE') {
            $headers = getallheaders();
            $requestToken = $headers['X-CSRF-Token'] ?? '';
        }

        if (!CSRF::validate($requestToken)) {
            Response::error('Invalid CSRF token', 403);
            return false;
        }

        return true;
    }
}
