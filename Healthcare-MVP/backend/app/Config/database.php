<?php

class Database
{
    private static ?PDO $masterConnection = null;

    /**
     * Tenant connections indexed by database/user combination.
     *
     * This ensures that repeated calls to Database::tenant()
     * during the same request reuse the same PDO connection.
     */
    private static array $tenantConnections = [];

    /**
     * Get Master DB connection.
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
     * Get a tenant DB connection.
     *
     * Each tenant uses its own database and credentials.
     */
    public static function tenant(
        string $databaseName,
        ?string $username = null,
        ?string $password = null
    ): PDO {
        if (!self::isValidDatabaseName($databaseName)) {
            throw new InvalidArgumentException(
                'Invalid tenant database name.'
            );
        }

        $user = $username ?? ($_ENV['DB_USER'] ?? 'root');
        $pass = $password ?? ($_ENV['DB_PASS'] ?? '');

        /*
         * Create a unique key for this tenant connection.
         *
         * The password is hashed for the key instead of being
         * stored directly in the array key.
         */
        $connectionKey = $databaseName
            . '|' . $user
            . '|' . hash('sha256', $pass);

        /*
         * Reuse an existing connection for this tenant.
         */
        if (isset(self::$tenantConnections[$connectionKey])) {
            return self::$tenantConnections[$connectionKey];
        }

        /*
         * Create and store a new tenant connection.
         */
        self::$tenantConnections[$connectionKey] = self::createConnection(
            $databaseName,
            $user,
            $pass
        );

        return self::$tenantConnections[$connectionKey];
    }

    /**
     * Create a PDO connection.
     */
    private static function createConnection(
        string $databaseName,
        ?string $username = null,
        ?string $password = null
    ): PDO {
        $host = $_ENV['DB_HOST'] ?? '127.0.0.1';
        $port = $_ENV['DB_PORT'] ?? '3308';

        $user = $username ?? ($_ENV['DB_USER'] ?? 'root');
        $pass = $password ?? ($_ENV['DB_PASS'] ?? '');

        $dsn = "mysql:host={$host};port={$port};dbname={$databaseName};charset=utf8mb4";

        return new PDO(
            $dsn,
            $user,
            $pass,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
    }

    /**
     * Validate a MySQL database name.
     *
     * Database names are never accepted directly into SQL
     * without validation.
     */
    private static function isValidDatabaseName(
        string $databaseName
    ): bool {
        return preg_match(
            '/^[A-Za-z0-9_]+$/',
            $databaseName
        ) === 1;
    }
}
