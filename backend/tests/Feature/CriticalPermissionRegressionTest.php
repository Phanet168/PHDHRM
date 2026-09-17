<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Focused regression tests for the Phase 1.5 critical authorization fixes.
 * These assert route-level middleware only (no DB writes), mirroring the
 * pattern already used in EmployeeReportGeneratorTest.
 */
class CriticalPermissionRegressionTest extends TestCase
{
    private function middlewareFor(string $routeName): array
    {
        $route = app('router')->getRoutes()->getByName($routeName);
        $this->assertNotNull($route, "Route [{$routeName}] should exist.");

        return $route->gatherMiddleware();
    }

    public function test_currency_edit_and_update_require_update_currency_permission(): void
    {
        $this->assertContains('permission:update_currency', $this->middlewareFor('currencies.edit'));
        $this->assertContains('permission:update_currency', $this->middlewareFor('currencies.update'));
    }

    public function test_employee_report_template_writes_require_dedicated_permissions(): void
    {
        $this->assertContains('permission:create_employee_report', $this->middlewareFor('reports.employee-report-templates.store'));
        $this->assertContains('permission:update_employee_report', $this->middlewareFor('reports.employee-report-templates.update'));
        $this->assertContains('permission:delete_employee_report', $this->middlewareFor('reports.employee-report-templates.destroy'));
        // Viewing/exporting still only needs read access, as before this fix.
        $this->assertContains('permission:read_employee_report', $this->middlewareFor('reports.employee-report-templates.index'));
    }

    public function test_previously_unprotected_hr_report_routes_now_require_permission(): void
    {
        $this->assertContains('permission:read_job_card_report', $this->middlewareFor('reports.job_card_reports'));
        $this->assertContains('permission:read_monthly_attendance', $this->middlewareFor('reports.monthly-report'));
        $this->assertContains('permission:read_attendance_report', $this->middlewareFor('reports.attendance-log'));
        $this->assertContains('permission:read_attendance_report', $this->middlewareFor('reports.daily-present'));
        $this->assertContains('permission:read_salary_advance', $this->middlewareFor('reports.salary-advance'));
        $this->assertContains('permission:read_adhoc_report', $this->middlewareFor('reports.adhoc-advance-show'));
        $this->assertContains('permission:read_payroll_report', $this->middlewareFor('reports.iicf3-contribution-report-show'));
        $this->assertContains('permission:read_payroll_report', $this->middlewareFor('reports.salary-confirmation-pdf'));
    }

    public function test_supplier_wise_sale_profit_report_now_requires_permission(): void
    {
        $this->assertContains(
            'permission:read_supplier_wise_sale_profit',
            $this->middlewareFor('report.supplier-wise-sale-profit-report')
        );
    }

    public function test_phase2_report_followup_permissions_exist_and_are_unassigned(): void
    {
        foreach ([
            'read_sale_report_casher',
            'read_category_wise_sales_report',
            'read_warehouse_wise_product',
        ] as $permissionName) {
            $permission = \Spatie\Permission\Models\Permission::where('name', $permissionName)->first();
            $this->assertNotNull($permission, "Permission [{$permissionName}] should exist.");
            $this->assertSame(
                0,
                $permission->roles()->count(),
                "Permission [{$permissionName}] should not be auto-assigned to any role."
            );
        }
    }

    public function test_phase2_report_followup_routes_require_the_new_permissions(): void
    {
        $this->assertContains('permission:read_sale_report_casher', $this->middlewareFor('report.sale-report-casher'));
        $this->assertContains('permission:read_category_wise_sales_report', $this->middlewareFor('report.category_wise_sale_report'));

        $warehouseGetResponseRoute = collect(app('router')->getRoutes())
            ->first(fn ($route) => $route->uri() === 'report/warehouse-wise-product-report/get-response');
        $this->assertNotNull($warehouseGetResponseRoute, 'Warehouse wise product report get-response route should exist.');
        $this->assertContains('permission:read_warehouse_wise_product', $warehouseGetResponseRoute->gatherMiddleware());
    }
}
