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
     *   Tenant-wide data.
     *
     * Provider:
     *   Provider-specific patient,
     *   appointment and prescription data.
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
                    $providerId
                ),

            'appointments' =>
                $this->repository->getAppointmentStatistics(
                    $providerId
                ),

            'prescriptions' =>
                $this->repository->getPrescriptionSummary(
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
                $this->repository
                    ->getAppointmentStatistics(
                        $providerId
                    ),

            'status_breakdown' =>
                $this->repository
                    ->getAppointmentStatusBreakdown(
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
                $this->repository
                    ->getPrescriptionSummary(
                        $providerId
                    ),

            'status_breakdown' =>
                $this->repository
                    ->getPrescriptionStatusBreakdown(
                        $providerId
                    )
        ];
    }

    /**
     * Get tenant-wide analytics.
     */
    public function getTenantAnalytics(
        int $tenantId
    ): array {
        if ($tenantId <= 0) {
            throw new Exception(
                'Invalid tenant.'
            );
        }

        $analytics =
            $this->repository->getTenantAnalytics();

        /*
         * Tenant ID is Master DB metadata.
         * It is not a column in the tenant tables.
         */
        $analytics['tenant_id'] = $tenantId;

        return $analytics;
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
        if (
            in_array(
                'Admin',
                $roles,
                true
            )
        ) {
            return null;
        }

        if (
            in_array(
                'Provider',
                $roles,
                true
            ) &&
            $userId > 0
        ) {
            return $userId;
        }

        throw new Exception(
            'You are not authorized to access dashboard reports.'
        );
    }
}
