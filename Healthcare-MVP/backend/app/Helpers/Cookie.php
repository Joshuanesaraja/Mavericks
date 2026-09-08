<?php

class Cookie
{
    public static function set(
        string $name,
        string $value,
        int $expires
    ): void {

        // For our local http://localhost:8000 testing, Secure must be false, otherwise the browser/Postman may refuse to store/send the cookie.
        $secure = (
            isset($_SERVER['HTTPS'])
            && $_SERVER['HTTPS'] !== 'off'
            && $_SERVER['HTTPS'] !== ''
        );

        setcookie($name, $value, [
            'expires' => $expires,
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Strict'
        ]);
    }

    public static function delete(string $name): void
    {
        $secure = (
            isset($_SERVER['HTTPS'])
            && $_SERVER['HTTPS'] !== 'off'
            && $_SERVER['HTTPS'] !== ''
        );

        setcookie($name, '', [
            'expires' => time() - 3600,
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Strict'
        ]);
    }
}
