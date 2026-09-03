<?php

class Hash
{
    /**
     * Hash a plain-text password before storing it in the database.
     */
    public static function make(string $password): string
    {
        return password_hash($password, PASSWORD_DEFAULT);
    }

    /**
     * Verify a plain-text password against its stored hash.
     */
    public static function verify(string $password, string $hashedPassword): bool
    {
        return password_verify($password, $hashedPassword);
    }

    /**
     * Hash a refresh token before storing it in the database.
     */
    public static function token(string $token): string
    {
        return hash('sha256', $token);
    }
}
