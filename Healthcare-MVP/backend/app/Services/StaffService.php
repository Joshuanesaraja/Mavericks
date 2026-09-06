<?php

require_once __DIR__ . '/../Repositories/StaffRepository.php';
require_once __DIR__ . '/../Security/Hash.php';

class StaffService
{
    private StaffRepository $repository;

    private const STAFF_ROLES = [
        'provider' => 'Provider',
        'nurse' => 'Nurse',
        'pharmacist' => 'Pharmacist'
    ];

    public function __construct(
        StaffRepository $repository
    ) {
        $this->repository = $repository;
    }

    public function getStaff(
        int $tenantId
    ): array {
        return $this->repository->findAllByTenant(
            $tenantId
        );
    }

    public function getStaffMember(
        int $staffId,
        int $tenantId
    ): array {
        $staff = $this->repository->findById(
            $staffId,
            $tenantId
        );

        if (!$staff) {
            throw new RuntimeException(
                'Staff not found.'
            );
        }

        return $staff;
    }

    public function createStaff(
        int $tenantId,
        string $name,
        string $email,
        string $password,
        string $staffType
    ): array {
        $staffType = strtolower(
            trim($staffType)
        );

        if (!isset(
            self::STAFF_ROLES[$staffType]
        )) {
            throw new RuntimeException(
                'Invalid staff type.'
            );
        }

        if (strlen($password) < 8) {
            throw new RuntimeException(
                'Password must be at least 8 characters.'
            );
        }

        if ($this->repository->emailExists(
            $email
        )) {
            throw new RuntimeException(
                'Email already exists.'
            );
        }

        $roleName =
            self::STAFF_ROLES[$staffType];

        $roleId = $this->repository->roleId(
            $roleName
        );

        if ($roleId === null) {
            throw new RuntimeException(
                'Staff role is not configured.'
            );
        }

        $passwordHash = Hash::make(
            $password
        );

        $staffId = $this->repository->create(
            $tenantId,
            $name,
            $email,
            $passwordHash,
            $staffType,
            $roleId
        );

        return $this->getStaffMember(
            $staffId,
            $tenantId
        );
    }

    public function updateStaff(
        int $staffId,
        int $tenantId,
        string $name,
        string $email,
        string $staffType
    ): array {
        $staffType = strtolower(
            trim($staffType)
        );

        if (!isset(
            self::STAFF_ROLES[$staffType]
        )) {
            throw new RuntimeException(
                'Invalid staff type.'
            );
        }

        $staff = $this->getStaffMember(
            $staffId,
            $tenantId
        );

        $userId = (int) $staff['user_id'];

        if ($this->repository->emailExists(
            $email,
            $userId
        )) {
            throw new RuntimeException(
                'Email already exists.'
            );
        }

        $roleName =
            self::STAFF_ROLES[$staffType];

        $roleId = $this->repository->roleId(
            $roleName
        );

        if ($roleId === null) {
            throw new RuntimeException(
                'Staff role is not configured.'
            );
        }

        $updated = $this->repository->update(
            $staffId,
            $tenantId,
            $name,
            $email,
            $staffType,
            $roleId
        );

        if (!$updated) {
            throw new RuntimeException(
                'Staff update failed.'
            );
        }

        return $this->getStaffMember(
            $staffId,
            $tenantId
        );
    }

    public function updateStatus(
        int $staffId,
        int $tenantId,
        string $status
    ): array {
        $status = strtolower(
            trim($status)
        );

        if (!in_array(
            $status,
            ['active', 'inactive'],
            true
        )) {
            throw new RuntimeException(
                'Invalid status.'
            );
        }

        $this->getStaffMember(
            $staffId,
            $tenantId
        );

        if (!$this->repository->updateStatus(
            $staffId,
            $tenantId,
            $status
        )) {
            throw new RuntimeException(
                'Staff status update failed.'
            );
        }

        return $this->getStaffMember(
            $staffId,
            $tenantId
        );
    }

    public function deleteStaff(
        int $staffId,
        int $tenantId
    ): void {
        $this->getStaffMember(
            $staffId,
            $tenantId
        );

        if (!$this->repository->softDelete(
            $staffId,
            $tenantId
        )) {
            throw new RuntimeException(
                'Staff deletion failed.'
            );
        }
    }
}