<?php

require_once __DIR__ . '/../Helpers/EncryptedPayload.php';
require_once __DIR__ . '/../Helpers/Response.php';

class EncryptionMiddleware
{
    public static function handle(
        string $method,
        array $input
    ): ?array {
        // GET requests do not have an encrypted request body.
        if ($method === 'GET') {
            return $input;
        }

        $payload = $input['payload'] ?? '';

        if ($payload === '') {
            Response::error('Encrypted payload is required', 400);
            return null;
        }

        try {
            return EncryptedPayload::decrypt($payload);
        } catch (Throwable $e) {
            Response::error('Invalid encrypted payload', 400);
            return null;
        }
    }
}