<?php

namespace Modules\Pharmaceutical\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\HumanResource\Entities\Department;
use Modules\Pharmaceutical\Services\PharmReportService;
use Modules\Pharmaceutical\Traits\PharmScope;

class PharmSummaryReportController extends Controller
{
    use PharmScope;

    private function getDepartments()
    {
        $deptIds = $this->pharmAccessibleDepartmentIds();
        $facilityTypes = array_keys(\Modules\HumanResource\Support\OrgScopeService::LEVEL_MAP);

        return Department::withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->where('is_active', 1)
            ->whereIn('unit_type_id', $facilityTypes)
            ->when($deptIds !== null, fn ($q) => $q->whereIn('id', $deptIds))
            ->orderBy('department_name')
            ->get(['id', 'department_name']);
    }

    // ── Medicine Usage Report ──
    public function usage(Request $request)
    {
        $departments = $this->getDepartments();
        $deptFilter  = (int) $request->query('dept', 0);
        $periodStart = $request->query('period_start', now()->startOfMonth()->toDateString());
        $periodEnd   = $request->query('period_end', now()->toDateString());
        $reportGenerated = $request->hasAny(['dept', 'period_start', 'period_end']);

        $items = [];
        $totals = ['total_qty' => 0, 'dispense_count' => 0, 'patient_count' => 0, 'current_stock' => 0];

        if ($reportGenerated && $deptFilter > 0) {
            $deptIds = $this->pharmAccessibleDepartmentIds();
            if ($deptIds !== null) {
                abort_unless(in_array($deptFilter, $deptIds, true), 403);
            }
            $svc   = new PharmReportService();
            $items = $svc->usageReport($deptFilter, $periodStart, $periodEnd);

            foreach ($items as $item) {
                $totals['total_qty']      += $item['total_qty'];
                $totals['dispense_count'] += $item['dispense_count'];
                $totals['patient_count']  += $item['patient_count'];
                $totals['current_stock']  += $item['current_stock'];
            }
        }

        if ($reportGenerated && $deptFilter === 0) {
            $svc = new PharmReportService();
            $allDeptIds = $departments->pluck('id')->map(fn ($id) => (int) $id)->toArray();

            $merged = [];
            foreach ($allDeptIds as $deptId) {
                $deptItems = $svc->usageReport($deptId, $periodStart, $periodEnd);
                foreach ($deptItems as $row) {
                    $medId = (int) $row['medicine_id'];
                    if (!isset($merged[$medId])) {
                        $merged[$medId] = $row;
                        continue;
                    }

                    $merged[$medId]['total_qty'] += $row['total_qty'];
                    $merged[$medId]['dispense_count'] += $row['dispense_count'];
                    $merged[$medId]['patient_count'] += $row['patient_count'];
                    $merged[$medId]['current_stock'] += $row['current_stock'];
                }
            }

            $items = array_values($merged);
            usort($items, fn ($a, $b) => $b['total_qty'] <=> $a['total_qty']);

            foreach ($items as $item) {
                $totals['total_qty']      += $item['total_qty'];
                $totals['dispense_count'] += $item['dispense_count'];
                $totals['patient_count']  += $item['patient_count'];
                $totals['current_stock']  += $item['current_stock'];
            }
        }

        $facilityName = $deptFilter > 0
            ? ($departments->firstWhere('id', $deptFilter)?->department_name ?? '-')
            : localize('all_facilities', 'All facilities');

        return view('pharmaceutical::summary-reports.usage', compact(
            'departments', 'deptFilter', 'periodStart', 'periodEnd', 'items', 'totals', 'facilityName', 'reportGenerated'
        ));
    }

    public function usagePrint(Request $request)
    {
        $departments = $this->getDepartments();
        $deptFilter  = (int) $request->query('dept', 0);
        $periodStart = $request->query('period_start', now()->startOfMonth()->toDateString());
        $periodEnd   = $request->query('period_end', now()->toDateString());

        $svc = new PharmReportService();
        $items = [];

        if ($deptFilter > 0) {
            $deptIds = $this->pharmAccessibleDepartmentIds();
            if ($deptIds !== null) {
                abort_unless(in_array($deptFilter, $deptIds, true), 403);
            }

            $items = $svc->usageReport($deptFilter, $periodStart, $periodEnd);
        } else {
            $allDeptIds = $departments->pluck('id')->map(fn ($id) => (int) $id)->toArray();
            $merged = [];

            foreach ($allDeptIds as $deptId) {
                $deptItems = $svc->usageReport($deptId, $periodStart, $periodEnd);
                foreach ($deptItems as $row) {
                    $medId = (int) $row['medicine_id'];
                    if (!isset($merged[$medId])) {
                        $merged[$medId] = $row;
                        continue;
                    }

                    $merged[$medId]['total_qty'] += $row['total_qty'];
                    $merged[$medId]['dispense_count'] += $row['dispense_count'];
                    $merged[$medId]['patient_count'] += $row['patient_count'];
                    $merged[$medId]['current_stock'] += $row['current_stock'];
                }
            }

            $items = array_values($merged);
            usort($items, fn ($a, $b) => $b['total_qty'] <=> $a['total_qty']);
        }

        $totals = ['total_qty' => 0, 'dispense_count' => 0, 'patient_count' => 0, 'current_stock' => 0];
        foreach ($items as $item) {
            $totals['total_qty']      += $item['total_qty'];
            $totals['dispense_count'] += $item['dispense_count'];
            $totals['patient_count']  += $item['patient_count'];
            $totals['current_stock']  += $item['current_stock'];
        }

        $facility = $deptFilter > 0 ? Department::withoutGlobalScopes()->find($deptFilter) : null;
        $facilityName = $deptFilter > 0
            ? ($facility?->department_name ?? '-')
            : localize('all_facilities', 'All facilities');

        return view('pharmaceutical::summary-reports.usage-print', compact(
            'items', 'totals', 'facility', 'periodStart', 'periodEnd', 'facilityName'
        ));
    }

    // ── Opening / Closing Stock Summary Report ──
    public function stockSummary(Request $request)
    {
        $departments = $this->getDepartments();
        $deptFilter  = (int) $request->query('dept', 0);
        $periodStart = $request->query('period_start', now()->startOfMonth()->toDateString());
        $periodEnd   = $request->query('period_end', now()->toDateString());
        $reportGenerated = $request->hasAny(['dept', 'period_start', 'period_end']);

        $items = [];
        $totals = [
            'opening_stock' => 0, 'received_qty' => 0, 'dispensed_qty' => 0,
            'damaged_qty' => 0, 'expired_qty' => 0, 'adjustment_qty' => 0,
            'closing_stock' => 0, 'current_stock' => 0, 'variance' => 0,
        ];

        if ($reportGenerated && $deptFilter > 0) {
            $deptIds = $this->pharmAccessibleDepartmentIds();
            if ($deptIds !== null) {
                abort_unless(in_array($deptFilter, $deptIds, true), 403);
            }
            $svc   = new PharmReportService();
            $items = $svc->stockSummaryReport($deptFilter, $periodStart, $periodEnd);

            foreach ($items as $item) {
                $totals['opening_stock']  += $item['opening_stock'];
                $totals['received_qty']   += $item['received_qty'];
                $totals['dispensed_qty']  += $item['dispensed_qty'];
                $totals['damaged_qty']    += $item['damaged_qty'];
                $totals['expired_qty']    += $item['expired_qty'];
                $totals['adjustment_qty'] += $item['adjustment_qty'];
                $totals['closing_stock']  += $item['closing_stock'];
                $totals['current_stock']  += $item['current_stock'];
                $totals['variance']       += $item['variance'];
            }
        }

        if ($reportGenerated && $deptFilter === 0) {
            $svc = new PharmReportService();
            $allDeptIds = $departments->pluck('id')->map(fn ($id) => (int) $id)->toArray();
            $merged = [];

            foreach ($allDeptIds as $deptId) {
                $deptItems = $svc->stockSummaryReport($deptId, $periodStart, $periodEnd);
                foreach ($deptItems as $row) {
                    $medId = (int) $row['medicine_id'];
                    if (!isset($merged[$medId])) {
                        $merged[$medId] = $row;
                        continue;
                    }

                    $merged[$medId]['opening_stock'] += $row['opening_stock'];
                    $merged[$medId]['received_qty'] += $row['received_qty'];
                    $merged[$medId]['dispensed_qty'] += $row['dispensed_qty'];
                    $merged[$medId]['damaged_qty'] += $row['damaged_qty'];
                    $merged[$medId]['expired_qty'] += $row['expired_qty'];
                    $merged[$medId]['adjustment_qty'] += $row['adjustment_qty'];
                    $merged[$medId]['closing_stock'] += $row['closing_stock'];
                    $merged[$medId]['current_stock'] += $row['current_stock'];
                    $merged[$medId]['variance'] += $row['variance'];
                }
            }

            $items = array_values($merged);
            usort($items, fn ($a, $b) => strcmp($a['medicine_name'], $b['medicine_name']));

            foreach ($items as $item) {
                $totals['opening_stock']  += $item['opening_stock'];
                $totals['received_qty']   += $item['received_qty'];
                $totals['dispensed_qty']  += $item['dispensed_qty'];
                $totals['damaged_qty']    += $item['damaged_qty'];
                $totals['expired_qty']    += $item['expired_qty'];
                $totals['adjustment_qty'] += $item['adjustment_qty'];
                $totals['closing_stock']  += $item['closing_stock'];
                $totals['current_stock']  += $item['current_stock'];
                $totals['variance']       += $item['variance'];
            }
        }

        $facilityName = $deptFilter > 0
            ? ($departments->firstWhere('id', $deptFilter)?->department_name ?? '-')
            : localize('all_facilities', 'All facilities');

        return view('pharmaceutical::summary-reports.stock-summary', compact(
            'departments', 'deptFilter', 'periodStart', 'periodEnd', 'items', 'totals', 'facilityName', 'reportGenerated'
        ));
    }

    public function stockSummaryPrint(Request $request)
    {
        $departments = $this->getDepartments();
        $deptFilter  = (int) $request->query('dept', 0);
        $periodStart = $request->query('period_start', now()->startOfMonth()->toDateString());
        $periodEnd   = $request->query('period_end', now()->toDateString());

        $svc = new PharmReportService();
        $items = [];

        if ($deptFilter > 0) {
            $deptIds = $this->pharmAccessibleDepartmentIds();
            if ($deptIds !== null) {
                abort_unless(in_array($deptFilter, $deptIds, true), 403);
            }

            $items = $svc->stockSummaryReport($deptFilter, $periodStart, $periodEnd);
        } else {
            $allDeptIds = $departments->pluck('id')->map(fn ($id) => (int) $id)->toArray();
            $merged = [];

            foreach ($allDeptIds as $deptId) {
                $deptItems = $svc->stockSummaryReport($deptId, $periodStart, $periodEnd);
                foreach ($deptItems as $row) {
                    $medId = (int) $row['medicine_id'];
                    if (!isset($merged[$medId])) {
                        $merged[$medId] = $row;
                        continue;
                    }

                    $merged[$medId]['opening_stock'] += $row['opening_stock'];
                    $merged[$medId]['received_qty'] += $row['received_qty'];
                    $merged[$medId]['dispensed_qty'] += $row['dispensed_qty'];
                    $merged[$medId]['damaged_qty'] += $row['damaged_qty'];
                    $merged[$medId]['expired_qty'] += $row['expired_qty'];
                    $merged[$medId]['adjustment_qty'] += $row['adjustment_qty'];
                    $merged[$medId]['closing_stock'] += $row['closing_stock'];
                    $merged[$medId]['current_stock'] += $row['current_stock'];
                    $merged[$medId]['variance'] += $row['variance'];
                }
            }

            $items = array_values($merged);
            usort($items, fn ($a, $b) => strcmp($a['medicine_name'], $b['medicine_name']));
        }

        $totals = [
            'opening_stock' => 0, 'received_qty' => 0, 'dispensed_qty' => 0,
            'damaged_qty' => 0, 'expired_qty' => 0, 'adjustment_qty' => 0,
            'closing_stock' => 0, 'current_stock' => 0, 'variance' => 0,
        ];
        foreach ($items as $item) {
            foreach ($totals as $key => &$val) {
                $val += $item[$key];
            }
        }

        $facility = $deptFilter > 0 ? Department::withoutGlobalScopes()->find($deptFilter) : null;
        $facilityName = $deptFilter > 0
            ? ($facility?->department_name ?? '-')
            : localize('all_facilities', 'All facilities');

        return view('pharmaceutical::summary-reports.stock-summary-print', compact(
            'items', 'totals', 'facility', 'periodStart', 'periodEnd', 'facilityName'
        ));
    }
}
