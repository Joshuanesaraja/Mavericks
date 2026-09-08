<?php

use Firebase\JWT\JWT as FirebaseJWT;
use Firebase\JWT\Key;

class JWT
{
    private static function getSecret(): string
    {
        $secret = $_ENV['JWT_SECRET'] ?? '';

        if ($secret === '') {
            throw new Exception('JWT secret is not configured');
        }

        return $secret;
    }

    /**
     * Generate a JWT access token.
     */
    public static function generateAccessToken(
        int $userId,
        int $tenantId,
        array $roles
    ): string {
        $issuedAt = time();
        $expiresAt = $issuedAt + (15 * 60);

        $payload = [
            'iss' => 'healthcare-mvp',
            'iat' => $issuedAt,
            'exp' => $expiresAt,
            'type' => 'access',
            'sub' => $userId,
            'tenant_id' => $tenantId,
            'roles' => $roles
        ];

        return FirebaseJWT::encode(
            $payload,
            self::getSecret(),
            'HS256'
        );
    }

    /**
     * Generate a JWT refresh token.
     */
    public static function generateRefreshToken(
        int $userId,
        int $tenantId
    ): string {
        $issuedAt = time();
        $expiresAt = $issuedAt + (30 * 24 * 60 * 60);

        $payload = [
            'iss' => 'healthcare-mvp',
            'iat' => $issuedAt,
            'exp' => $expiresAt,
            'type' => 'refresh',
            'sub' => $userId,
            'tenant_id' => $tenantId
        ];

        return FirebaseJWT::encode(
            $payload,
            self::getSecret(),
            'HS256'
        );
    }

    /**
     * Decode and verify a JWT.
     */
    public static function decode(string $token): object
    {
        return FirebaseJWT::decode(
            $token,
            new Key(self::getSecret(), 'HS256')
        );
    }
}
