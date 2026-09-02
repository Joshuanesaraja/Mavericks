<?php

namespace App\Services;

use App\Repositories\UserRepository;
use RuntimeException;

class UserService
{
    private UserRepository $repository;

    public function __construct(UserRepository $repository)
    {
        $this->repository = $repository;
    }

    public function getUsers(int $tenantId): array
    {
        return $this->repository->findAllByTenant($tenantId);
    }

    public function getUser(
        int $userId,
        int $tenantId
    ): array {
        $user = $this->repository->findById(
            $userId,
            $tenantId
        );

        if (!$user) {
            throw new RuntimeException('User not found.');
        }

        return $user;
    }

    public function assignRole(
        int $userId,
        int $tenantId,
        string $roleName
    ): bool {
        // Confirm the user belongs to this tenant.
        $user = $this->repository->findById(
            $userId,
            $tenantId
        );

        if (!$user) {
            throw new RuntimeException('User not found.');
        }

        $roleId = $this->repository->findRoleIdByName(
            $roleName
        );

        if (!$roleId) {
            throw new RuntimeException('Invalid role.');
        }

        $this->repository->removeRoles($userId);

        return $this->repository->assignRole(
            $userId,
            $roleId
        );
    }

    public function updateStatus(
        int $userId,
        int $tenantId,
        string $status
    ): bool {
        if (!in_array($status, ['active', 'inactive'], true)) {
            throw new RuntimeException('Invalid status.');
        }

        return $this->repository->updateStatus(
            $userId,
            $tenantId,
            $status
        );
    }
}