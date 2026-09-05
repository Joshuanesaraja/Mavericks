<?php

require_once __DIR__ . '/../Config/database.php';

class TenantRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::master();
    }

    /**
     * Find an active tenant by ID.
     */
    public function findActiveById(int $tenantId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT id, tenant_code, name, status
             FROM tenants
             WHERE id = :id
             AND status = 'active'
             LIMIT 1"
        );

        $stmt->execute([
            'id' => $tenantId
        ]);

        $tenant = $stmt->fetch();

        return $tenant ?: null;
    }

    /**
     * Find an active tenant by tenant code.
     */
    public function findActiveByCode(string $tenantCode): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT id, tenant_code, name, status
             FROM tenants
             WHERE tenant_code = :tenant_code
             AND status = 'active'
             LIMIT 1"
        );

        $stmt->execute([
            'tenant_code' => $tenantCode
        ]);

        $tenant = $stmt->fetch();

        return $tenant ?: null;
    }
}
