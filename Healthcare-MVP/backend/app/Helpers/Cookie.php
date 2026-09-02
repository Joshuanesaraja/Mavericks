<?php

class Cookie
{
    public static function set(
        string $name,
        string $value,
        int $expires
    ): void {
        setcookie($name, $value, [
            'expires' => $expires,
            'path' => '/',
            'secure' => isset($_SERVER['HTTPS']),
            'httponly' => true,
            'samesite' => 'Strict'
        ]);
    }

    public static function delete(string $name): void
    {
        setcookie($name, '', [
            'expires' => time() - 3600,
            'path' => '/',
            'secure' => isset($_SERVER['HTTPS']),
            'httponly' => true,
            'samesite' => 'Strict'
        ]);
    }
}