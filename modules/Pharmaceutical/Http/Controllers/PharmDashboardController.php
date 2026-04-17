<?php

namespace Modules\Pharmaceutical\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\HumanResource\Entities\Department;
use Modules\Pharmaceutical\Entities\PharmDispensing;
use Modules\Pharmaceutical\Entities\PharmDistribution;
use Modules\Pharmaceutical\Entities\PharmFacilityStock;
use Modules\Pharmaceutical\Entities\PharmMedicine;
use Modules\Pharmaceutical\Entities\PharmReport;
use Modules\Pharmaceutical\Traits\PharmScope;

class PharmDashboardController extends Controller
{
    use PharmScope;

    private function stockDepartments(?array $deptIds)
    {
        $facilityTypes = array_keys(\Modules\HumanResource\Support\OrgScopeService::LEVEL_MAP);

        return Department::withoutGlobalScopes()
            ->where('is_active', 1)
            ->whereIn('unit_type_id', $facilityTypes)
            ->when($deptIds !== null, fn ($q) => $q->whereIn('id', $deptIds))
            ->orderBy('department_name')
            ->get(['id', 'department_name', 'unit_type_id']);
    }

    private function stockQuery(?array $deptIds, int $departmentId, string $search, bool $summary)
    {
        if ($summary) {
            return PharmFacilityStock::query()
                ->join('pharm_medicines as m', 'm.id', '=', 'pharm_facility_stocks.medicine_id')
                ->leftJoin('departments as d', 'd.id', '=', 'pharm_facility_stocks.department_id')
                ->leftJoin('pharm_categories as c', 'c.id', '=', 'm.category_id')
                ->select([
                    'pharm_facility_stocks.department_id',
                    'pharm_facility_stocks.medicine_id',
                    'd.department_name',
                    'm.name as medicine_name',
                    'm.code as medicine_code',
                    'm.unit as medicine_unit',
                    'c.name as category_name',
                    \DB::raw('SUM(pharm_facility_stocks.quantity) as total_quantity'),
                    \DB::raw('MIN(pharm_facility_stocks.expiry_date) as nearest_expiry'),
                ])
                ->whereNull('pharm_facility_stocks.deleted_at')
                ->when($deptIds !== null, fn ($q) => $q->whereIn('pharm_facility_stocks.department_id', $deptIds))
                ->when($departmentId > 0, fn ($q) => $q->where('pharm_facility_stocks.department_id', $departmentId))
                ->when($search !== '', fn ($q) => $q->where(fn ($sq) => $sq
                    ->where('m.name', 'like', "%{$search}%")
                    ->orWhere('m.code', 'like', "%{$search}%")
                ))
                ->groupBy(
                    'pharm_facility_stocks.department_id',
                    'pharm_facility_stocks.medicine_id',
                    'd.department_name',
                    'm.name',
                    'm.code',
                    'm.unit',
                    'c.name'
                )
                ->having(\DB::raw('SUM(pharm_facility_stocks.quantity)'), '>', 0)
                ->orderBy('d.department_name')
                ->orderBy('m.name');
        }

        return PharmFacilityStock::query()
            ->with(['department', 'medicine.category'])
            ->when($deptIds !== null, fn ($q) => $q->whereIn('department_id', $deptIds))
            ->when($departmentId > 0, fn ($q) => $q->where('department_id', $departmentId))
            ->when($search !== '', function ($q) use ($search) {
                $q->whereHas('medicine', fn ($mq) => $mq->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%"));
            })
            ->where('quantity', '>', 0)
            ->latest('updated_at');
    }

    public function index()
    {
        $deptIds = $this->pharmAccessibleDepartmentIds(); // null = all (PHD)
        $level   = $this->pharmLevel();

        $medicineCount = PharmMedicine::where('is_active', true)->count();

        $distQuery = PharmDistribution::query();
        if ($deptIds !== null) {
            $distQuery->where(fn ($q) => $q->whereIn('from_department_id', $deptIds)->orWhereIn('to_department_id', $deptIds));
        }
        $distributionCount = (clone $distQuery)->count();
        $pendingDistributions = (clone $distQuery)->whereIn('status', [PharmDistribution::STATUS_DRAFT, PharmDistribution::STATUS_SENT])->count();

        $rptQuery = PharmReport::query();
        if ($deptIds !== null) {
            $rptQuery->where(fn ($q) => $q->whereIn('department_id', $deptIds)->orWhereIn('parent_department_id', $deptIds));
        }
        $reportCount = (clone $rptQuery)->count();
        $pendingReports = (clone $rptQuery)->whereIn('status', [PharmReport::STATUS_DRAFT, PharmReport::STATUS_SUBMITTED])->count();

        $recentDistributions = PharmDistribution::with(['fromDepartment', 'toDepartment'])
            ->when($deptIds !== null, fn ($q) => $q->where(fn ($qq) => $qq->whereIn('from_department_id', $deptIds)->orWhereIn('to_department_id', $deptIds)))
            ->latest('distribution_date')->limit(5)->get();

        $recentReports = PharmReport::with('department')
            ->when($deptIds !== null, fn ($q) => $q->where(fn ($qq) => $qq->whereIn('department_id', $deptIds)->orWhereIn('parent_department_id', $deptIds)))
            ->latest('period_start')->limit(5)->get();

        $lowStockItems = PharmFacilityStock::with(['department', 'medicine'])
            ->when($deptIds !== null, fn ($q) => $q->whereIn('department_id', $deptIds))
            ->where('quantity', '<=', 10)
            ->where('quantity', '>', 0)
            ->limit(10)->get();

        $expiringSoon = PharmFacilityStock::with(['department', 'medicine'])
            ->when($deptIds !== null, fn ($q) => $q->whereIn('department_id', $deptIds))
            ->where('expiry_date', '<=', now()->addMonths(3))
            ->where('expiry_date', '>=', now())
            ->where('quantity', '>', 0)
            ->limit(10)->get();

        // Dispensing stats (Hospital / HC)
        $dispensingCount = 0;
        $todayDispensingCount = 0;
        if (in_array($level, ['hospital', 'hc'])) {
            $dispensingCount = PharmDispensing::whereIn('department_id', $deptIds)->count();
            $todayDispensingCount = PharmDispensing::whereIn('department_id', $deptIds)->whereDate('dispensing_date', today())->count();
        }

        return view('pharmaceutical::dashboard', compact(
            'medicineCount',
            'distributionCount',
            'pendingDistributions',
            'reportCount',
            'pendingReports',
            'recentDistributions',
            'recentReports',
            'lowStockItems',
            'expiringSoon',
            'level',
            'dispensingCount',
            'todayDispensingCount'
        ));
    }

    public function stock(Request $request)
    {
        $deptIds = $this->pharmAccessibleDepartmentIds();
        $departmentId = (int) $request->query('department_id', 0);
        $search = trim((string) $request->query('search', ''));
        $summary = (bool) $request->query('summary', false);

        $stocks = $this->stockQuery($deptIds, $departmentId, $search, $summary)
            ->paginate(30)
            ->appends($request->query());

        $departments = $this->stockDepartments($deptIds);

        return view('pharmaceutical::stock', compact('stocks', 'departments', 'departmentId', 'search', 'summary'));
    }

    public function stockPrint(Request $request)
    {
        $deptIds = $this->pharmAccessibleDepartmentIds();
        $departmentId = (int) $request->query('department_id', 0);
        $search = trim((string) $request->query('search', ''));
        $summary = (bool) $request->query('summary', false);

        if ($deptIds !== null && $departmentId > 0) {
            abort_unless(in_array($departmentId, $deptIds, true), 403);
        }

        $stocks = $this->stockQuery($deptIds, $departmentId, $search, $summary)->get();
        $departments = $this->stockDepartments($deptIds);
        $facilityName = $departmentId > 0
            ? ($departments->firstWhere('id', $departmentId)?->department_name ?? localize('selected_facility', 'Selected facility'))
            : localize('all_facilities', 'All facilities');

        return view('pharmaceutical::stock-print', compact('stocks', 'departmentId', 'search', 'summary', 'facilityName'));
    }
}
