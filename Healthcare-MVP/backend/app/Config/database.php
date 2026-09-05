<?php

class Database
{
    private static ?PDO $ehrConnection = null;
    private static ?PDO $masterConnection = null;

    /**
     * Get the EHR database connection.
     *
     * This is the default connection used by repositories
     * for users, patients, appointments, prescriptions, etc.
     */
    public static function connect(): PDO
    {
        if (self::$ehrConnection === null) {
            self::$ehrConnection = self::createConnection(
                $_ENV['EHR_DB_NAME'] ?? 'ehr_db'
            );
        }

        return self::$ehrConnection;
    }

    /**
     * Get the Master database connection.
     *
     * Used for tenant identity and tenant status validation.
     */
    public static function master(): PDO
    {
        if (self::$masterConnection === null) {
            self::$masterConnection = self::createConnection(
                $_ENV['MASTER_DB_NAME'] ?? 'master_db'
            );
        }

        return self::$masterConnection;
    }

    /**
     * Create a PDO connection.
     */
    private static function createConnection(string $databaseName): PDO
    {
        $host = $_ENV['DB_HOST'] ?? '127.0.0.1';
        $port = $_ENV['DB_PORT'] ?? '3308';
        $user = $_ENV['DB_USER'] ?? 'root';
        $pass = $_ENV['DB_PASS'] ?? '';

        $dsn = "mysql:host={$host};port={$port};dbname={$databaseName};charset=utf8mb4";

        return new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }
}
