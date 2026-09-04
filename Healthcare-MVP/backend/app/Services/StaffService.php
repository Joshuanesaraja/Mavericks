<?php

require_once __DIR__ . '/../Repositories/UserRepository.php';

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

                    // Only active staff should appear
                    if (($user['status'] ?? '') !== 'active') {
                        return false;
                    }

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

        // Verify user belongs to this tenant
        $user = $this->userRepository->findById(
            $userId,
            $tenantId
        );

        if ($user === null) {
            throw new RuntimeException(
                'User not found.'
            );
        }

        // Convert provider -> Provider
        $roleName = ucfirst($role);

        $roleId = $this->userRepository->findRoleIdByName(
            $roleName
        );

        if ($roleId === null) {
            throw new RuntimeException(
                'Role not found.'
            );
        }

        // Remove existing roles
        $this->userRepository->removeRoles(
            $userId,
            $tenantId
        );

        // Assign new staff role
        return $this->userRepository->assignRole(
            $userId,
            $roleId
        );
    }

    public function deactivateStaff(
        int $userId,
        int $tenantId
    ): bool {
        // Verify user belongs to this tenant
        $user = $this->userRepository->findById(
            $userId,
            $tenantId
        );

        if ($user === null) {
            throw new RuntimeException(
                'Staff not found.'
            );
        }

        // Soft delete = deactivate the user
        return $this->userRepository->updateStatus(
            $userId,
            $tenantId,
            'inactive'
        );
    }
}
