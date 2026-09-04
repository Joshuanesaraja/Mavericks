<?php

require_once __DIR__ . '/../Security/AES.php';

class EncryptedPayload
{
    public static function decrypt(string $payload): array
    {
        $decrypted = AES::decrypt($payload);

        $data = json_decode($decrypted, true);

        if (!is_array($data)) {
            throw new Exception('Invalid encrypted payload');
        }

        return $data;
    }

    public static function encrypt(array $data): string
    {
        $json = json_encode($data);

        if ($json === false) {
            throw new Exception('Failed to encode response data');
        }

        return AES::encrypt($json);
    }
}
