<?php

class Database
{
    private static ?PDO $connection = null;

    public static function connect(): PDO
    {
        if (self::$connection === null) {
            $host = $_ENV['DB_HOST'] ?? '127.0.0.1';
            $port = $_ENV['DB_PORT'] ?? '3308';
            $name = $_ENV['DB_NAME'] ?? 'healthcare_mvp';
            $user = $_ENV['DB_USER'] ?? 'root';
            $pass = $_ENV['DB_PASS'] ?? '';

            // DSN = Data Source Name. It is basically a string that tells PHP/PDO where and how to connect to the database.
            $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";

            // PDO = PHP Data Objects. It's PHP's standard way of communicating with databases.
            self::$connection = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        }

        return self::$connection;
    }
}
