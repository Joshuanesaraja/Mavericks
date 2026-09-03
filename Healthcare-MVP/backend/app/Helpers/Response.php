<?php

require_once __DIR__ . '/EncryptedPayload.php';

class Response
{
    public static function success(
        mixed $data = null,
        string $message = 'Operation successful',
        int $statusCode = 200
    ): void {
        http_response_code($statusCode);

        $response = [
            'success' => true,
            'data' => $data,
            'message' => $message
        ];

        echo json_encode([
            'payload' => EncryptedPayload::encrypt($response)
        ]);
    }

    public static function error(
        string $message,
        int $statusCode = 400
    ): void {
        http_response_code($statusCode);

        $response = [
            'success' => false,
            'message' => $message
        ];

        echo json_encode([
            'payload' => EncryptedPayload::encrypt($response)
        ]);
    }
}
