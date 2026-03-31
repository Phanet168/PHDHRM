<?php

namespace Modules\HumanResource\Http\Controllers;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
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
        $this->middleware('permission:read_employee_report');
    }

    public function index(Request $request, OrgUnitRuleService $orgUnitRuleService)
    {
        $templates = EmployeeReportTemplate::query()
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get();

        $departmentTreeOptions = $orgUnitRuleService->hierarchyOptions();
        $departmentFilterIds = $this->resolveDepartmentFilterIds($request, $orgUnitRuleService);

        $editingTemplate = null;
        if ($request->filled('edit')) {
            $editingTemplate = $templates->firstWhere('uuid', (string) $request->query('edit'));
        }

        $selectedTemplate = null;
        if ($request->filled('template')) {
            $selectedTemplate = $templates->firstWhere('uuid', (string) $request->query('template'));
        }

        $groupByOptions = $this->groupByOptionsForTemplate($selectedTemplate);

        $reportRows = null;
        $groupedSummary = null;
        $selectedGroupBy = null;
        $selectedColumns = [];
        if ($request->query('run') === '1') {
            $selectedGroupBy = (string) $request->query('group_by', '');
            if (!array_key_exists($selectedGroupBy, $groupByOptions)) {
                $selectedGroupBy = null;
            }

            $selectedColumns = $this->resolveColumns($selectedTemplate?->columns);
            $employees = $this->buildReportQuery($request, $departmentFilterIds)->get();

            if ($selectedGroupBy) {
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

            $mappedRows = $employees
                ->map(function (Employee $employee) use ($selectedColumns) {
                    $row = [];
                    foreach ($selectedColumns as $column) {
                        $row[$column] = $this->resolveCellValue($employee, $column);
                    }

                    return $row;
                })
                ->values();

            $perPage = 30;
            $page = max(1, (int) $request->query('page', 1));
            $slicedRows = $mappedRows->forPage($page, $perPage)->values();

            $reportRows = new LengthAwarePaginator(
                $slicedRows,
                $mappedRows->count(),
                $perPage,
                $page,
                [
                    'path' => $request->url(),
                    'query' => $request->query(),
                ]
            );
        }

        return view('humanresource::reports.employee-report-template-manager', [
            'templates' => $templates,
            'editingTemplate' => $editingTemplate,
            'selectedTemplate' => $selectedTemplate,
            'reportRows' => $reportRows,
            'groupedSummary' => $groupedSummary,
            'selectedGroupBy' => $selectedGroupBy,
            'selectedColumns' => $selectedColumns,
            'reportTypeOptions' => $this->reportTypeOptions(),
            'columnOptions' => $this->columnOptions(),
            'columnGroups' => $this->columnGroups(),
            'groupByOptions' => $groupByOptions,
            'departmentTreeOptions' => $departmentTreeOptions,
            'positions' => Position::query()->where('is_active', true)->orderBy('position_name')->get(['id', 'position_name', 'position_name_km']),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validateTemplate($request);

        EmployeeReportTemplate::query()->create($validated);

        return redirect()
            ->route('reports.employee-report-templates.index')
            ->with('success', localize('employee_report_template_created', 'Employee report template created successfully.'));
    }

    public function update(Request $request, string $uuid)
    {
        $template = EmployeeReportTemplate::query()->where('uuid', $uuid)->firstOrFail();
        $validated = $this->validateTemplate($request);

        $template->fill($validated);
        $template->save();

        return redirect()
            ->route('reports.employee-report-templates.index')
            ->with('success', localize('employee_report_template_updated', 'Employee report template updated successfully.'));
    }

    public function destroy(string $uuid)
    {
        $template = EmployeeReportTemplate::query()->where('uuid', $uuid)->firstOrFail();
        $template->delete();

        return redirect()
            ->route('reports.employee-report-templates.index')
            ->with('success', localize('employee_report_template_deleted', 'Employee report template deleted successfully.'));
    }

    public function exportCsv(Request $request, OrgUnitRuleService $orgUnitRuleService)
    {
        $payload = $this->prepareExportDataset($request, $orgUnitRuleService);
        $selectedColumns = $payload['selected_columns'];
        $employees = $payload['employees'];
        $headerMap = $payload['column_options'];

        $filename = 'employee_report_' . date('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($employees, $selectedColumns, $headerMap) {
            $output = fopen('php://output', 'w');

            // Excel on Windows needs UTF-8 BOM to display Khmer correctly.
            fwrite($output, "\xEF\xBB\xBF");

            fputcsv($output, array_map(function ($col) use ($headerMap) {
                return $headerMap[$col] ?? $col;
            }, $selectedColumns));

            foreach ($employees as $employee) {
                $row = [];
                foreach ($selectedColumns as $column) {
                    $row[] = $this->resolveCellValue($employee, $column);
                }
                fputcsv($output, $row);
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
                $this->buildEmployeeExportMeta($payload['template'])
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
            'meta' => $this->buildEmployeeExportMeta($payload['template']),
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
        $template = EmployeeReportTemplate::query()
            ->where('uuid', (string) $request->query('template'))
            ->firstOrFail();

        $columnOptions = $this->columnOptions();
        $selectedColumns = $this->resolveColumns($template->columns);
        $employees = $this->buildReportQuery($request, $this->resolveDepartmentFilterIds($request, $orgUnitRuleService))->get();

        $rows = $employees->map(function (Employee $employee) use ($selectedColumns) {
            $row = [];
            foreach ($selectedColumns as $column) {
                $row[$column] = $this->resolveCellValue($employee, $column);
            }

            return $row;
        })->values();

        $groupByOptions = $this->groupByOptionsForTemplate($template);
        $selectedGroupBy = (string) $request->query('group_by', '');
        if (!array_key_exists($selectedGroupBy, $groupByOptions)) {
            $selectedGroupBy = null;
        }

        $groupedSummary = collect();
        if ($selectedGroupBy) {
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
            'selected_columns' => $selectedColumns,
            'column_options' => $columnOptions,
            'employees' => $employees,
            'rows' => $rows,
            'grouped_summary' => $groupedSummary,
            'group_label' => $selectedGroupBy ? ($groupByOptions[$selectedGroupBy] ?? $selectedGroupBy) : null,
        ];
    }

    protected function buildEmployeeExportMeta(?EmployeeReportTemplate $template = null): array
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
        return Employee::query()
            ->with(['department', 'sub_department', 'position', 'gender', 'employee_type', 'marital_status', 'duty_type', 'pay_frequency'])
            ->when(!empty($departmentFilterIds), function ($query) use ($departmentFilterIds) {
                $query->where(function ($inner) use ($departmentFilterIds) {
                    $inner->whereIn('department_id', $departmentFilterIds)
                        ->orWhereIn('sub_department_id', $departmentFilterIds);
                });
            })
            ->when($request->filled('position_id'), function ($query) use ($request) {
                $query->where('position_id', (int) $request->query('position_id'));
            })
            ->when($request->filled('status') && in_array($request->query('status'), ['active', 'inactive'], true), function ($query) use ($request) {
                $query->where('is_active', $request->query('status') === 'active');
            })
            ->when($request->filled('keyword'), function ($query) use ($request) {
                $keyword = trim((string) $request->query('keyword'));
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
            ->orderByDesc('id');
    }

    protected function resolveDepartmentFilterIds(Request $request, OrgUnitRuleService $orgUnitRuleService): array
    {
        $departmentId = (int) $request->query('department_id', 0);
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
            'is_disable' => (string) ((int) ($employee->is_disable ?? 0) === 1 ? 'Yes' : 'No'),
            'work_status' => (string) ($employee->work_status_name ?: ($employee->is_active ? 'Active' : 'Inactive')),
            'is_active' => (string) ((int) ($employee->is_active ?? 0) === 1 ? 'Active' : 'Inactive'),
            'is_left' => (string) ((int) ($employee->is_left ?? 0) === 1 ? 'Yes' : 'No'),
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
            'workforce_summary' => localize('workforce_summary', 'Workforce summary'),
            'contact_directory' => localize('contact_directory', 'Contact directory'),
            'education_profile' => localize('education_profile', 'Education profile'),
            'service_profile' => localize('service_profile', 'Service profile'),
            'custom' => localize('custom_report', 'Custom report'),
        ];
    }

    protected function columnOptions(): array
    {
        $dbColumns = $this->employeeDbColumnOptions();

        $computedColumns = [
            'full_name' => localize('name_of_employee', 'Employee name'),
            'full_name_latin' => localize('full_name_latin', 'Full name (Latin)'),
            'gender' => localize('gender', 'ភេទ'),
            'marital_status' => localize('marital_status', 'Marital status'),
            'department' => localize('department', 'Department'),
            'sub_department' => localize('sub_department', 'Sub department'),
            'position' => localize('designation', 'Position'),
            'employee_type' => localize('employee_type', 'Employee type'),
            'duty_type' => localize('duty_type', 'Duty type'),
            'pay_frequency' => localize('pay_frequency', 'Pay frequency'),
            'employee_grade' => localize('employee_grade', 'ឋានន្តរស័ក្តិ និងថ្នាក់'),
            'work_status' => localize('work_status', 'Work status'),
        ];

        return array_merge($dbColumns, $computedColumns);
    }

    protected function employeeDbColumnOptions(): array
    {
        $columns = Schema::hasTable('employees') ? Schema::getColumnListing('employees') : [];

        $options = [];
        foreach ($columns as $column) {
            $label = Str::title(str_replace('_', ' ', (string) $column));
            $options[$column] = localize($column, $label);
        }

        return $options;
    }

    protected function columnGroups(): array
    {
        $allColumnKeys = array_keys($this->columnOptions());

        return [
            'identity' => [
                'label' => localize('group_identity_info', 'ព័ត៌មានអត្តសញ្ញាណ'),
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
                'label' => localize('group_contact_info', 'ព័ត៌មានទំនាក់ទំនង'),
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
                'label' => localize('group_org_info', 'ព័ត៌មានអង្គភាព និងការងារ'),
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
                'label' => localize('group_address_info', 'ព័ត៌មានអាសយដ្ឋាន'),
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
                'label' => localize('group_skill_education_info', 'ព័ត៌មានជំនាញ និងការសិក្សា'),
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
                'label' => localize('group_emergency_health_info', 'ព័ត៌មានសុខភាព'),
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
                'label' => localize('group_document_info', 'ព័ត៌មានឯកសារ'),
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
                'label' => localize('group_other_info', 'ព័ត៌មានផ្សេងៗពី DB'),
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
