<?php

namespace Modules\HumanResource\Http\Controllers;

use App\Http\Controllers\Controller;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Modules\HumanResource\Entities\Department;
use Modules\HumanResource\Entities\Employee;
use Modules\HumanResource\Entities\EmployeeServiceHistory;
use Modules\HumanResource\Entities\GovPayLevel;
use Modules\HumanResource\Exports\RetirementListExport;
use Modules\Setting\Entities\Application;
use Modules\HumanResource\Support\EmployeeRetirementService;
use Modules\HumanResource\Support\EmployeeServiceHistoryService;
use Modules\HumanResource\Support\OrgUnitRuleService;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\Process\Process;

class EmployeeRetirementController extends Controller
{
    public function index(Request $request, OrgUnitRuleService $orgUnitRuleService)
    {
        $asOf = Carbon::today();
        $upcomingMonths = max(1, min(60, (int) $request->integer('months', EmployeeRetirementService::DEFAULT_UPCOMING_MONTHS)));
        $retirementAge = EmployeeRetirementService::DEFAULT_RETIREMENT_AGE;
        $managedBranchIds = $this->managedBranchIds($orgUnitRuleService);

        $dueEmployees = $this->retirementDueEmployees($asOf, $retirementAge, $managedBranchIds);
        $upcomingEmployees = $this->retirementUpcomingEmployees($asOf, $upcomingMonths, $retirementAge, $managedBranchIds);

        $retirementHistories = EmployeeServiceHistory::query()
            ->with('employee')
            ->where('event_type', 'retirement')
            ->orderByDesc('event_date')
            ->orderByDesc('id');

        $this->applyHistoryManagedBranchScope($retirementHistories, $managedBranchIds);

        return view('humanresource::employee.retirement.index', [
            'as_of' => $asOf->toDateString(),
            'upcoming_months' => $upcomingMonths,
            'retirement_age' => $retirementAge,
            'due_employees' => $dueEmployees,
            'upcoming_employees' => $upcomingEmployees,
            'retirement_histories' => $retirementHistories->get(),
        ]);
    }

    public function report(Request $request, OrgUnitRuleService $orgUnitRuleService)
    {
        $asOf = Carbon::today();
        $year = max(1900, min(2100, (int) $request->integer('year', (int) $asOf->year)));
        $forecastYears = max(1, min(10, (int) $request->integer('forecast_years', 1)));
        $retiredScope = strtolower((string) $request->query('retired_scope', 'year'));
        if (!in_array($retiredScope, ['year', 'all'], true)) {
            $retiredScope = 'year';
        }

        $retirementAge = EmployeeRetirementService::DEFAULT_RETIREMENT_AGE;
        $forecastStartYear = $year;
        $forecastEndYear = $year + $forecastYears - 1;
        $managedBranchIds = $this->managedBranchIds($orgUnitRuleService);

        $yearlyEmployees = $this->retirementRangeEmployees($year, $year, $retirementAge, $asOf, $managedBranchIds);
        $forecastEmployees = $this->retirementRangeEmployees($forecastStartYear, $forecastEndYear, $retirementAge, $asOf, $managedBranchIds);

        $retiredRecords = EmployeeServiceHistory::query()
            ->with('employee')
            ->where('event_type', 'retirement')
            ->when($retiredScope === 'year', function ($query) use ($year) {
                $query->whereYear('event_date', $year);
            })
            ->orderByDesc('event_date')
            ->orderByDesc('id');

        $this->applyHistoryManagedBranchScope($retiredRecords, $managedBranchIds);

        $templatePath = $this->resolveRetirementTemplatePath();

        return view('humanresource::employee.retirement.report', [
            'as_of' => $asOf->toDateString(),
            'report_year' => $year,
            'forecast_years' => $forecastYears,
            'forecast_start_year' => $forecastStartYear,
            'forecast_end_year' => $forecastEndYear,
            'retired_scope' => $retiredScope,
            'retirement_age' => $retirementAge,
            'yearly_employees' => $yearlyEmployees,
            'forecast_employees' => $forecastEmployees,
            'retired_records' => $retiredRecords->get(),
            'retirement_template_path' => $templatePath,
            'retirement_template_name' => $templatePath ? basename($templatePath) : null,
            'retirement_template_updated_at' => $templatePath ? date('Y-m-d H:i', (int) @filemtime($templatePath)) : null,
        ]);
    }

    public function uploadTemplate(Request $request)
    {
        $request->validate([
            'template_file' => 'required|file|mimes:xlsx|max:51200',
        ]);

        $file = $request->file('template_file');
        Storage::disk('public')->putFileAs('templates', $file, 'retirement_template.xlsx');

        return redirect()
            ->route('employee-retirements.report')
            ->with('success', localize('template_uploaded_successfully', 'Template uploaded successfully.'));
    }

    public function exportReport(Request $request, OrgUnitRuleService $orgUnitRuleService)
    {
        $asOf = Carbon::today();
        $startYear = max(1900, min(2100, (int) $request->integer('year', (int) $asOf->year)));
        $forecastYears = max(1, min(10, (int) $request->integer('forecast_years', 1)));
        $endYear = $startYear + $forecastYears - 1;

        $managedBranchIds = $this->managedBranchIds($orgUnitRuleService);
        $employees = $this->retirementRangeEmployees(
            $startYear,
            $endYear,
            EmployeeRetirementService::DEFAULT_RETIREMENT_AGE,
            $asOf,
            $managedBranchIds
        )->sortBy('retirement_date')->values();

        $fileName = sprintf('retirement_list_%d_%d.xlsx', $startYear, $endYear);
        $exportMeta = $this->buildRetirementExportMeta();

        return Excel::download(
            new RetirementListExport($employees, $startYear, $endYear, $exportMeta),
            $fileName
        );
    }

    public function exportReportPdf(Request $request, OrgUnitRuleService $orgUnitRuleService)
    {
        $asOf = Carbon::today();
        $year = max(1900, min(2100, (int) $request->integer('year', (int) $asOf->year)));
        $forecastYears = max(1, min(10, (int) $request->integer('forecast_years', 1)));
        $retiredScope = strtolower((string) $request->query('retired_scope', 'year'));
        if (!in_array($retiredScope, ['year', 'all'], true)) {
            $retiredScope = 'year';
        }

        $retirementAge = EmployeeRetirementService::DEFAULT_RETIREMENT_AGE;
        $forecastStartYear = $year;
        $forecastEndYear = $year + $forecastYears - 1;
        $managedBranchIds = $this->managedBranchIds($orgUnitRuleService);

        $yearlyEmployees = $this->retirementRangeEmployees($year, $year, $retirementAge, $asOf, $managedBranchIds);
        $forecastEmployees = $this->retirementRangeEmployees($forecastStartYear, $forecastEndYear, $retirementAge, $asOf, $managedBranchIds);

        $retiredRecords = EmployeeServiceHistory::query()
            ->with('employee')
            ->where('event_type', 'retirement')
            ->when($retiredScope === 'year', function ($query) use ($year) {
                $query->whereYear('event_date', $year);
            })
            ->orderByDesc('event_date')
            ->orderByDesc('id');

        $this->applyHistoryManagedBranchScope($retiredRecords, $managedBranchIds);

        $viewData = [
            'as_of' => $asOf->toDateString(),
            'report_year' => $year,
            'forecast_years' => $forecastYears,
            'forecast_start_year' => $forecastStartYear,
            'forecast_end_year' => $forecastEndYear,
            'retired_scope' => $retiredScope,
            'retirement_age' => $retirementAge,
            'yearly_employees' => $yearlyEmployees,
            'forecast_employees' => $forecastEmployees,
            'retired_records' => $retiredRecords->get(),
            'export_meta' => $this->buildRetirementExportMeta(),
            'pay_level_name_by_code' => $this->payLevelNameByCodeMap(),
            'logo_data_uri' => $this->resolveRetirementLogoDataUri(),
            'logo_file_uri' => $this->resolveRetirementLogoFileUri(),
        ];

        $fileName = sprintf('retirement_report_%d_%d.pdf', $year, $forecastEndYear);

        $chromePdfDownload = $this->renderRetirementPdfByHeadlessBrowser($viewData, $fileName);
        if ($chromePdfDownload) {
            return $chromePdfDownload;
        }

        $pdf = Pdf::loadView('humanresource::employee.retirement.report-pdf', $viewData)
            ->setPaper('a4', 'landscape')
            ->setOption('defaultFont', 'Khmer OS Siemreap')
            ->setOption('isHtml5ParserEnabled', true)
            ->setOption('isRemoteEnabled', true);

        return $pdf->download($fileName);
    }

    public function store(Request $request, OrgUnitRuleService $orgUnitRuleService)
    {
        $validated = $request->validate([
            'employee_id' => 'required|integer|exists:employees,id',
            'effective_date' => 'required|date',
            'document_date' => 'nullable|date',
            'document_reference' => 'nullable|string|max:255',
            'note' => 'nullable|string|max:500',
        ]);

        $employee = Employee::query()->findOrFail((int) $validated['employee_id']);
        $this->assertCanManageEmployee($employee, $orgUnitRuleService);

        try {
            DB::beginTransaction();

            $fromStatus = trim((string) ($employee->work_status_name ?: $employee->service_state ?: localize('active', 'Active')));
            $toStatus = $this->retirementStatusLabel();

            $employee->is_active = false;
            $employee->is_left = true;
            $employee->service_state = 'inactive';
            $employee->work_status_name = $toStatus;
            $employee->save();

            $details = trim(implode(' | ', array_filter([
                $validated['document_reference'] ?? null,
                $validated['note'] ?? null,
            ])));

            $this->historyService()->log(
                (int) $employee->id,
                'retirement',
                localize('retirement_processed_title', 'Employee retirement'),
                $details !== '' ? $details : localize('retirement_processed_detail', 'Processed retirement and set employee inactive.'),
                (string) $validated['effective_date'],
                $fromStatus,
                $toStatus,
                'employee_retirement',
                (int) $employee->id,
                [
                    'document_date' => $validated['document_date'] ?? null,
                    'document_reference' => $validated['document_reference'] ?? null,
                    'note' => $validated['note'] ?? null,
                ]
            );

            DB::commit();

            return redirect()
                ->route('employee-retirements.index')
                ->with('success', localize('retirement_processed_success', 'Retirement has been processed successfully.'));
        } catch (\Throwable $exception) {
            DB::rollBack();
            Log::error('Employee retirement process failed: ' . $exception->getMessage(), [
                'employee_id' => $validated['employee_id'] ?? null,
            ]);

            return redirect()
                ->back()
                ->withInput()
                ->with('error', localize('retirement_processed_failed', 'Unable to process retirement right now.'));
        }
    }

    protected function retirementDueEmployees(Carbon $asOf, int $retirementAge, ?array $managedBranchIds): Collection
    {
        $dueDobThreshold = $asOf->copy()->subYears($retirementAge)->toDateString();

        $query = Employee::query()
            ->with([
                'department',
                'sub_department',
                'position',
                'currentPayGradeHistory.payLevel',
                'latestPayGradeHistory.payLevel',
            ])
            ->where('is_active', true)
            ->whereNotNull('date_of_birth')
            ->whereDate('date_of_birth', '<=', $dueDobThreshold)
            ->orderBy('date_of_birth')
            ->orderBy('last_name')
            ->orderBy('first_name');

        $this->applyManagedBranchScope($query, $managedBranchIds);

        return $this->decorateRetirementRows($query->get(), $asOf, $retirementAge);
    }

    protected function retirementUpcomingEmployees(Carbon $asOf, int $upcomingMonths, int $retirementAge, ?array $managedBranchIds): Collection
    {
        $dueDobThreshold = $asOf->copy()->subYears($retirementAge)->toDateString();
        $upcomingDobThreshold = $asOf->copy()->addMonths($upcomingMonths)->subYears($retirementAge)->toDateString();

        $query = Employee::query()
            ->with([
                'department',
                'sub_department',
                'position',
                'currentPayGradeHistory.payLevel',
                'latestPayGradeHistory.payLevel',
            ])
            ->where('is_active', true)
            ->whereNotNull('date_of_birth')
            ->whereDate('date_of_birth', '>', $dueDobThreshold)
            ->whereDate('date_of_birth', '<=', $upcomingDobThreshold)
            ->orderBy('date_of_birth')
            ->orderBy('last_name')
            ->orderBy('first_name');

        $this->applyManagedBranchScope($query, $managedBranchIds);

        return $this->decorateRetirementRows($query->get(), $asOf, $retirementAge);
    }

    protected function retirementRangeEmployees(
        int $startYear,
        int $endYear,
        int $retirementAge,
        Carbon $asOf,
        ?array $managedBranchIds
    ): Collection {
        $rangeStart = Carbon::create($startYear, 1, 1);
        $rangeEnd = Carbon::create($endYear, 12, 31);

        $dobStart = $rangeStart->copy()->subYears($retirementAge)->toDateString();
        $dobEnd = $rangeEnd->copy()->subYears($retirementAge)->toDateString();

        $query = Employee::query()
            ->with([
                'department',
                'sub_department',
                'position',
                'currentPayGradeHistory.payLevel',
                'latestPayGradeHistory.payLevel',
            ])
            ->where('is_active', true)
            ->whereNotNull('date_of_birth')
            ->whereDate('date_of_birth', '>=', $dobStart)
            ->whereDate('date_of_birth', '<=', $dobEnd)
            ->orderBy('date_of_birth')
            ->orderBy('last_name')
            ->orderBy('first_name');

        $this->applyManagedBranchScope($query, $managedBranchIds);

        return $this->decorateRetirementRows($query->get(), $asOf, $retirementAge);
    }

    protected function decorateRetirementRows(Collection $employees, Carbon $asOf, int $retirementAge): Collection
    {
        return $employees->map(function (Employee $employee) use ($asOf, $retirementAge) {
            $retirementDate = Carbon::parse($employee->date_of_birth)->addYears($retirementAge);
            $employee->setAttribute('retirement_age', $retirementAge);
            $employee->setAttribute('retirement_date', $retirementDate->toDateString());
            $employee->setAttribute('days_to_retirement', $asOf->diffInDays($retirementDate, false));
            $employee->setAttribute('display_unit_path', $this->resolveRetirementDisplayUnitPath($employee));
            $employee->setAttribute(
                'display_unit_name',
                $this->resolveRetirementDisplayUnitName($employee)
            );

            return $employee;
        })->values();
    }

    protected function resolveRetirementDisplayUnitName(Employee $employee): string
    {
        $allowedTypeCodes = $this->retirementAllowedUnitTypeCodes();

        $currentId = (int) ($employee->sub_department_id ?: $employee->department_id ?: 0);
        if ($currentId <= 0) {
            return '-';
        }

        $visited = [];
        $fallbackName = '';
        $guard = 0;

        while ($currentId > 0 && $guard < 50) {
            if (isset($visited[$currentId])) {
                break;
            }
            $visited[$currentId] = true;

            $node = $this->retirementUnitNode($currentId);
            if (!$node) {
                break;
            }

            if ($fallbackName === '') {
                $fallbackName = (string) ($node['name'] ?? '');
            }

            $typeCode = (string) ($node['type_code'] ?? '');
            if ($typeCode !== '' && in_array($typeCode, $allowedTypeCodes, true)) {
                return (string) ($node['name'] ?: '-');
            }

            $currentId = (int) ($node['parent_id'] ?? 0);
            $guard++;
        }

        return $fallbackName !== '' ? $fallbackName : '-';
    }

    protected function resolveRetirementDisplayUnitPath(Employee $employee): string
    {
        $allowedTypeCodes = $this->retirementAllowedUnitTypeCodes();

        $currentId = (int) ($employee->sub_department_id ?: $employee->department_id ?: 0);
        if ($currentId <= 0) {
            return '-';
        }

        $visited = [];
        $chain = [];
        $guard = 0;

        while ($currentId > 0 && $guard < 50) {
            if (isset($visited[$currentId])) {
                break;
            }
            $visited[$currentId] = true;

            $node = $this->retirementUnitNode($currentId);
            if (!$node) {
                break;
            }

            $name = trim((string) ($node['name'] ?? ''));
            $typeCode = (string) ($node['type_code'] ?? '');

            if ($name !== '' && ($typeCode === '' || in_array($typeCode, $allowedTypeCodes, true))) {
                $chain[] = $name;
            }

            $currentId = (int) ($node['parent_id'] ?? 0);
            $guard++;
        }

        if (empty($chain)) {
            return '-';
        }

        $chain = array_values(array_unique(array_reverse($chain)));
        $chain = array_slice($chain, -3);

        return implode(' | ', $chain);
    }

    protected function retirementAllowedUnitTypeCodes(): array
    {
        return [
            'phd',
            'office',
            'operational_district',
            'district_hospital',
            'provincial_hospital',
            'health_center',
            'health_post',
        ];
    }

    protected function retirementUnitNode(int $departmentId): ?array
    {
        static $cache = [];

        if (array_key_exists($departmentId, $cache)) {
            return $cache[$departmentId];
        }

        /** @var Department|null $unit */
        $unit = Department::withoutGlobalScopes()
            ->with(['unitType:id,code'])
            ->select(['id', 'department_name', 'parent_id', 'unit_type_id'])
            ->find($departmentId);

        if (!$unit) {
            $cache[$departmentId] = null;
            return null;
        }

        $cache[$departmentId] = [
            'id' => (int) $unit->id,
            'name' => trim((string) $unit->department_name),
            'parent_id' => (int) ($unit->parent_id ?? 0),
            'type_code' => strtolower(trim((string) ($unit->unitType?->code ?? ''))),
        ];

        return $cache[$departmentId];
    }

    protected function buildRetirementExportMeta(): array
    {
        $defaults = [
            'admin_text' => 'រដ្ឋបាលខេត្តស្ទឹងត្រែង',
            'unit_text' => 'មន្ទីរសុខាភិបាលនៃរដ្ឋបាលខេត្ត',
            'location_text' => 'ស្ទឹងត្រែង',
            'approval_text' => 'ប្រធានមន្ទីរសុខាភិបាល',
            'hr_manager_text' => 'មន្ត្រីគ្រប់គ្រងបុគ្គលិក',
        ];

        $rootUnitId = $this->currentUserRootUnitId();
        if (!$rootUnitId) {
            return $defaults;
        }

        $unit = Department::withoutGlobalScopes()
            ->with('unitType')
            ->find($rootUnitId);

        if (!$unit) {
            return $defaults;
        }

        $unitName = trim((string) $unit->department_name);
        if ($unitName !== '' && str_contains($unitName, 'ស្ទឹងត្រែង')) {
            $defaults['location_text'] = 'ស្ទឹងត្រែង';
        }

        $typeCode = strtolower(trim((string) optional($unit->unitType)->code));
        $defaults['approval_text'] = $this->resolveApprovalTitleByUnitType($typeCode);

        return $defaults;
    }

    protected function resolveApprovalTitleByUnitType(string $typeCode): string
    {
        $map = [
            'provincial_hospital' => 'ប្រធានមន្ទីរពេទ្យខេត្ត',
            'district_hospital' => 'ប្រធានមន្ទីរពេទ្យស្រុក',
            'operational_district' => 'ប្រធានស្រុកប្រតិបត្តិ',
            'health_center' => 'ប្រធានមណ្ឌលសុខភាព',
            'health_center_with_bed' => 'ប្រធានមណ្ឌលសុខភាព',
            'health_center_without_bed' => 'ប្រធានមណ្ឌលសុខភាព',
            'health_post' => 'ប្រធានប៉ុស្តិ៍សុខភាព',
            'phd' => 'ប្រធានមន្ទីរសុខាភិបាល',
        ];

        return $map[$typeCode] ?? 'ប្រធានមន្ទីរសុខាភិបាល';
    }

    protected function resolveRetirementTemplatePath(): ?string
    {
        $candidates = [];

        $envPath = trim((string) env('RETIREMENT_REPORT_TEMPLATE', ''));
        if ($envPath !== '') {
            $candidates[] = $envPath;
            $candidates[] = base_path($envPath);
        }

        $candidates[] = storage_path('app/public/templates/retirement_template.xlsx');
        $candidates[] = public_path('templates/retirement_template.xlsx');

        foreach ($candidates as $path) {
            if ($path && is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    protected function resolveRetirementLogoDataUri(): ?string
    {
        $path = $this->resolveRetirementLogoPath();
        if (!$path || !is_file($path)) {
            return null;
        }

        $ext = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
        $mimeByExt = [
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
        ];

        $mime = $mimeByExt[$ext] ?? 'application/octet-stream';
        $data = @file_get_contents($path);
        if ($data === false) {
            return null;
        }

        return 'data:' . $mime . ';base64,' . base64_encode($data);
    }

    protected function resolveRetirementLogoFileUri(): ?string
    {
        $path = $this->resolveRetirementLogoPath();
        if (!$path || !is_file($path)) {
            return null;
        }

        return 'file:///' . ltrim(str_replace('\\', '/', $path), '/');
    }

    protected function renderRetirementPdfByHeadlessBrowser(array $viewData, string $fileName): ?BinaryFileResponse
    {
        $browserPath = $this->resolvePdfBrowserPath();
        if (!$browserPath) {
            return null;
        }

        $tmpDir = storage_path('app/tmp/retirement-pdf');
        if (!is_dir($tmpDir)) {
            @mkdir($tmpDir, 0777, true);
        }

        $token = bin2hex(random_bytes(8));
        $htmlPath = $tmpDir . DIRECTORY_SEPARATOR . 'retirement_' . $token . '.html';
        $pdfPath = $tmpDir . DIRECTORY_SEPARATOR . 'retirement_' . $token . '.pdf';

        try {
            $html = view('humanresource::employee.retirement.report-pdf', $viewData)->render();
            if (@file_put_contents($htmlPath, $html) === false) {
                return null;
            }

            $process = new Process([
                $browserPath,
                '--headless',
                '--disable-gpu',
                '--no-sandbox',
                '--disable-dev-shm-usage',
                '--allow-file-access-from-files',
                '--print-to-pdf=' . $pdfPath,
                '--print-to-pdf-no-header',
                $htmlPath,
            ]);

            $process->setTimeout(90);
            $process->run();

            if (!$process->isSuccessful() || !is_file($pdfPath) || filesize($pdfPath) === 0) {
                Log::warning('Headless browser PDF export failed. Falling back to DomPDF.', [
                    'browser' => $browserPath,
                    'error' => $process->getErrorOutput(),
                ]);
                @unlink($pdfPath);
                return null;
            }

            return response()
                ->download($pdfPath, $fileName, ['Content-Type' => 'application/pdf'])
                ->deleteFileAfterSend(true);
        } catch (\Throwable $exception) {
            Log::warning('Headless browser PDF export exception. Falling back to DomPDF.', [
                'browser' => $browserPath,
                'error' => $exception->getMessage(),
            ]);

            @unlink($pdfPath);
            return null;
        } finally {
            @unlink($htmlPath);
        }
    }

    protected function resolvePdfBrowserPath(): ?string
    {
        $envPath = trim((string) env('PDF_BROWSER_PATH', ''));
        if ($this->isValidBrowserCommand($envPath)) {
            return $envPath;
        }

        $candidates = [
            'C:/Program Files/Google/Chrome/Application/chrome.exe',
            'C:/Program Files (x86)/Google/Chrome/Application/chrome.exe',
            'C:/Program Files/Microsoft/Edge/Application/msedge.exe',
            'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe',
            '/usr/bin/google-chrome',
            '/usr/bin/chromium-browser',
            '/usr/bin/chromium',
            '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
            '/Applications/Microsoft Edge.app/Contents/MacOS/Microsoft Edge',
            'chrome',
            'google-chrome',
            'chromium-browser',
            'chromium',
            'msedge',
        ];

        foreach ($candidates as $candidate) {
            if ($this->isValidBrowserCommand($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    protected function isValidBrowserCommand(string $command): bool
    {
        if ($command === '') {
            return false;
        }

        $normalized = str_replace('\\', '/', $command);
        $looksLikePath = str_contains($normalized, '/') || (bool) preg_match('/^[A-Za-z]:\//', $normalized);

        if ($looksLikePath) {
            return is_file($command);
        }

        return true;
    }

    protected function resolveRetirementLogoPath(): ?string
    {
        $folderCandidates = [];
        $logoDirectories = [
            public_path('assets/logo'),
            public_path('assets/logo/logo'),
        ];

        foreach ($logoDirectories as $dir) {
            if (!is_dir($dir)) {
                continue;
            }

            $images = glob($dir . DIRECTORY_SEPARATOR . '*.{png,jpg,jpeg,webp,svg}', GLOB_BRACE) ?: [];
            foreach ($images as $imagePath) {
                $folderCandidates[] = $imagePath;
            }
        }

        $dbCandidates = [];
        $application = Application::query()->select(['logo', 'sidebar_logo', 'sidebar_collapsed_logo'])->first();

        if ($application) {
            foreach (['logo', 'sidebar_logo', 'sidebar_collapsed_logo'] as $column) {
                $value = trim((string) ($application->{$column} ?? ''));
                if ($value === '') {
                    continue;
                }

                $clean = ltrim(str_replace('\\', '/', $value), '/');
                $dbCandidates[] = storage_path('app/public/' . $clean);
                $dbCandidates[] = public_path('storage/' . $clean);
                $dbCandidates[] = public_path($clean);
            }
        }

        $candidates = array_filter(array_merge($folderCandidates, $dbCandidates, [
            public_path('backend/assets/dist/img/sidebar-logo.png'),
            public_path('backend/assets/dist/img/new-logo.png'),
            public_path('backend/assets/dist/img/logo-preview.png'),
            public_path('assets/logo.png'),
            public_path('assets/logo2.png'),
            public_path('assets/hrm-nrw-logo.png'),
            public_path('assets/img/logo.png'),
            public_path('logo.png'),
        ]));

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    protected function payLevelNameByCodeMap(): array
    {
        $rows = GovPayLevel::query()
            ->select(['level_code', 'level_name_km'])
            ->where('is_active', true)
            ->get();

        $map = [];

        foreach ($rows as $row) {
            $code = trim((string) $row->level_code);
            $name = trim((string) $row->level_name_km);
            if ($code === '' || $name === '') {
                continue;
            }

            $upper = strtoupper($code);
            $plain = preg_replace('/\s+/u', '', $upper) ?? $upper;
            $compact = preg_replace('/[^A-Z0-9]/', '', $plain) ?? '';

            $map[$upper] = $name;
            $map[$plain] = $name;
            if ($compact !== '') {
                $map[$compact] = $name;
            }
        }

        return $map;
    }

    protected function retirementStatusLabel(): string
    {
        return localize('retirement_work_status', 'ចូលនិវត្តន៍');
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

    protected function applyManagedBranchScope(Builder $query, ?array $managedBranchIds): void
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

    protected function applyHistoryManagedBranchScope(Builder $query, ?array $managedBranchIds): void
    {
        if (!is_array($managedBranchIds)) {
            return;
        }

        if (empty($managedBranchIds)) {
            $query->whereRaw('1=0');
            return;
        }

        $query->whereHas('employee', function ($employeeQuery) use ($managedBranchIds) {
            $employeeQuery->where(function ($subQuery) use ($managedBranchIds) {
                $subQuery->whereIn('department_id', $managedBranchIds)
                    ->orWhereIn('sub_department_id', $managedBranchIds);
            });
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
            abort(403, 'អ្នកអាចគ្រប់គ្រងបានតែមន្រ្តីក្នុងអង្គភាពរបស់ខ្លួនប៉ុណ្ណោះ។');
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
