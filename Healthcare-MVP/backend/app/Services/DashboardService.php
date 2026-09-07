<?php

namespace App\Services;

use App\Repositories\DashboardRepository;
use Exception;

class DashboardService
{
    private DashboardRepository $repository;

    public function __construct(
        DashboardRepository $repository
    ) {
        $this->repository = $repository;
    }

    /**
     * Get the main dashboard summary.
     *
     * Admin:
     *   - Tenant-wide data
     *
     * Provider:
     *   - Provider-specific patient, appointment and prescription data
     */
    public function getDashboard(
        int $tenantId,
        array $roles,
        int $userId
    ): array {
        if ($tenantId <= 0) {
            throw new Exception(
                'Invalid tenant.'
            );
        }

        $providerId = $this->getProviderScope(
            $roles,
            $userId
        );

        return [
            'scope' => $providerId === null
                ? 'tenant'
                : 'provider',

            'tenant_id' => $tenantId,

            'total_patients' =>
                $this->repository->getTotalPatients(
                    $tenantId,
                    $providerId
                ),

            'appointments' =>
                $this->repository->getAppointmentStatistics(
                    $tenantId,
                    $providerId
                ),

            'prescriptions' =>
                $this->repository->getPrescriptionSummary(
                    $tenantId,
                    $providerId
                )
        ];
    }

    /**
     * Get appointment report.
     */
    public function getAppointmentReport(
        int $tenantId,
        array $roles,
        int $userId
    ): array {
        if ($tenantId <= 0) {
            throw new Exception(
                'Invalid tenant.'
            );
        }

        $providerId = $this->getProviderScope(
            $roles,
            $userId
        );

        return [
            'scope' => $providerId === null
                ? 'tenant'
                : 'provider',

            'tenant_id' => $tenantId,

            'statistics' =>
                $this->repository->getAppointmentStatistics(
                    $tenantId,
                    $providerId
                ),

            'status_breakdown' =>
                $this->repository->getAppointmentStatusBreakdown(
                    $tenantId,
                    $providerId
                )
        ];
    }

    /**
     * Get prescription report.
     */
    public function getPrescriptionReport(
        int $tenantId,
        array $roles,
        int $userId
    ): array {
        if ($tenantId <= 0) {
            throw new Exception(
                'Invalid tenant.'
            );
        }

        $providerId = $this->getProviderScope(
            $roles,
            $userId
        );

        return [
            'scope' => $providerId === null
                ? 'tenant'
                : 'provider',

            'tenant_id' => $tenantId,

            'summary' =>
                $this->repository->getPrescriptionSummary(
                    $tenantId,
                    $providerId
                ),

            'status_breakdown' =>
                $this->repository->getPrescriptionStatusBreakdown(
                    $tenantId,
                    $providerId
                )
        ];
    }

    /**
     * Get tenant-wide analytics.
     *
     * Only aggregate information is returned.
     */
    public function getTenantAnalytics(
        int $tenantId
    ): array {
        if ($tenantId <= 0) {
            throw new Exception(
                'Invalid tenant.'
            );
        }

        return $this->repository->getTenantAnalytics(
            $tenantId
        );
    }

    /**
     * Determine whether the current user should
     * be restricted to their own provider data.
     *
     * Admin takes precedence over Provider.
     */
    private function getProviderScope(
        array $roles,
        int $userId
    ): ?int {
        if (in_array('Admin', $roles, true)) {
            return null;
        }

        if (
            in_array('Provider', $roles, true) &&
            $userId > 0
        ) {
            return $userId;
        }

        throw new Exception(
            'You are not authorized to access dashboard reports.'
        );
    }
}