<?php

namespace App\Services;

use App\Repositories\UserRepository;
use RuntimeException;

class StaffService
{
    private UserRepository $userRepository;

    private const STAFF_ROLES = [
        'provider',
        'nurse',
        'pharmacist'
    ];

    public function __construct(UserRepository $userRepository)
    {
        $this->userRepository = $userRepository;
    }

    public function getStaff(int $tenantId): array
    {
        $users = $this->userRepository->findAllByTenant(
            $tenantId
        );

        return array_values(
            array_filter(
                $users,
                function (array $user): bool {
                    foreach ($user['roles'] as $role) {
                        if (in_array(
                            strtolower($role),
                            self::STAFF_ROLES,
                            true
                        )) {
                            return true;
                        }
                    }

                    return false;
                }
            )
        );
    }

    public function assignStaffRole(
        int $userId,
        int $tenantId,
        string $role
    ): bool {
        $role = strtolower(trim($role));

        if (!in_array($role, self::STAFF_ROLES, true)) {
            throw new RuntimeException(
                'Invalid staff role.'
            );
        }

        return $this->userRepository->assignRoleToUserByName(
            $userId,
            $tenantId,
            $role
        );
    }
}