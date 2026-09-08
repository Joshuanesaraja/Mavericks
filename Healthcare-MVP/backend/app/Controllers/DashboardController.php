<?php

namespace App\Controllers;

use App\Repositories\DashboardRepository;
use App\Services\DashboardService;
use Exception;

class DashboardController
{
    /**
     * Create DashboardService using
     * the authenticated tenant database.
     */
    private static function service(object $auth): DashboardService
    {
        $dbName = (string) (
            $auth->tenant_db_name ?? ''
        );

        $dbHost = (string) (
            $auth->tenant_db_host ?? ''
        );

        $dbUser = (string) (
            $auth->tenant_db_user ?? ''
        );

        $dbPassword = (string) (
            $auth->tenant_db_password ?? ''
        );

        if (
            $dbName === '' ||
            $dbHost === '' ||
            $dbUser === '' ||
            $dbPassword === ''
        ) {
            throw new Exception(
                'Tenant database credentials are missing.'
            );
        }

        $db = \Database::tenant(
            $dbName,
            $dbUser,
            $dbPassword
        );

        return new DashboardService(
            new DashboardRepository($db)
        );
    }

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

            $service = self::service($auth);

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

            $service = self::service($auth);

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

            $service = self::service($auth);

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

            $service = self::service($auth);

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