<?php

require_once __DIR__ . '/../Config/database.php';

class TenantProvisioningService
{
    public static function provision(
        int $tenantId,
        string $dbName,
        string $adminName,
        string $adminEmail,
        string $adminPasswordHash
    ): array {
        self::validateDatabaseName($dbName);

        $tenantDbUser = 'heal_tenant_' . $tenantId . '_user';

        $tenantDbPassword = bin2hex(
            random_bytes(32)
        );

        $master = Database::master();

        try {
            /*
         * Create the tenant database.
         */
            $master->exec(
                "CREATE DATABASE `$dbName`
             CHARACTER SET utf8mb4
             COLLATE utf8mb4_unicode_ci"
            );

            /*
            * Create a dedicated MySQL account for this tenant.
            */
            $master->exec(
                "CREATE USER IF NOT EXISTS
                '$tenantDbUser'@'127.0.0.1'
                IDENTIFIED BY " .
                    $master->quote($tenantDbPassword)
            );

            /*
            * Grant access ONLY to this tenant database.
            */
            $master->exec(
                "GRANT ALL PRIVILEGES
                ON `$dbName`.*
                TO '$tenantDbUser'@'127.0.0.1'"
            );

            /*
         * Connect to the newly created tenant database.
         */
            $tenantDb = Database::tenant(
                $dbName,
                $tenantDbUser,
                $tenantDbPassword
            );

            /*
         * Create tenant schema.
         */
            self::createTables($tenantDb);

            /*
         * Create standard application roles.
         */
            self::createRoles($tenantDb);

            /*
         * Create the default Admin user.
         */
            self::createAdmin(
                $tenantDb,
                $adminName,
                $adminEmail,
                $adminPasswordHash
            );

            return [
                'db_name' => $dbName,
                'db_host' => '127.0.0.1',
                'db_user' => $tenantDbUser,
                'db_password' => $tenantDbPassword
            ];
        } catch (Throwable $e) {

            /*
         * Remove partially-created tenant database (if admin creation fails)
         * so provisioning can be safely retried.
         */
            try {
                $master->exec(
                    "DROP DATABASE IF EXISTS `$dbName`"
                );

                $master->exec(
                    "DROP USER IF EXISTS
                    '$tenantDbUser'@'127.0.0.1'"
                );
            } catch (Throwable $cleanupException) {
                /*
             * Keep the original provisioning error.
             */
            }

            throw $e;
        }
    }

    private static function createTables(PDO $db): void
    {
        $tables = [

            /*
             * Users
             */
            "CREATE TABLE users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(150) NOT NULL,
                email VARCHAR(255) NOT NULL UNIQUE,
                password_hash VARCHAR(255) NOT NULL,
                status ENUM('active','inactive')
                    NOT NULL DEFAULT 'active',
                created_at TIMESTAMP
                    NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP
                    NOT NULL DEFAULT CURRENT_TIMESTAMP
                    ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB",

            /*
             * Roles
             */
            "CREATE TABLE roles (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(50) NOT NULL UNIQUE,
                created_at TIMESTAMP
                    NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB",

            /*
             * User roles
             */
            "CREATE TABLE user_roles (
                user_id INT NOT NULL,
                role_id INT NOT NULL,
                PRIMARY KEY (user_id, role_id),

                CONSTRAINT fk_user_roles_user
                    FOREIGN KEY (user_id)
                    REFERENCES users(id)
                    ON DELETE CASCADE,

                CONSTRAINT fk_user_roles_role
                    FOREIGN KEY (role_id)
                    REFERENCES roles(id)
                    ON DELETE CASCADE
            ) ENGINE=InnoDB",

            /*
             * Patients
             */
            "CREATE TABLE patients (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NULL,
                encrypted_data LONGTEXT NOT NULL,
                created_at TIMESTAMP
                    NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP
                    NOT NULL DEFAULT CURRENT_TIMESTAMP
                    ON UPDATE CURRENT_TIMESTAMP,
                deleted_at TIMESTAMP NULL,

                CONSTRAINT fk_patients_user
                    FOREIGN KEY (user_id)
                    REFERENCES users(id)
                    ON DELETE SET NULL
            ) ENGINE=InnoDB",

            /*
             * Staff
             */
            "CREATE TABLE staff (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                staff_type VARCHAR(100) NOT NULL,
                status ENUM('active','inactive')
                    NOT NULL DEFAULT 'active',
                created_at TIMESTAMP
                    NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP
                    NOT NULL DEFAULT CURRENT_TIMESTAMP
                    ON UPDATE CURRENT_TIMESTAMP,
                deleted_at TIMESTAMP NULL,

                CONSTRAINT fk_staff_user
                    FOREIGN KEY (user_id)
                    REFERENCES users(id)
                    ON DELETE CASCADE
            ) ENGINE=InnoDB",

            /*
             * Appointments
             */
            "CREATE TABLE appointments (
                id INT AUTO_INCREMENT PRIMARY KEY,
                patient_id INT NOT NULL,
                provider_id INT NOT NULL,
                start_at DATETIME NOT NULL,
                end_at DATETIME NOT NULL,
                status VARCHAR(50) NOT NULL DEFAULT 'scheduled',
                reason TEXT NULL,
                created_at TIMESTAMP
                    NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP
                    NOT NULL DEFAULT CURRENT_TIMESTAMP
                    ON UPDATE CURRENT_TIMESTAMP,
                cancelled_at TIMESTAMP NULL,

                CONSTRAINT fk_appointments_patient
                    FOREIGN KEY (patient_id)
                    REFERENCES patients(id)
                    ON DELETE CASCADE,

                CONSTRAINT fk_appointments_provider
                    FOREIGN KEY (provider_id)
                    REFERENCES users(id)
                    ON DELETE CASCADE
            ) ENGINE=InnoDB",

            /*
             * Appointment notes
             */
            "CREATE TABLE appointment_notes (
                id INT AUTO_INCREMENT PRIMARY KEY,
                appointment_id INT NOT NULL,
                user_id INT NOT NULL,
                encrypted_content LONGTEXT NOT NULL,
                created_at TIMESTAMP
                    NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP
                    NOT NULL DEFAULT CURRENT_TIMESTAMP
                    ON UPDATE CURRENT_TIMESTAMP,

                CONSTRAINT fk_notes_appointment
                    FOREIGN KEY (appointment_id)
                    REFERENCES appointments(id)
                    ON DELETE CASCADE,

                CONSTRAINT fk_notes_user
                    FOREIGN KEY (user_id)
                    REFERENCES users(id)
                    ON DELETE CASCADE
            ) ENGINE=InnoDB",

            /*
             * Prescriptions
             */
            "CREATE TABLE prescriptions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                patient_id INT NOT NULL,
                provider_id INT NOT NULL,
                pharmacist_id INT NULL,
                encrypted_data LONGTEXT NOT NULL,
                status VARCHAR(50) NOT NULL DEFAULT 'active',
                created_at TIMESTAMP
                    NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP
                    NOT NULL DEFAULT CURRENT_TIMESTAMP
                    ON UPDATE CURRENT_TIMESTAMP,

                CONSTRAINT fk_prescriptions_patient
                    FOREIGN KEY (patient_id)
                    REFERENCES patients(id)
                    ON DELETE CASCADE,

                CONSTRAINT fk_prescriptions_provider
                    FOREIGN KEY (provider_id)
                    REFERENCES users(id)
                    ON DELETE CASCADE,

                CONSTRAINT fk_prescriptions_pharmacist
                    FOREIGN KEY (pharmacist_id)
                    REFERENCES users(id)
                    ON DELETE SET NULL
            ) ENGINE=InnoDB",

            /*
             * Messages
             */
            "CREATE TABLE messages (
                id INT AUTO_INCREMENT PRIMARY KEY,
                appointment_id INT NULL,
                sender_id INT NOT NULL,
                receiver_id INT NOT NULL,
                encrypted_content LONGTEXT NOT NULL,
                created_at TIMESTAMP
                    NOT NULL DEFAULT CURRENT_TIMESTAMP,

                CONSTRAINT fk_messages_appointment
                    FOREIGN KEY (appointment_id)
                    REFERENCES appointments(id)
                    ON DELETE SET NULL,

                CONSTRAINT fk_messages_sender
                    FOREIGN KEY (sender_id)
                    REFERENCES users(id)
                    ON DELETE CASCADE,

                CONSTRAINT fk_messages_receiver
                    FOREIGN KEY (receiver_id)
                    REFERENCES users(id)
                    ON DELETE CASCADE
            ) ENGINE=InnoDB",

            /*
             * Invoices
             */
            "CREATE TABLE invoices (
                id INT AUTO_INCREMENT PRIMARY KEY,
                patient_id INT NOT NULL,
                appointment_id INT NULL,
                invoice_number VARCHAR(100) NOT NULL UNIQUE,
                amount DECIMAL(12,2) NOT NULL,
                status VARCHAR(50) NOT NULL DEFAULT 'pending',
                created_at TIMESTAMP
                    NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP
                    NOT NULL DEFAULT CURRENT_TIMESTAMP
                    ON UPDATE CURRENT_TIMESTAMP,

                CONSTRAINT fk_invoices_patient
                    FOREIGN KEY (patient_id)
                    REFERENCES patients(id)
                    ON DELETE CASCADE,

                CONSTRAINT fk_invoices_appointment
                    FOREIGN KEY (appointment_id)
                    REFERENCES appointments(id)
                    ON DELETE SET NULL
            ) ENGINE=InnoDB",

            /*
             * Payments
             */
            "CREATE TABLE payments (
                id INT AUTO_INCREMENT PRIMARY KEY,
                invoice_id INT NOT NULL,
                amount DECIMAL(12,2) NOT NULL,
                status VARCHAR(50) NOT NULL DEFAULT 'pending',
                paid_at TIMESTAMP NULL,
                created_at TIMESTAMP
                    NOT NULL DEFAULT CURRENT_TIMESTAMP,

                CONSTRAINT fk_payments_invoice
                    FOREIGN KEY (invoice_id)
                    REFERENCES invoices(id)
                    ON DELETE CASCADE
            ) ENGINE=InnoDB",

            /*
             * Refresh tokens
             */
            "CREATE TABLE refresh_tokens (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                token_hash VARCHAR(255) NOT NULL,
                expires_at DATETIME NOT NULL,
                created_at TIMESTAMP
                    NOT NULL DEFAULT CURRENT_TIMESTAMP,
                revoked TINYINT(1)
                    NOT NULL DEFAULT 0,

                CONSTRAINT fk_refresh_tokens_user
                    FOREIGN KEY (user_id)
                    REFERENCES users(id)
                    ON DELETE CASCADE
            ) ENGINE=InnoDB"
        ];

        foreach ($tables as $sql) {
            $db->exec($sql);
        }
    }

    private static function createRoles(PDO $db): void
    {
        $roles = [
            'Admin',
            'Provider',
            'Nurse',
            'Patient',
            'Pharmacist'
        ];

        $stmt = $db->prepare(
            "INSERT INTO roles (name)
             VALUES (:name)"
        );

        foreach ($roles as $role) {
            $stmt->execute([
                ':name' => $role
            ]);
        }
    }

    private static function createAdmin(
        PDO $db,
        string $adminName,
        string $adminEmail,
        string $adminPasswordHash
    ): void {
        $stmt = $db->prepare(
            "INSERT INTO users
            (name, email, password_hash, status)
         VALUES
            (:name, :email, :password_hash, 'active')"
        );

        $stmt->execute([
            ':name' => $adminName,
            ':email' => $adminEmail,
            ':password_hash' => $adminPasswordHash
        ]);

        $userId = (int) $db->lastInsertId();

        $roleStmt = $db->prepare(
            "SELECT id
         FROM roles
         WHERE name = 'Admin'
         LIMIT 1"
        );

        $roleStmt->execute();

        $roleId = $roleStmt->fetchColumn();

        if ($roleId === false) {
            throw new Exception(
                'Admin role was not created'
            );
        }

        $assignStmt = $db->prepare(
            "INSERT INTO user_roles
            (user_id, role_id)
         VALUES
            (:user_id, :role_id)"
        );

        $assignStmt->execute([
            ':user_id' => $userId,
            ':role_id' => (int) $roleId
        ]);
    }

    private static function validateDatabaseName(
        string $dbName
    ): void {
        if (
            preg_match(
                '/^heal_tenant_[0-9]+$/',
                $dbName
            ) !== 1
        ) {
            throw new InvalidArgumentException(
                'Invalid tenant database name.'
            );
        }
    }
}
