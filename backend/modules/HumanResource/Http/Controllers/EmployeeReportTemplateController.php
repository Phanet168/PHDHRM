<?php

namespace Modules\HumanResource\Http\Controllers;

use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Modules\HumanResource\Entities\Gender;
use Modules\HumanResource\Entities\OrgUnitType;
use Modules\HumanResource\Support\EmployeeReportDataset;
use Modules\HumanResource\Support\EmployeeStructureSummary;
use Modules\HumanResource\Support\EmployeeReportLabels;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Modules\HumanResource\Exports\EmployeeTemplateReportExport;
use Modules\HumanResource\Entities\Department;
use Modules\HumanResource\Entities\Employee;
use Modules\HumanResource\Entities\EmployeeReportTemplate;
use Modules\HumanResource\Entities\Position;
use Modules\HumanResource\Support\OrgUnitRuleService;
use Modules\Setting\Entities\Application;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\Process\Process;

class EmployeeReportTemplateController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:read_employee_report')->only(['index', 'generate', 'exportCsv', 'exportExcel', 'exportPdf']);
        $this->middleware('permission:create_employee_report')->only(['store']);
        $this->middleware('permission:update_employee_report')->only(['update']);
        $this->middleware('permission:delete_employee_report')->only(['destroy']);
    }

    public function index(Request $request, OrgUnitRuleService $orgUnitRuleService)
    {
        $templates = EmployeeReportTemplate::query()
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get();

        $departmentTreeOptions = $orgUnitRuleService->hierarchyOptions();

        $editingTemplate = null;
        if ($request->filled('edit')) {
            $editingTemplate = $templates->firstWhere('uuid', (string) $request->input('edit'));
        }

        $selectedTemplate = null;
        if ($request->filled('template')) {
            $selectedTemplate = $templates->firstWhere('uuid', (string) $request->input('template'));
        }

        // Configure the report without loading employee records into the interface.
        $selectedColumns = $this->resolveColumns($selectedTemplate?->columns);
        $groupByOptions = $this->groupByOptionsForTemplate(null);

        return view('humanresource::reports.employee-report-template-manager', [
            'templates' => $templates,
            'editingTemplate' => $editingTemplate,
            'selectedTemplate' => $selectedTemplate,
            'selectedColumns' => $selectedColumns,
            'reportTypeOptions' => $this->reportTypeOptions(),
            'columnOptions' => $this->columnOptions(),
            'columnGroups' => $this->columnGroups(),
            'reportPresets' => $this->reportPresets(),
            'genders' => Gender::query()->where('is_active', true)->get(),
            'unitTypes' => OrgUnitType::query()->active()->orderBy('sort_order')->get(),
            'combinationOptions' => collect(['skill_name', 'employee_grade', 'work_status_name'])->mapWithKeys(fn ($column) => [
                $column => Employee::query()->whereNotNull($column)->where($column, '!=', '')->distinct()->orderBy($column)->pluck($column),
            ]),
            'groupByOptions' => $groupByOptions,
            'departmentTreeOptions' => $departmentTreeOptions,
            'positions' => Position::query()->where('is_active', true)->orderBy('position_name')->get(['id', 'position_name', 'position_name_km']),
        ]);
    }

    public function generate(Request $request, OrgUnitRuleService $orgUnitRuleService)
    {
        $validated = $request->validate([
            'mode' => ['required', 'in:detail,summary,workplace'],
            'format' => ['required', 'in:excel,pdf,csv,view'],
            'columns' => ['required_if:mode,detail', 'array', 'min:1'],
        ]);

        return match ($validated['format']) {
            'excel' => $this->exportExcel($request, $orgUnitRuleService),
            'pdf' => $this->exportPdf($request, $orgUnitRuleService),
            'csv' => $this->exportCsv($request, $orgUnitRuleService),
            'view' => $this->preview($request, $orgUnitRuleService),
        };
    }

    public function preview(Request $request, OrgUnitRuleService $orgUnitRuleService)
    {
        $payload = $this->prepareExportDataset($request, $orgUnitRuleService);
        return view('humanresource::reports.employee-report-template-pdf', [
            'selected_columns' => $payload['selected_columns'],
            'column_options' => $payload['column_options'],
            'rows' => $payload['rows'],
            'grouped_summary' => $payload['grouped_summary'],
            'group_label' => $payload['group_label'],
            'meta' => $this->buildEmployeeExportMeta($payload['template'], $payload['title']),
            'preview' => true,
        ]);
    }

    protected function generationRules(): array
    {
        return [
            'mode' => ['nullable', 'in:detail,summary,workplace'],
            'layout' => ['nullable', 'in:plain,structured'],
            'split_gender' => ['nullable', 'boolean'],
            'template' => ['nullable', 'uuid'],
            'title' => ['nullable', 'string', 'max:150'],
            'columns' => ['sometimes', 'required', 'array', 'min:1'],
            'columns.*' => ['required', 'string', 'distinct', 'in:' . implode(',', array_keys($this->columnOptions()))],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'position_id' => ['nullable', 'integer', 'exists:positions,id'],
            'status' => ['nullable', 'in:active,inactive'],
            'group_by' => ['nullable', 'string', 'in:' . implode(',', array_keys($this->groupByOptionsForTemplate(null)))],
            'keyword' => ['nullable', 'string', 'max:150'],
            'employee_name' => ['nullable', 'string', 'max:150'],
            'employee_code' => ['nullable', 'string', 'max:50'],
            'gender_id' => ['nullable', 'integer', 'exists:genders,id'],
            'birth_date' => ['nullable', 'date_format:Y-m-d'],
            'birth_year' => ['nullable', 'integer', 'between:1900,2100'],
            'age' => ['nullable', 'integer', 'between:0,120'],
            'service_years' => ['nullable', 'integer', 'between:0,80'],
            'joining_year' => ['nullable', 'integer', 'between:1900,2100'],
            'unit_type_id' => ['nullable', 'integer', 'exists:org_unit_types,id'],
            'skill_name' => ['nullable', 'array'],
            'skill_name.*' => ['string', 'max:255'],
            'employee_grade' => ['nullable', 'array'],
            'employee_grade.*' => ['string', 'max:255'],
            'work_status_name' => ['nullable', 'array'],
            'work_status_name.*' => ['string', 'max:255'],
        ];
    }

    protected function reportPresets(): array
    {
        $presets = [
            'workforce_summary' => ['full_name', 'gender', 'department', 'sub_department', 'position', 'employee_type', 'work_status'],
            'contact_directory' => ['full_name', 'department', 'position', 'phone', 'email'],
            'education_profile' => ['full_name', 'department', 'highest_educational_qualification', 'degree_name', 'university_name', 'passing_year'],
            'service_profile' => ['full_name', 'department', 'position', 'employee_grade', 'service_start_date', 'joining_date', 'work_status'],
            'custom' => ['employee_id', 'full_name', 'department', 'position', 'phone', 'work_status'],
        ];

        return array_map(fn (array $columns) => $this->resolveColumns($columns), $presets);
    }

    public function store(Request $request)
    {
        $validated = $this->validateTemplate($request);

        EmployeeReportTemplate::query()->create($validated);

        return redirect()
            ->route('reports.employee-report-templates.index')
            ->with('success', 'បានរក្សាទុកគំរូរបាយការណ៍ថ្មី។');
    }

    public function update(Request $request, string $uuid)
    {
        $template = EmployeeReportTemplate::query()->where('uuid', $uuid)->firstOrFail();
        $validated = $this->validateTemplate($request);

        $template->fill($validated);
        $template->save();

        return redirect()
            ->route('reports.employee-report-templates.index')
            ->with('success', 'បានកែសម្រួលគំរូរបាយការណ៍។');
    }

    public function destroy(string $uuid)
    {
        $template = EmployeeReportTemplate::query()->where('uuid', $uuid)->firstOrFail();
        $template->delete();

        return redirect()
            ->route('reports.employee-report-templates.index')
            ->with('success', 'បានលុបគំរូរបាយការណ៍។');
    }

    public function exportCsv(Request $request, OrgUnitRuleService $orgUnitRuleService)
    {
        $payload = $this->prepareExportDataset($request, $orgUnitRuleService);
        $selectedColumns = $payload['selected_columns'];
        $rows = $payload['rows'];
        $headerMap = $payload['column_options'];

        $filename = 'employee_report_' . date('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($rows, $selectedColumns, $headerMap) {
            $output = fopen('php://output', 'w');

            // Excel on Windows needs UTF-8 BOM to display Khmer correctly.
            fwrite($output, "\xEF\xBB\xBF");

            fputcsv($output, array_map(function ($col) use ($headerMap) {
                return $headerMap[$col] ?? $col;
            }, $selectedColumns));

            foreach ($rows as $row) {
                if (isset($row['__group'])) {
                    fputcsv($output, array_pad([(string) $row['__group']], count($selectedColumns), ''));
                    continue;
                }
                fputcsv($output, array_map(fn ($column) => (string) ($row[$column] ?? ''), $selectedColumns));
            }

            fclose($output);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function exportExcel(Request $request, OrgUnitRuleService $orgUnitRuleService)
    {
        $payload = $this->prepareExportDataset($request, $orgUnitRuleService);

        $fileName = 'employee_report_template_' . date('Ymd_His') . '.xlsx';

        return Excel::download(
            new EmployeeTemplateReportExport(
                $payload['selected_columns'],
                $payload['column_options'],
                $payload['rows'],
                $payload['grouped_summary'],
                $payload['group_label'],
                $this->buildEmployeeExportMeta($payload['template'], $payload['title'])
            ),
            $fileName
        );
    }

    public function exportPdf(Request $request, OrgUnitRuleService $orgUnitRuleService)
    {
        $payload = $this->prepareExportDataset($request, $orgUnitRuleService);
        $fileName = 'employee_report_template_' . date('Ymd_His') . '.pdf';
        $viewData = [
            'selected_columns' => $payload['selected_columns'],
            'column_options' => $payload['column_options'],
            'rows' => $payload['rows'],
            'grouped_summary' => $payload['grouped_summary'],
            'group_label' => $payload['group_label'],
            'meta' => $this->buildEmployeeExportMeta($payload['template'], $payload['title']),
        ];

        $chromePdfDownload = $this->renderEmployeeTemplatePdfByHeadlessBrowser($viewData, $fileName);
        if ($chromePdfDownload) {
            return $chromePdfDownload;
        }

        $pdf = Pdf::loadView('humanresource::reports.employee-report-template-pdf', $viewData)
            ->setPaper('a4', 'landscape')
            ->setOption('defaultFont', 'Khmer OS Siemreap')
            ->setOption('isHtml5ParserEnabled', true)
            ->setOption('isRemoteEnabled', true);

        return $pdf->download($fileName);
    }

    protected function renderEmployeeTemplatePdfByHeadlessBrowser(array $viewData, string $fileName): ?BinaryFileResponse
    {
        $browserPath = $this->resolvePdfBrowserPath();
        if (!$browserPath) {
            return null;
        }

        $tmpDir = storage_path('app/tmp/employee-report-pdf');
        if (!is_dir($tmpDir)) {
            @mkdir($tmpDir, 0777, true);
        }

        $token = bin2hex(random_bytes(8));
        $htmlPath = $tmpDir . DIRECTORY_SEPARATOR . 'employee_report_' . $token . '.html';
        $pdfPath = $tmpDir . DIRECTORY_SEPARATOR . 'employee_report_' . $token . '.pdf';

        try {
            $html = view('humanresource::reports.employee-report-template-pdf', $viewData)->render();
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

    protected function prepareExportDataset(Request $request, OrgUnitRuleService $orgUnitRuleService): array
    {
        $validated = $request->validate($this->generationRules());
        $template = !empty($validated['template'])
            ? EmployeeReportTemplate::query()->where('uuid', $validated['template'])->firstOrFail()
            : null;

        $columnOptions = $this->columnOptions();
        $selectedColumns = $this->resolveColumns($validated['columns'] ?? $template?->columns);
        $employees = $this->buildReportQuery($request, $this->resolveDepartmentFilterIds($request, $orgUnitRuleService))->get();

        $mode = $validated['mode'] ?? 'detail';
        if ($mode === 'workplace') {
            $dataset = $this->workplaceDataset($request, $orgUnitRuleService, $employees);
        } elseif ($mode === 'summary' && ($validated['layout'] ?? 'plain') === 'structured') {
            $branchIds = $this->resolveDepartmentFilterIds($request, $orgUnitRuleService);
            $units = Department::query()
                ->when($request->filled('department_id'), fn ($query) => $query->whereIn('id', $branchIds))
                ->reorder()->orderBy('sort_order')->orderBy('department_name')->orderBy('id')
                ->get(['id', 'parent_id', 'department_name', 'sort_order']);
            $dataset = app(EmployeeStructureSummary::class)->build(
                $employees,
                $units,
                fn ($employee, $column) => $this->resolveCellValue($employee, $column),
                ($validated['group_by'] ?? '') ?: 'skill_name',
                $request->boolean('split_gender')
            );
        } else {
            $dataset = app(EmployeeReportDataset::class)->build(
                $employees,
                $selectedColumns,
                fn ($employee, $column) => $this->resolveCellValue($employee, $column),
                $mode,
                ($validated['group_by'] ?? '') ?: 'department',
                $request->boolean('split_gender'),
                $validated['layout'] ?? 'plain'
            );
        }
        $selectedColumns = $dataset['columns'];
        $columnOptions = array_merge($columnOptions, $dataset['labels']);
        $rows = $dataset['rows'];

        $groupByOptions = $this->groupByOptionsForTemplate(null);
        $selectedGroupBy = (string) $request->input('group_by', '');
        if (!array_key_exists($selectedGroupBy, $groupByOptions)) {
            $selectedGroupBy = null;
        }

        $groupedSummary = collect();
        if ($selectedGroupBy && $mode === 'detail') {
            $groupedSummary = $employees
                ->groupBy(function (Employee $employee) use ($selectedGroupBy) {
                    return $this->resolveGroupValue($employee, $selectedGroupBy);
                })
                ->map(function ($items, $groupLabel) {
                    return [
                        'group_label' => (string) $groupLabel,
                        'total' => $items->count(),
                    ];
                })
                ->values()
                ->sortByDesc('total')
                ->values();
        }

        return [
            'template' => $template,
            'title' => $validated['title'] ?? null,
            'selected_columns' => $selectedColumns,
            'column_options' => $columnOptions,
            'employees' => $employees,
            'rows' => $rows,
            'grouped_summary' => $groupedSummary,
            'group_label' => $selectedGroupBy && $mode === 'detail' ? ($groupByOptions[$selectedGroupBy] ?? $selectedGroupBy) : null,
        ];
    }

    protected function workplaceDataset(Request $request, OrgUnitRuleService $orgUnitRuleService, $employees): array
    {
        $ids = $this->resolveDepartmentFilterIds($request, $orgUnitRuleService);
        $units = Department::query()->with(['unitType', 'parentDept'])
            ->when($request->filled('department_id'), fn ($q) => $q->whereIn('id', $ids))
            ->when($request->filled('unit_type_id'), fn ($q) => $q->where('unit_type_id', $request->input('unit_type_id')))
            ->get();
        $counts = $employees->countBy(fn ($employee) => $employee->sub_department_id ?: $employee->department_id ?: 0);
        $labels = ['unit_code' => 'លេខកូដអង្គភាព', 'unit_name' => 'ឈ្មោះអង្គភាព', 'parent_unit' => 'អង្គភាពមេ', 'unit_type' => 'ប្រភេទអង្គភាព', 'total' => 'ចំនួនបុគ្គលិក'];
        return [
            'columns' => array_keys($labels),
            'labels' => $labels,
            'rows' => $units->map(fn ($unit) => [
                'unit_code' => $unit->location_code,
                'unit_name' => $unit->department_name,
                'parent_unit' => $unit->parentDept?->department_name ?? '',
                'unit_type' => $unit->unitType?->display_name ?? '',
                'total' => $counts->get($unit->id, 0),
            ]),
        ];
    }

    protected function buildEmployeeExportMeta(?EmployeeReportTemplate $template = null, ?string $title = null): array
    {
        $meta = [
            'admin_text' => 'រដ្ឋបាលខេត្តស្ទឹងត្រែង',
            'unit_text' => 'មន្ទីរសុខាភិបាលនៃរដ្ឋបាលខេត្ត',
            'title_text' => 'តារាងរបាយការណ៍បុគ្គលិក',
            'location_text' => 'ស្ទឹងត្រែង',
            'approval_text' => 'ប្រធានមន្ទីរសុខាភិបាល',
            'hr_manager_text' => 'មន្ត្រីគ្រប់គ្រងបុគ្គលិក',
        ];

        if ($template && !empty($template->name)) {
            $meta['title_text'] = 'តារាងរបាយការណ៍បុគ្គលិក - ' . (string) $template->name;
        }

        if (filled($title)) {
            $meta['title_text'] = $title;
        }

        try {
            $app = Application::query()->first();
            if ($app) {
                $meta['admin_text'] = (string) ($app->company_name ?: $meta['admin_text']);
                $meta['unit_text'] = (string) (($app->title ?: $app->site_title ?: $app->company_name) ?: $meta['unit_text']);
                // Keep official place label for report footer date instead of full address.
                $meta['location_text'] = 'ស្ទឹងត្រែង';
            }
        } catch (\Throwable $exception) {
            // Keep defaults when settings are unavailable.
        }

        return $meta;
    }

    protected function validateTemplate(Request $request): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'report_type' => ['required', 'string', 'in:' . implode(',', array_keys($this->reportTypeOptions()))],
            'description' => ['nullable', 'string', 'max:1000'],
            'columns' => ['required', 'array', 'min:1'],
            'columns.*' => ['required', 'string', 'in:' . implode(',', array_keys($this->columnOptions()))],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $validated['is_active'] = $request->boolean('is_active');

        return $validated;
    }

    protected function buildReportQuery(Request $request, ?array $departmentFilterIds = null)
    {
        $query = Employee::query()
            ->with(['department', 'sub_department', 'position', 'gender', 'employee_type', 'marital_status', 'duty_type', 'pay_frequency'])
            ->when(!empty($departmentFilterIds), function ($query) use ($departmentFilterIds) {
                $query->where(function ($inner) use ($departmentFilterIds) {
                    $inner->whereIn('department_id', $departmentFilterIds)
                        ->orWhereIn('sub_department_id', $departmentFilterIds);
                });
            })
            ->when($request->filled('position_id'), function ($query) use ($request) {
                $query->where('position_id', (int) $request->input('position_id'));
            })
            ->when($request->filled('status') && in_array($request->input('status'), ['active', 'inactive'], true), function ($query) use ($request) {
                $query->where('is_active', $request->input('status') === 'active');
            })
            ->when($request->filled('keyword'), function ($query) use ($request) {
                $keyword = trim((string) $request->input('keyword'));
                $query->where(function ($inner) use ($keyword) {
                    $inner->where('employee_id', 'like', "%{$keyword}%")
                        ->orWhere('first_name', 'like', "%{$keyword}%")
                        ->orWhere('last_name', 'like', "%{$keyword}%")
                        ->orWhere('first_name_latin', 'like', "%{$keyword}%")
                        ->orWhere('last_name_latin', 'like', "%{$keyword}%")
                        ->orWhere('phone', 'like', "%{$keyword}%")
                        ->orWhere('email', 'like', "%{$keyword}%");
                });
            })
            ->orderBy('department_id')->orderBy('sub_department_id')->orderBy('last_name')->orderBy('first_name')->orderBy('id');

        foreach (['skill_name', 'employee_grade', 'work_status_name'] as $column) {
            if ($request->filled($column)) {
                $query->whereIn($column, $request->input($column));
            }
        }
        if ($request->filled('employee_name')) {
            foreach (preg_split('/\s+/u', trim($request->input('employee_name')), -1, PREG_SPLIT_NO_EMPTY) as $part) {
                $query->where(fn ($q) => $q->where('first_name', 'like', '%' . $part . '%')->orWhere('last_name', 'like', '%' . $part . '%'));
            }
        }
        if ($request->filled('employee_code')) {
            $code = $request->input('employee_code');
            $query->where(fn ($q) => $q->where('employee_id', $code)->orWhere('official_id_10', $code)->orWhere('employee_code', $code));
        }
        if ($request->filled('gender_id')) $query->where('gender_id', $request->input('gender_id'));
        if ($request->filled('birth_date')) $query->whereDate('date_of_birth', $request->input('birth_date'));
        if ($request->filled('birth_year')) $query->whereYear('date_of_birth', $request->input('birth_year'));
        if ($request->filled('joining_year')) $query->whereYear('joining_date', $request->input('joining_year'));
        if ($request->filled('age')) {
            $years = (int) $request->input('age');
            $query->whereDate('date_of_birth', '>', Carbon::today()->subYearsNoOverflow($years + 1))
                ->whereDate('date_of_birth', '<=', Carbon::today()->subYearsNoOverflow($years));
        }
        if ($request->filled('service_years')) {
            $years = (int) $request->input('service_years');
            $query->whereRaw('DATE(COALESCE(service_start_date, joining_date)) > ?', [Carbon::today()->subYearsNoOverflow($years + 1)->toDateString()])
                ->whereRaw('DATE(COALESCE(service_start_date, joining_date)) <= ?', [Carbon::today()->subYearsNoOverflow($years)->toDateString()]);
        }
        if ($request->filled('unit_type_id')) {
            $type = (int) $request->input('unit_type_id');
            $query->where(function ($q) use ($type) {
                $q->whereHas('sub_department', fn ($unit) => $unit->where('unit_type_id', $type))
                    ->orWhere(fn ($main) => $main->whereNull('sub_department_id')->whereHas('department', fn ($unit) => $unit->where('unit_type_id', $type)));
            });
        }
        return $query;
    }

    protected function resolveDepartmentFilterIds(Request $request, OrgUnitRuleService $orgUnitRuleService): array
    {
        $departmentId = (int) $request->input('department_id', 0);
        if ($departmentId <= 0) {
            return [];
        }

        return $orgUnitRuleService->branchIdsIncludingSelf($departmentId);
    }

    protected function resolveColumns(?array $columns): array
    {
        $allowed = array_keys($this->columnOptions());
        $columns = is_array($columns) ? array_values(array_intersect($columns, $allowed)) : [];

        if (empty($columns)) {
            return ['employee_id', 'full_name', 'gender', 'department', 'position', 'phone', 'work_status'];
        }

        return $columns;
    }

    protected function resolveCellValue(Employee $employee, string $column): string
    {
        return match ($column) {
            'full_name' => (string) ($employee->full_name ?? ''),
            'full_name_latin' => (string) ($employee->full_name_latin ?? ''),
            'gender' => $this->khmerizeGender((string) ($employee->gender?->gender_name_km ?: $employee->gender?->gender_name_kh ?: $employee->gender?->gender_name ?: '')),
            'marital_status' => (string) ($employee->marital_status?->marital_status ?? ''),
            'department' => (string) ($employee->department?->department_name ?? ''),
            'sub_department' => (string) ($employee->sub_department?->department_name ?? ''),
            'position' => (string) ($employee->position?->position_name_km ?: $employee->position?->position_name),
            'employee_type' => (string) ($employee->employee_type?->name ?? ''),
            'duty_type' => (string) ($employee->duty_type?->type_name ?? ''),
            'pay_frequency' => (string) ($employee->pay_frequency?->name ?? ($employee->pay_frequency_text ?? '')),
            'employee_grade' => $this->khmerizePayLevel((string) data_get($employee, 'employee_grade', '')),
            'is_disable' => (string) ((int) ($employee->is_disable ?? 0) === 1 ? 'បាទ/ចាស' : 'ទេ'),
            'work_status' => (string) ($employee->work_status_name ?: ($employee->is_active ? 'សកម្ម' : 'អសកម្ម')),
            'is_active' => (string) ((int) ($employee->is_active ?? 0) === 1 ? 'សកម្ម' : 'អសកម្ម'),
            'is_left' => (string) ((int) ($employee->is_left ?? 0) === 1 ? 'បាទ/ចាស' : 'ទេ'),
            default => (string) data_get($employee, $column, ''),
        };
    }

    protected function khmerizeGender(string $value): string
    {
        $clean = trim($value);
        if ($clean === '') {
            return '';
        }

        $lower = mb_strtolower($clean, 'UTF-8');

        return match ($lower) {
            'male', 'm', 'ប្រុស' => 'ប្រុស',
            'female', 'f', 'ស្រី' => 'ស្រី',
            default => $clean,
        };
    }

    protected function khmerizePayLevel(string $value): string
    {
        $clean = trim($value);
        if ($clean === '') {
            return '';
        }

        $letterMap = [
            'A' => 'ក',
            'B' => 'ខ',
            'C' => 'គ',
            'D' => 'ឃ',
            'E' => 'ង',
            'F' => 'ច',
            'G' => 'ឆ',
            'H' => 'ជ',
        ];

        $digitMap = [
            '0' => '០',
            '1' => '១',
            '2' => '២',
            '3' => '៣',
            '4' => '៤',
            '5' => '៥',
            '6' => '៦',
            '7' => '៧',
            '8' => '៨',
            '9' => '៩',
        ];

        return strtr(strtoupper($clean), $letterMap + $digitMap);
    }

    protected function reportTypeOptions(): array
    {
        return [
            'workforce_summary' => 'ព័ត៌មានទូទៅបុគ្គលិក',
            'contact_directory' => 'ព័ត៌មានទំនាក់ទំនង',
            'education_profile' => 'ព័ត៌មានការសិក្សា',
            'service_profile' => 'ប្រវត្តិបម្រើការងារ',
            'custom' => 'ជ្រើសព័ត៌មានដោយខ្លួនឯង',
        ];
    }

    protected function columnOptions(): array
    {
        $labels = EmployeeReportLabels::columns();
        $computed = ['full_name', 'full_name_latin', 'gender', 'marital_status', 'department', 'sub_department', 'position', 'employee_type', 'duty_type', 'pay_frequency', 'employee_grade', 'work_status'];
        return array_merge($this->employeeDbColumnOptions(), array_intersect_key($labels, array_flip($computed)));
    }

    protected function employeeDbColumnOptions(): array
    {
        $columns = Schema::hasTable('employees') ? Schema::getColumnListing('employees') : [];
        $labels = EmployeeReportLabels::columns();
        $options = [];
        foreach ($columns as $column) {
            $options[$column] = $labels[$column] ?? localize($column, null, 'km');
        }
        return $options;
    }

    protected function columnGroups(): array
    {
        $allColumnKeys = array_keys($this->columnOptions());

        return [
            'identity' => [
                'label' => 'ព័ត៌មានអត្តសញ្ញាណ',
                'columns' => $this->filterColumns($allColumnKeys, [
                    'employee_id',
                    'official_id_10',
                    'card_no',
                    'full_name',
                    'full_name_latin',
                    'last_name',
                    'first_name',
                    'last_name_latin',
                    'first_name_latin',
                    'gender',
                    'gender_id',
                    'marital_status',
                    'marital_status_id',
                    'date_of_birth',
                    'ethnic_group',
                    'religion',
                    'nationality',
                    'national_id',
                    'national_id_no',
                    'passport_no',
                    'employee_device_id',
                ]),
            ],
            'contact' => [
                'label' => 'ព័ត៌មានទំនាក់ទំនង',
                'columns' => $this->filterColumns($allColumnKeys, [
                    'phone',
                    'alternate_phone',
                    'email',
                    'home_phone',
                    'business_phone',
                    'cell_phone',
                    'home_email',
                    'business_email',
                    'emergency_contact_person',
                    'emergency_contact_relationship',
                    'emergency_contact',
                    'emergency_contact_country',
                    'emergency_contact_state',
                    'emergency_contact_city',
                    'emergency_contact_post_code',
                    'emergency_contact_address',
                ]),
            ],
            'organization' => [
                'label' => 'ព័ត៌មានអង្គភាព និងការងារ',
                'columns' => $this->filterColumns($allColumnKeys, [
                    'department',
                    'department_id',
                    'sub_department',
                    'sub_department_id',
                    'position',
                    'position_id',
                    'employee_type',
                    'employee_type_id',
                    'duty_type',
                    'duty_type_id',
                    'pay_frequency',
                    'pay_frequency_id',
                    'pay_frequency_text',
                    'service_state',
                    'joining_date',
                    'hire_date',
                    'service_start_date',
                    'contract_start_date',
                    'contract_end_date',
                    'work_status',
                    'work_status_id',
                    'work_status_name',
                    'is_active',
                    'is_left',
                ]),
            ],
            'address' => [
                'label' => 'ព័ត៌មានអាសយដ្ឋាន',
                'columns' => $this->filterColumns($allColumnKeys, [
                    'state_id',
                    'city',
                    'zip',
                    'birth_place_state_id',
                    'birth_place_city_id',
                    'birth_place_commune_id',
                    'birth_place_village_id',
                    'present_address_country',
                    'present_address_state_id',
                    'present_address_city_id',
                    'present_address_commune_id',
                    'present_address_village_id',
                    'present_address_state',
                    'present_address_city',
                    'present_address_post_code',
                    'present_address_address',
                    'permanent_address_country',
                    'permanent_address_state',
                    'permanent_address_city',
                    'permanent_address_post_code',
                    'permanent_address_address',
                ]),
            ],
            'skills_education' => [
                'label' => 'ព័ត៌មានជំនាញ និងការសិក្សា',
                'columns' => $this->filterColumns($allColumnKeys, [
                    'skill_type',
                    'skill_name',
                    'certificate_type',
                    'certificate_name',
                    'employee_grade',
                    'highest_educational_qualification',
                    'degree_name',
                    'university_name',
                    'cgp',
                    'passing_year',
                ]),
            ],
            'health' => [
                'label' => 'ព័ត៌មានសុខភាព',
                'columns' => $this->filterColumns($allColumnKeys, [
                    'blood_group',
                    'health_condition',
                    'chronic_disease_history',
                    'allergy_reaction',
                    'is_disable',
                    'disabilities_desc',
                ]),
            ],
            'documents' => [
                'label' => 'ព័ត៌មានឯកសារ',
                'columns' => $this->filterColumns($allColumnKeys, [
                    'work_permit',
                    'signature',
                    'profile_img_name',
                    'profile_img_location',
                    'identification_attachment',
                    'skill_attachment',
                ]),
            ],
            'other' => [
                'label' => 'ព័ត៌មានបន្ថែម',
                'columns' => $this->remainingColumns($allColumnKeys),
            ],
        ];
    }

    protected function filterColumns(array $allColumnKeys, array $wanted): array
    {
        return array_values(array_intersect($wanted, $allColumnKeys));
    }

    protected function remainingColumns(array $allColumnKeys): array
    {
        $known = [
                    'employee_id',
                    'official_id_10',
                    'card_no',
                    'full_name',
                    'full_name_latin',
                    'last_name',
                    'first_name',
                    'last_name_latin',
                    'first_name_latin',
                    'gender',
                'gender_id',
                    'marital_status',
                'marital_status_id',
                    'date_of_birth',
                    'ethnic_group',
                    'religion',
                    'nationality',
                'national_id',
                    'national_id_no',
                    'passport_no',
                    'employee_device_id',
                    'phone',
                    'alternate_phone',
                    'email',
                    'home_phone',
                    'business_phone',
                    'cell_phone',
                    'home_email',
                    'business_email',
                    'department',
                'department_id',
                    'sub_department',
                'sub_department_id',
                    'position',
                'position_id',
                    'employee_type',
                'employee_type_id',
                    'duty_type',
                'duty_type_id',
                    'pay_frequency',
                'pay_frequency_id',
                'pay_frequency_text',
                    'service_state',
                    'joining_date',
                    'hire_date',
                    'service_start_date',
                    'contract_start_date',
                    'contract_end_date',
                    'work_status',
                'work_status_id',
                'work_status_name',
                    'is_active',
                    'is_left',
                'state_id',
                'city',
                'zip',
                    'birth_place_state_id',
                    'birth_place_city_id',
                    'birth_place_commune_id',
                    'birth_place_village_id',
                'present_address_country',
                    'present_address_state_id',
                    'present_address_city_id',
                    'present_address_commune_id',
                    'present_address_village_id',
                'present_address_state',
                'present_address_city',
                'present_address_post_code',
                    'present_address_address',
                'permanent_address_country',
                'permanent_address_state',
                'permanent_address_city',
                'permanent_address_post_code',
                    'permanent_address_address',
                'skill_type',
                    'skill_name',
                'certificate_type',
                'certificate_name',
                    'employee_grade',
                    'highest_educational_qualification',
                'degree_name',
                'university_name',
                'cgp',
                'passing_year',
                    'emergency_contact_person',
                    'emergency_contact_relationship',
                    'emergency_contact',
                'emergency_contact_country',
                'emergency_contact_state',
                'emergency_contact_city',
                'emergency_contact_post_code',
                'emergency_contact_address',
                    'blood_group',
                    'health_condition',
                    'chronic_disease_history',
                    'allergy_reaction',
                    'is_disable',
                    'disabilities_desc',
                    'work_permit',
                    'signature',
                    'profile_img_name',
                'profile_img_location',
                'identification_attachment',
                'skill_attachment',
            ];

        return array_values(array_diff($allColumnKeys, array_unique($known)));
    }

    protected function groupByOptionsForTemplate(?EmployeeReportTemplate $selectedTemplate): array
    {
        $columnLabels = $this->columnOptions();
        $allKeys = array_keys($columnLabels);

        $templateColumns = is_array($selectedTemplate?->columns) ? $selectedTemplate->columns : [];
        $candidateKeys = !empty($templateColumns)
            ? array_values(array_intersect($templateColumns, $allKeys))
            : $allKeys;

        $preferredOrder = [
            'department',
            'sub_department',
            'gender',
            'marital_status',
            'employee_type',
            'position',
            'duty_type',
            'pay_frequency',
            'service_state',
            'skill_name',
            'employee_grade',
            'ethnic_group',
            'religion',
            'work_status',
            'is_active',
            'is_left',
        ];

        $orderedKeys = array_values(array_unique(array_merge(
            array_values(array_intersect($preferredOrder, $candidateKeys)),
            $candidateKeys
        )));

        $options = [];
        foreach ($orderedKeys as $key) {
            if ($this->isGroupableColumn($key)) {
                $options[$key] = $columnLabels[$key] ?? Str::title(str_replace('_', ' ', $key));
            }
        }

        if (empty($options)) {
            foreach ($preferredOrder as $key) {
                if (isset($columnLabels[$key])) {
                    $options[$key] = $columnLabels[$key];
                }
            }
        }

        return $options;
    }

    protected function resolveGroupValue(Employee $employee, string $groupBy): string
    {
        $value = trim((string) $this->resolveCellValue($employee, $groupBy));

        return $value !== '' ? $value : localize('not_specified', 'មិនបានបញ្ជាក់');
    }

    protected function isGroupableColumn(string $key): bool
    {
        $alwaysAllowed = [
            'department',
            'sub_department',
            'gender',
            'marital_status',
            'employee_type',
            'position',
            'duty_type',
            'pay_frequency',
            'service_state',
            'skill_name',
            'employee_grade',
            'ethnic_group',
            'religion',
            'work_status',
            'is_active',
            'is_left',
        ];

        if (in_array($key, $alwaysAllowed, true)) {
            return true;
        }

        $blockedPatterns = [
            '/^id$/i',
            '/_id$/i',
            '/date/i',
            '/phone/i',
            '/email/i',
            '/address/i',
            '/attachment/i',
            '/signature/i',
            '/img|image/i',
            '/latitude|longitude/i',
            '/uuid/i',
            '/_code$/i',
            '/card_no|passport|national_id|official_id|employee_id/i',
        ];

        foreach ($blockedPatterns as $pattern) {
            if (preg_match($pattern, $key)) {
                return false;
            }
        }

        return true;
    }
}
