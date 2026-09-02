<?php

class AES
{
    private const CIPHER = 'AES-256-CBC';

    private static function getKey(): string
    {
        $key = $_ENV['AES_KEY'] ?? '';

        if ($key === '') {
            throw new Exception('AES key is not configured');
        }

        return hash('sha256', $key, true);
    }

    // ENCRYPT

    public static function encrypt(string $plaintext): string
    {
        $ivLength = openssl_cipher_iv_length(self::CIPHER);
        $iv = random_bytes($ivLength);

        $encrypted = openssl_encrypt(
            $plaintext,
            self::CIPHER,
            self::getKey(),
            OPENSSL_RAW_DATA,
            $iv
        );

        if ($encrypted === false) {
            throw new Exception('AES encryption failed');
        }

        return base64_encode($iv . $encrypted);
    }

    // DECRYPT

    public static function decrypt(string $encryptedData): string
    {
        $decoded = base64_decode($encryptedData, true);

        if ($decoded === false) {
            throw new Exception('Invalid encrypted data');
        }

        $ivLength = openssl_cipher_iv_length(self::CIPHER);

        if (strlen($decoded) <= $ivLength) {
            throw new Exception('Invalid encrypted data');
        }

        $iv = substr($decoded, 0, $ivLength);
        $ciphertext = substr($decoded, $ivLength);

        $decrypted = openssl_decrypt(
            $ciphertext,
            self::CIPHER,
            self::getKey(),
            OPENSSL_RAW_DATA,
            $iv
        );

        if ($decrypted === false) {
            throw new Exception('AES decryption failed');
        }

        return $decrypted;
    }
}
