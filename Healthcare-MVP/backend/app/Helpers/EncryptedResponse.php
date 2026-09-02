<?php

require_once __DIR__ . '/EncryptedPayload.php';

class EncryptedResponse
{
    public static function success(
        mixed $data = null,
        string $message = 'Operation successful'
    ): void {
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
        string $message
    ): void {
        $response = [
            'success' => false,
            'message' => $message
        ];

        echo json_encode([
            'payload' => EncryptedPayload::encrypt($response)
        ]);
    }
}
