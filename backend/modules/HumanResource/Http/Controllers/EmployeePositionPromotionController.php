<?php

namespace Modules\HumanResource\Http\Controllers;

use Brian2694\Toastr\Facades\Toastr;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Modules\HumanResource\Entities\Employee;
use Modules\HumanResource\Entities\EmployeeProfileExtra;
use Modules\HumanResource\Entities\EmployeeUnitPosting;
use Modules\HumanResource\Entities\EmployeeWorkHistory;
use Modules\HumanResource\Entities\Position;
use Modules\HumanResource\Support\EmployeeServiceHistoryService;
use Modules\HumanResource\Support\OrgUnitRuleService;

class EmployeePositionPromotionController extends Controller
{
    public function __construct()
    {
        $this->middleware(['permission:read_employee'])->only('index', 'export');
        $this->middleware(['permission:update_employee'])->only('store');
    }

    public function index(Request $request, OrgUnitRuleService $orgUnitRuleService)
    {
        $year = (int) $request->query('year', now()->year);
        if ($year < 1950 || $year > 2100) {
            $year = (int) now()->year;
        }

        $managedBranchIds = $this->managedBranchIds($orgUnitRuleService);

        $employees = Employee::query()
            ->where('is_active', true)
            ->with(['position', 'department', 'sub_department'])
            ->orderBy('last_name')
            ->orderBy('first_name');
        $this->applyManagedBranchScope($employees, $managedBranchIds);
        $employees = $employees->get();

        $promotions = EmployeeUnitPosting::query()
            ->with([
                'employee.position',
                'employee.department',
                'employee.sub_department',
                'position',
            ])
            ->whereNotNull('position_id')
            ->whereYear('start_date', $year)
            ->where('note', 'like', '[POSITION_PROMOTION]%')
            ->orderByDesc('start_date')
            ->orderByDesc('id');

        if (is_array($managedBranchIds)) {
            $promotions->whereHas('employee', function ($q) use ($managedBranchIds) {
                $q->whereIn('department_id', $managedBranchIds)
                    ->orWhereIn('sub_department_id', $managedBranchIds);
            });
        }

        $promotions = $promotions->get();

        return view('humanresource::employee.position-promotion.index', [
            'year' => $year,
            'employees' => $employees,
            'positions' => Position::query()->where('is_active', true)->orderBy('position_name')->get(),
            'current_position_labels' => $this->currentPositionLabels($employees),
            'previous_position_labels' => $this->previousPositionLabelMap($promotions),
            'promotion_documents' => $this->promotionDocumentMap($promotions, $year),
            'promotions' => $promotions,
        ]);
    }

    public function export(Request $request, OrgUnitRuleService $orgUnitRuleService)
    {
        $year = (int) $request->query('year', now()->year);
        if ($year < 1950 || $year > 2100) {
            $year = (int) now()->year;
        }

        $managedBranchIds = $this->managedBranchIds($orgUnitRuleService);

        $promotions = EmployeeUnitPosting::query()
            ->with([
                'employee.department',
                'employee.sub_department',
                'position',
            ])
            ->whereNotNull('position_id')
            ->whereYear('start_date', $year)
            ->where('note', 'like', '[POSITION_PROMOTION]%')
            ->orderByDesc('start_date')
            ->orderByDesc('id');

        if (is_array($managedBranchIds)) {
            $promotions->whereHas('employee', function ($q) use ($managedBranchIds) {
                $q->whereIn('department_id', $managedBranchIds)
                    ->orWhereIn('sub_department_id', $managedBranchIds);
            });
        }

        $promotions = $promotions->get();
        $previousPositionLabels = $this->previousPositionLabelMap($promotions);
        $promotionDocuments = $this->promotionDocumentMap($promotions, $year);

        $filename = 'position-promotions-' . $year . '.csv';

        return response()->streamDownload(function () use ($promotions, $previousPositionLabels, $promotionDocuments) {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));

            fputcsv($handle, [
                'ល.រ',
                'អត្តលេខមន្ត្រី',
                'ឈ្មោះ',
                'អង្គភាព',
                'តួនាទីចាស់',
                'តួនាទីថ្មី',
                'ថ្ងៃមានប្រសិទ្ធិភាព',
                'លេខលិខិត',
                'ថ្ងៃខែលិខិត',
                'សម្គាល់',
            ]);

            $row = 1;
            foreach ($promotions as $promotion) {
                $unit = $promotion->employee?->sub_department?->department_name
                    ?: ($promotion->employee?->department?->department_name ?: '-');
                $newPosition = $promotion->position?->position_name_km
                    ?: ($promotion->position?->position_name ?: '-');
                $oldPosition = $previousPositionLabels[(int) $promotion->id] ?? '-';
                $note = trim(str_replace('[POSITION_PROMOTION] |', '', (string) $promotion->note));

                fputcsv($handle, [
                    $row++,
                    $promotion->employee?->employee_id ?: '-',
                    $this->normalizeKhmerText($promotion->employee?->full_name ?: '-'),
                    $this->normalizeKhmerText($unit),
                    $this->normalizeKhmerText($oldPosition),
                    $this->normalizeKhmerText($newPosition),
                    optional($promotion->start_date)->format('Y-m-d') ?: '-',
                    $promotionDocuments[(int) $promotion->id]['document_reference'] ?? '-',
                    $promotionDocuments[(int) $promotion->id]['document_date'] ?? '-',
                    $this->normalizeKhmerText($note !== '' ? $note : '-'),
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function store(Request $request, OrgUnitRuleService $orgUnitRuleService)
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'position_id' => 'required|exists:positions,id',
            'effective_date' => 'required|date',
            'document_reference' => 'nullable|string|max:191',
            'document_date' => 'nullable|date',
            'note' => 'nullable|string',
        ]);

        $employee = Employee::query()->where('id', (int) $validated['employee_id'])->firstOrFail();
        $this->assertCanManageEmployee($employee, $orgUnitRuleService);

        $newPosition = Position::query()
            ->where('id', (int) $validated['position_id'])
            ->where('is_active', true)
            ->firstOrFail();

        $effectiveDate = Carbon::parse($validated['effective_date'])->toDateString();
        $documentDate = !empty($validated['document_date'])
            ? Carbon::parse($validated['document_date'])->toDateString()
            : null;

        $oldPositionId = (int) ($employee->position_id ?? 0);
        if ($oldPositionId === (int) $newPosition->id) {
            Toastr::info('តួនាទីថ្មីដូចតួនាទីបច្ចុប្បន្ន (មិនមានការកែប្រែ)។', 'ព័ត៌មាន');
            return redirect()->route('employee-position-promotions.index', [
                'year' => Carbon::parse($effectiveDate)->year,
            ]);
        }

        $activePrimaryPosting = EmployeeUnitPosting::query()
            ->where('employee_id', $employee->id)
            ->where('is_primary', true)
            ->whereNull('end_date')
            ->latest('id')
            ->first();

        $targetDepartmentId = (int) ($employee->sub_department_id ?: $employee->department_id);
        if (!$targetDepartmentId && $activePrimaryPosting) {
            $targetDepartmentId = (int) $activePrimaryPosting->department_id;
        }

        if ($targetDepartmentId <= 0) {
            return redirect()->back()
                ->withErrors(['employee_id' => 'រកមិនឃើញអង្គភាពបច្ចុប្បន្នរបស់មន្ត្រី។'])
                ->withInput();
        }

        DB::beginTransaction();
        try {
            if ($activePrimaryPosting) {
                $activePrimaryPosting->is_primary = false;
                if (!$activePrimaryPosting->end_date || Carbon::parse($activePrimaryPosting->end_date)->gte($effectiveDate)) {
                    $candidateEndDate = Carbon::parse($effectiveDate)->subDay()->toDateString();
                    $startDate = optional($activePrimaryPosting->start_date)->toDateString();
                    $activePrimaryPosting->end_date = ($startDate && $candidateEndDate < $startDate)
                        ? $effectiveDate
                        : $candidateEndDate;
                }
                $activePrimaryPosting->save();
            }

            $oldPositionLabel = $this->positionNameById($oldPositionId) ?: '-';
            $newPositionLabel = $newPosition->position_name_km ?: $newPosition->position_name;
            $normalizedNote = trim((string) ($validated['note'] ?? ''));

            $postingNoteParts = [
                '[POSITION_PROMOTION]',
                'Old: ' . $oldPositionLabel,
                'New: ' . $newPositionLabel,
            ];
            if (!empty($validated['document_reference'])) {
                $postingNoteParts[] = 'Doc: ' . $validated['document_reference'];
            }
            if (!empty($documentDate)) {
                $postingNoteParts[] = 'DocDate: ' . $documentDate;
            }
            if ($normalizedNote !== '') {
                $postingNoteParts[] = $normalizedNote;
            }

            $newPosting = EmployeeUnitPosting::create([
                'employee_id' => $employee->id,
                'department_id' => $targetDepartmentId,
                'position_id' => $newPosition->id,
                'start_date' => $effectiveDate,
                'end_date' => null,
                'is_primary' => true,
                'note' => implode(' | ', $postingNoteParts),
            ]);

            $employee->position_id = $newPosition->id;
            $employee->save();

            EmployeeProfileExtra::updateOrCreate(
                ['employee_id' => $employee->id],
                [
                    'current_position_start_date' => $effectiveDate,
                    'current_position_document_number' => $validated['document_reference'] ?: null,
                    'current_position_document_date' => $documentDate,
                ]
            );

            $workHistoryNoteParts = [
                'តួនាទីចាស់: ' . $oldPositionLabel,
                'តួនាទីថ្មី: ' . $newPositionLabel,
            ];
            if ($normalizedNote !== '') {
                $workHistoryNoteParts[] = $normalizedNote;
            }

            EmployeeWorkHistory::create([
                'employee_id' => $employee->id,
                'work_status_name' => 'ឡើងតួនាទី',
                'start_date' => $effectiveDate,
                'document_reference' => $validated['document_reference'] ?: null,
                'document_date' => $documentDate,
                'note' => implode(' | ', $workHistoryNoteParts),
            ]);

            $this->historyService()->log(
                $employee->id,
                'position_change',
                'Position changed',
                "Changed position from {$oldPositionLabel} to {$newPositionLabel}",
                $effectiveDate,
                $oldPositionLabel,
                $newPositionLabel,
                'employee_unit_posting',
                $newPosting->id,
                [
                    'document_reference' => $validated['document_reference'] ?: null,
                    'document_date' => $documentDate,
                ]
            );

            DB::commit();
            Toastr::success('បានកែប្រែតួនាទី និងកត់ត្រាប្រវត្តិការងាររួចរាល់។', 'ជោគជ័យ');

            return redirect()->route('employee-position-promotions.index', [
                'year' => Carbon::parse($effectiveDate)->year,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            activity()
                ->causedBy(auth()->user())
                ->log('An error occurred: ' . $e->getMessage());

            Toastr::error('រក្សាទុកការឡើងតួនាទីមិនបានជោគជ័យ។', 'បរាជ័យ');
            return redirect()->back()->withInput();
        }
    }

    protected function currentPositionLabels($employees): array
    {
        $labels = [];
        foreach ($employees as $employee) {
            $labels[(int) $employee->id] = $employee->position?->position_name_km
                ?: ($employee->position?->position_name ?: '-');
        }
        return $labels;
    }

    protected function previousPositionLabelMap($promotions): array
    {
        $result = [];
        $employeeIds = collect($promotions)->pluck('employee_id')->map(function ($id) {
            return (int) $id;
        })->unique()->values()->all();

        if (empty($employeeIds)) {
            return $result;
        }

        $postingsByEmployee = EmployeeUnitPosting::query()
            ->whereIn('employee_id', $employeeIds)
            ->whereNotNull('position_id')
            ->orderBy('start_date')
            ->orderBy('id')
            ->get()
            ->groupBy('employee_id');

        foreach ($promotions as $promotion) {
            $rows = collect($postingsByEmployee->get((int) $promotion->employee_id, []))->values();
            $index = $rows->search(function ($row) use ($promotion) {
                return (int) $row->id === (int) $promotion->id;
            });

            if ($index === false || $index === 0) {
                $result[(int) $promotion->id] = '-';
                continue;
            }

            $previous = $rows->get($index - 1);
            $result[(int) $promotion->id] = $this->positionNameById((int) ($previous->position_id ?? 0)) ?: '-';
        }

        return $result;
    }

    protected function promotionDocumentMap($promotions, int $year): array
    {
        $result = [];
        $employeeIds = collect($promotions)->pluck('employee_id')->map(function ($id) {
            return (int) $id;
        })->unique()->values()->all();

        if (empty($employeeIds)) {
            return $result;
        }

        $workHistoryByEmployeeDate = EmployeeWorkHistory::query()
            ->whereIn('employee_id', $employeeIds)
            ->where('work_status_name', 'ឡើងតួនាទី')
            ->whereYear('start_date', $year)
            ->orderByDesc('id')
            ->get()
            ->groupBy(function ($row) {
                return (int) $row->employee_id . '|' . (string) optional($row->start_date)->format('Y-m-d');
            });

        foreach ($promotions as $promotion) {
            $key = (int) $promotion->employee_id . '|' . (string) optional($promotion->start_date)->format('Y-m-d');
            $history = collect($workHistoryByEmployeeDate->get($key, []))->first();
            $result[(int) $promotion->id] = [
                'document_reference' => $history?->document_reference ?: null,
                'document_date' => optional($history?->document_date)->format('Y-m-d') ?: null,
            ];
        }

        return $result;
    }

    protected function positionNameById(?int $id): ?string
    {
        if (!$id) {
            return null;
        }

        $position = Position::withoutGlobalScopes()->find($id);
        return $position?->position_name_km ?: $position?->position_name;
    }

    protected function normalizeKhmerText(?string $text): string
    {
        $value = trim((string) $text);
        if ($value === '') {
            return '';
        }

        $looksMojibake = str_contains($value, 'á') || str_contains($value, 'â') || str_contains($value, 'Ã');
        if (!$looksMojibake) {
            return $value;
        }

        $iconv = @iconv('Windows-1252', 'UTF-8//IGNORE', $value);
        if (is_string($iconv) && $iconv !== '' && preg_match('/\p{Khmer}/u', $iconv)) {
            return trim($iconv);
        }

        $mb = @mb_convert_encoding($value, 'UTF-8', 'Windows-1252');
        if (is_string($mb) && $mb !== '' && preg_match('/\p{Khmer}/u', $mb)) {
            return trim($mb);
        }

        return $value;
    }

    protected function historyService(): EmployeeServiceHistoryService
    {
        return app(EmployeeServiceHistoryService::class);
    }

    protected function managedBranchIds(OrgUnitRuleService $orgUnitRuleService): ?array
    {
        if ($this->isSystemAdmin()) {
            return null;
        }

        $rootUnitId = $this->currentUserRootUnitId();
        if (!$rootUnitId) {
            return [];
        }

        return $orgUnitRuleService->branchIdsIncludingSelf($rootUnitId);
    }

    protected function applyManagedBranchScope($query, ?array $managedBranchIds): void
    {
        if (!is_array($managedBranchIds)) {
            return;
        }

        if (empty($managedBranchIds)) {
            $query->whereRaw('1=0');
            return;
        }

        $query->where(function ($q) use ($managedBranchIds) {
            $q->whereIn('department_id', $managedBranchIds)
                ->orWhereIn('sub_department_id', $managedBranchIds);
        });
    }

    protected function assertCanManageEmployee(Employee $employee, OrgUnitRuleService $orgUnitRuleService): void
    {
        $managedBranchIds = $this->managedBranchIds($orgUnitRuleService);
        if (!is_array($managedBranchIds)) {
            return;
        }

        $employeeUnitId = $this->employeeAssignedUnitId($employee);
        if (!$employeeUnitId || !in_array($employeeUnitId, $managedBranchIds, true)) {
            abort(403, 'អ្នកអាចគ្រប់គ្រងបានតែមន្ត្រីក្នុងអង្គភាពរបស់ខ្លួនប៉ុណ្ណោះ។');
        }
    }

    protected function isSystemAdmin(): bool
    {
        $user = auth()->user();
        return $user && (int) $user->user_type_id === 1;
    }

    protected function currentUserRootUnitId(): ?int
    {
        $user = auth()->user();
        if (!$user) {
            return null;
        }

        $employee = $user->employee()->with('primaryUnitPosting')->first();
        if (!$employee) {
            return null;
        }

        $rootUnitId = $this->employeeAssignedUnitId($employee);
        if ($rootUnitId) {
            return $rootUnitId;
        }

        $postedUnitId = (int) optional($employee->primaryUnitPosting)->department_id;
        return $postedUnitId > 0 ? $postedUnitId : null;
    }

    protected function employeeAssignedUnitId(Employee $employee): ?int
    {
        $unitId = (int) ($employee->sub_department_id ?: $employee->department_id);
        if ($unitId > 0) {
            return $unitId;
        }

        $postedUnitId = (int) optional($employee->primaryUnitPosting)->department_id;
        return $postedUnitId > 0 ? $postedUnitId : null;
    }
}
