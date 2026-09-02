<?php

namespace App\Controllers;

use App\Services\StaffService;
use RuntimeException;

class StaffController
{
    private StaffService $service;

    public function __construct(StaffService $service)
    {
        $this->service = $service;
    }

    public function index(array $authUser): array
    {
        return $this->service->getStaff(
            (int) $authUser['tenant_id']
        );
    }

    public function assignRole(
        int $userId,
        array $data,
        array $authUser
    ): array {
        $role = $data['role'] ?? null;

        if (!is_string($role) || trim($role) === '') {
            throw new RuntimeException(
                'Role is required.'
            );
        }

        $this->service->assignStaffRole(
            $userId,
            (int) $authUser['tenant_id'],
            $role
        );

        return [
            'message' => 'Staff role assigned successfully.'
        ];
    }
}