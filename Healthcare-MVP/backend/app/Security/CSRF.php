<?php

class CSRF
{
    /**
     * Generate a CSRF token for the current session.
     * Creates a token if the session doesn't already have one
     */
    public static function generate(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf_token'];
    }

    /**
     * Regenerate the CSRF token.
     * Creates a completely new token.
     */
    public static function regenerate(): string
    {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

        return $_SESSION['csrf_token'];
    }

    /**
     * Validate a supplied CSRF token.
     */
    public static function validate(string $token): bool
    {
        if (empty($_SESSION['csrf_token'])) {
            return false;
        }

        return hash_equals(
            $_SESSION['csrf_token'],
            $token
        );
    }
}
