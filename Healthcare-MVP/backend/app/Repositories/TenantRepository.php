<?php

require_once __DIR__ . '/../Config/database.php';

class TenantRepository
{
    private PDO $db;

    public function __construct()
    {
        // Tenant information is stored in the Master DB.
        $this->db = Database::master();
    }

    /**
     * Find an active tenant by ID.
     *
     * Used after JWT authentication to identify
     * which tenant database the user belongs to.
     */
    public function findActiveById(int $tenantId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT
                id,
                name,
                subdomain,
                status,
                trial_end,
                subscription_status,
                db_name,
                db_host,
                db_user,
                db_password,
                created_at
             FROM tenants
             WHERE id = :id
               AND status = :status
             LIMIT 1'
        );

        $stmt->execute([
            ':id' => $tenantId,
            ':status' => 'active'
        ]);

        $tenant = $stmt->fetch();

        return $tenant ?: null;
    }

    /**
     * Find an active tenant by subdomain.
     *
     * Used during tenant resolution from the
     * hospital subdomain.
     */
    public function findActiveBySubdomain(string $subdomain): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT
            id,
            name,
            subdomain,
            status,
            trial_end,
            subscription_status,
            db_name,
            db_host,
            db_user,
            db_password,
            created_at
         FROM tenants
         WHERE subdomain = :subdomain
           AND status = :status
           AND (
                trial_end IS NULL
                OR trial_end >= CURDATE()
                OR subscription_status = :subscription_status
           )
         LIMIT 1'
        );

        $stmt->execute([
            ':subdomain' => $subdomain,
            ':status' => 'active',
            ':subscription_status' => 'paid'
        ]);

        $tenant = $stmt->fetch();

        return $tenant ?: null;
    }
}
