<?php

namespace App\Controllers;

use App\Repositories\DashboardRepository;
use App\Services\DashboardService;
use Exception;

class DashboardController
{
    /**
     * GET /dashboard
     */
    public static function dashboard(
        object $auth
    ): void {
        try {
            $tenantId = (int) (
                $auth->tenant_id ?? 0
            );

            $userId = (int) (
                $auth->sub ?? 0
            );

            $roles = (array) (
                $auth->roles ?? []
            );

            $service = new DashboardService(
                new DashboardRepository(
                    \Database::connect()
                )
            );

            $dashboard = $service->getDashboard(
                $tenantId,
                $roles,
                $userId
            );

            \Response::success(
                $dashboard,
                'Dashboard data retrieved successfully.'
            );
        } catch (Exception $e) {
            \Response::error(
                $e->getMessage(),
                400
            );
        }
    }

    /**
     * GET /reports/appointments
     */
    public static function appointmentReport(
        object $auth
    ): void {
        try {
            $tenantId = (int) (
                $auth->tenant_id ?? 0
            );

            $userId = (int) (
                $auth->sub ?? 0
            );

            $roles = (array) (
                $auth->roles ?? []
            );

            $service = new DashboardService(
                new DashboardRepository(
                    \Database::connect()
                )
            );

            $report = $service->getAppointmentReport(
                $tenantId,
                $roles,
                $userId
            );

            \Response::success(
                $report,
                'Appointment report retrieved successfully.'
            );
        } catch (Exception $e) {
            \Response::error(
                $e->getMessage(),
                400
            );
        }
    }

    /**
     * GET /reports/prescriptions
     */
    public static function prescriptionReport(
        object $auth
    ): void {
        try {
            $tenantId = (int) (
                $auth->tenant_id ?? 0
            );

            $userId = (int) (
                $auth->sub ?? 0
            );

            $roles = (array) (
                $auth->roles ?? []
            );

            $service = new DashboardService(
                new DashboardRepository(
                    \Database::connect()
                )
            );

            $report = $service->getPrescriptionReport(
                $tenantId,
                $roles,
                $userId
            );

            \Response::success(
                $report,
                'Prescription report retrieved successfully.'
            );
        } catch (Exception $e) {
            \Response::error(
                $e->getMessage(),
                400
            );
        }
    }

    /**
     * GET /analytics/tenant
     */
    public static function tenantAnalytics(
        object $auth
    ): void {
        try {
            $tenantId = (int) (
                $auth->tenant_id ?? 0
            );

            $service = new DashboardService(
                new DashboardRepository(
                    \Database::connect()
                )
            );

            $analytics = $service->getTenantAnalytics(
                $tenantId
            );

            \Response::success(
                $analytics,
                'Tenant analytics retrieved successfully.'
            );
        } catch (Exception $e) {
            \Response::error(
                $e->getMessage(),
                400
            );
        }
    }
}