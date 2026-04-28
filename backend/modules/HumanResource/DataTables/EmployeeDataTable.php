<?php

namespace Modules\HumanResource\DataTables;

use Yajra\DataTables\Html\Button;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Services\DataTable;
use Modules\HumanResource\Entities\Department;
use Modules\HumanResource\Entities\Employee;
use Modules\HumanResource\Entities\GovPayLevel;
use Modules\HumanResource\Support\OrgHierarchyAccessService;
use Modules\HumanResource\Support\OrgUnitRuleService;
use Modules\Setting\Entities\DocExpiredSetting;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Illuminate\Support\Facades\Schema;

class EmployeeDataTable extends DataTable
{
    /**
     * Build DataTable class.
     *
     * @param QueryBuilder $query Results from query() method.
     * @return \Yajra\DataTables\EloquentDataTable
     */
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            ->addIndexColumn()

            ->editColumn('employee_id', function ($employee) {
                return $employee->employee_id;
            })
            ->editColumn('official_id_10', function ($employee) {
                return $employee->official_id_10 ?: '-';
            })
            ->editColumn('full_name', function ($employee) {
                return ucwords($employee->full_name);
            })
            ->filterColumn('full_name', function ($query, $keyword) {
                $query->where('first_name', 'like', "%{$keyword}%")
                    ->orWhere('last_name', 'like', "%{$keyword}%")
                    ->orWhere('middle_name', 'like', "%{$keyword}%");
            })
            ->editColumn('position_id', function ($employee) {
                if (!$employee->position) {
                    return '-';
                }
                return $employee->position->position_name_km ?: ucwords($employee->position->position_name ?? '');
            })
            ->addColumn('skill', function ($employee) {
                $skill = trim((string) (
                    $employee->skill_name
                    ?: ($employee->profileExtra->current_work_skill ?? null)
                    ?: (property_exists($employee, 'current_work_skill') ? $employee->current_work_skill : null)
                    ?: ''
                ));
                return $skill !== '' ? $skill : '-';
            })
            ->filterColumn('skill', function ($query, $keyword) {
                $query->where(function ($q) use ($keyword) {
                    $q->where('skill_name', 'like', "%{$keyword}%")
                        ->orWhereHas('profileExtra', function ($q1) use ($keyword) {
                            $q1->where('current_work_skill', 'like', "%{$keyword}%");
                        });

                    if (Schema::hasColumn('employees', 'current_work_skill')) {
                        $q->orWhere('employees.current_work_skill', 'like', "%{$keyword}%");
                    }
                });
            })
            ->addColumn('pay_level', function ($employee) {
                $label = $this->resolvePayLevelKmLabel($employee);
                return $label !== '' ? $label : '-';
            })
            ->editColumn('gender_id', function ($employee) {
                $gender = mb_strtolower(trim((string) ($employee->gender?->gender_name ?? '')));
                if (in_array($gender, ['male', 'm', 'ប្រុស'], true)) {
                    return localize('male');
                }
                if (in_array($gender, ['female', 'f', 'ស្រី'], true)) {
                    return localize('female');
                }
                return $employee->gender?->gender_name ?: '-';
            })
            ->editColumn('work_status_name', function ($employee) {
                return $employee->work_status_name ?: '-';
            })
            ->filterColumn('position_id', function ($query, $keyword) {
                $query->whereHas('position', function ($query) use ($keyword) {
                    $query->where('position_name', 'like', "%{$keyword}%")
                        ->orWhere('position_name_km', 'like', "%{$keyword}%");
                });
            })
            ->filterColumn('gender_id', function ($query, $keyword) {
                $query->whereHas('gender', function ($query) use ($keyword) {
                    $query->where('gender_name', 'like', "%{$keyword}%");
                });
            })
            ->addColumn('unit_name', function ($employee) {
                return $this->resolveEmployeeDisplayUnitPath($employee);
            })
            ->filterColumn('unit_name', function ($query, $keyword) {
                $query->where(function ($q) use ($keyword) {
                    $q->whereHas('department', function ($q1) use ($keyword) {
                        $q1->where('department_name', 'like', "%{$keyword}%");
                    })->orWhereHas('sub_department', function ($q2) use ($keyword) {
                        $q2->where('department_name', 'like', "%{$keyword}%");
                    });
                });
            })
            ->addColumn('status', function ($employee) {
                $serviceState = strtolower((string) ($employee->service_state ?? ''));
                if ($serviceState === 'suspended') {
                    return '<span class="badge badge-warning-soft">' . localize('suspended_temporary') . '</span>';
                }

                if ((int) $employee->is_active === 1) {
                    return '<span class="badge badge-success-soft">' . localize('active') . '</span>';
                }

                return '<span class="badge badge-danger-soft">' . localize('inactive') . '</span>';
            })

            ->addColumn('action', function ($employee) {

                $button = '';
                if (auth()->user()->can('read_employee')) {
                    $button .= '<a href="' . route('employees.show', $employee->id) . '" class="btn btn-primary-soft btn-sm me-1" title="' . e(localize('show')) . '"><i class="fa fa-eye"></i></a>';
                    $button .= '<a href="' . route('employees.profile.print', $employee->id) . '" class="btn btn-info-soft btn-sm me-1" title="' . e(localize('print_employee_profile', 'បោះពុម្ពប្រវត្តិរូប')) . '" target="_blank" rel="noopener"><i class="fa fa-print"></i></a>';
                }

                if (auth()->user()->can('update_employee')) {
                    $button .= '<a href="' . route('employees.edit', $employee->id) . '" class="btn btn-success-soft btn-sm me-1" title="' . e(localize('edit')) . '"><i class="fa fa-edit"></i></a>';
                }

                if (auth()->user()->can('delete_employee')) {
                    $button .= '<a href="javascript:void(0)" class="btn btn-danger-soft btn-sm delete-confirm" data-bs-toggle="tooltip" title="' . e(localize('delete')) . '" data-route="' . route('employees.destroy', $employee->id) . '" data-csrf="' . csrf_token() . '"><i class="fas fa-trash-alt"></i></a>';
                }

                return $button;
            })
            ->setRowClass(function ($employee) {

                $hasRed = false;
                $hasSecondary = false;
                $hasYellow = false;

                $doc_expiry_day_setup = DocExpiredSetting::first();
                $secondaryAlertDays = (int) ($doc_expiry_day_setup->secondary_expiration_alert ?? 0);
                $primaryAlertDays = (int) ($doc_expiry_day_setup->primary_expiration_alert ?? 0);

                if (isset($employee->employee_docs) && count($employee->employee_docs) > 0) {
                    foreach ($employee->employee_docs as $docs) {
                        $expiryDate = trim((string) ($docs->expiry_date ?? ''));
                        if ($expiryDate === '') {
                            continue;
                        }

                        if (check_expiry($expiryDate)) {
                            $hasRed = true;
                        } elseif ($secondaryAlertDays > 0 && check_expiry($expiryDate, $secondaryAlertDays)) {
                            $hasSecondary = true;
                        } elseif ($primaryAlertDays > 0 && check_expiry($expiryDate, $primaryAlertDays)) {
                            $hasYellow = true;
                        }
                    }
                }

                if ($hasRed == true) {
                    return 'alert-danger';
                } elseif ($hasSecondary && ($hasRed == false || $hasYellow == false)) {
                    return 'alert-warning';
                } elseif ($hasYellow && ($hasRed == false || $hasSecondary == false)) {
                    return 'alert-info';
                }
            })

            ->rawColumns(['status', 'action']);
    }

    /**
     * Get query source of dataTable.
     */
    public function query(Employee $model): QueryBuilder
    {
        $name = $this->request->get('employee_name');
        $employee_id = $this->request->get('employee_id');
        $employee_type = $this->request->get('employee_type');
        $department = $this->request->get('department');
        $designation = $this->request->get('designation');
        $blood_group = $this->request->get('blood_group');
        $gender = $this->request->get('gender');
        $marital_status = $this->request->get('marital_status');
        $employeeStatus = $this->request->get('employee_status');
        $officialId10 = trim((string) $this->request->get('official_id_10', ''));
        $serviceState = trim((string) $this->request->get('service_state', ''));
        $workStatusName = trim((string) $this->request->get('work_status_name', ''));
        $orgUnitRuleService = app(OrgUnitRuleService::class);
        $managedBranchIds = $this->managedBranchIds($orgUnitRuleService);

        $query = $model->newQuery()
            ->where('employees.is_active', 1)
            ->with([
                'department',
                'sub_department',
                'profileExtra',
                'currentPayGradeHistory.payLevel',
                'latestPayGradeHistory.payLevel',
            ]);

        if (is_array($managedBranchIds)) {
            if (empty($managedBranchIds)) {
                $query->whereRaw('1 = 0');
            } else {
                $this->applyBranchScope($query, $managedBranchIds);
            }
        }

        return $query
            ->when($name, function ($query) use ($name) {
                return $query->where('id', $name);
            })
            ->when($employee_id, function ($query) use ($employee_id) {
                return $query->where('employee_id', $employee_id);
            })
            ->when($employee_type, function ($query) use ($employee_type) {
                return $query->where('employee_type_id', $employee_type);
            })
            ->when($department, function ($query) use ($department) {
                $branchIds = app(OrgUnitRuleService::class)->branchIdsIncludingSelf((int) $department);
                return $this->applyBranchScope($query, $branchIds);
            })
            ->when($designation, function ($query) use ($designation) {
                return $query->where('position_id', $designation);
            })
            ->when($officialId10 !== '', function ($query) use ($officialId10) {
                return $query->where('official_id_10', 'like', '%' . $officialId10 . '%');
            })
            ->when($serviceState !== '', function ($query) use ($serviceState) {
                return $query->where('service_state', $serviceState);
            })
            ->when($workStatusName !== '', function ($query) use ($workStatusName) {
                return $query->where('work_status_name', $workStatusName);
            })
            ->when($employeeStatus !== null && $employeeStatus !== '', function ($query) use ($employeeStatus) {
                // Active employee list is restricted to active staff only.
                return $query->where('employees.is_active', 1);
            })
            ->when($blood_group, function ($query) use ($blood_group) {
                return $query->where('blood_group', $blood_group);
            })
            ->when($gender, function ($query) use ($gender) {
                return $query->where('gender_id', $gender);
            })
            ->when($marital_status, function ($query) use ($marital_status) {
                return $query->where('marital_status_id', $marital_status);
            })
            ->leftJoin('positions as p_order', 'employees.position_id', '=', 'p_order.id')
            ->leftJoin('departments as d_main', 'employees.department_id', '=', 'd_main.id')
            ->leftJoin('departments as d_sub', 'employees.sub_department_id', '=', 'd_sub.id')
            ->select('employees.*')
            // LEGACY-WP-1-2-19-2-1 => [1 ministry, 2 directorate, 19 province, 2 office, 1 section]
            // Sort by province segment first (3rd), then deeper hierarchy segments.
            ->orderByRaw("CASE WHEN COALESCE(NULLIF(d_sub.location_code, ''), NULLIF(d_main.location_code, '')) LIKE 'LEGACY-WP-%' THEN 0 ELSE 1 END ASC")
            ->orderByRaw("CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(REPLACE(COALESCE(NULLIF(d_sub.location_code, ''), NULLIF(d_main.location_code, '')), 'LEGACY-WP-', ''), '-', 3), '-', -1) AS UNSIGNED) ASC")
            ->orderByRaw("CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(REPLACE(COALESCE(NULLIF(d_sub.location_code, ''), NULLIF(d_main.location_code, '')), 'LEGACY-WP-', ''), '-', 4), '-', -1) AS UNSIGNED) ASC")
            ->orderByRaw("CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(REPLACE(COALESCE(NULLIF(d_sub.location_code, ''), NULLIF(d_main.location_code, '')), 'LEGACY-WP-', ''), '-', 5), '-', -1) AS UNSIGNED) ASC")
            ->orderByRaw("CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(REPLACE(COALESCE(NULLIF(d_sub.location_code, ''), NULLIF(d_main.location_code, '')), 'LEGACY-WP-', ''), '-', 6), '-', -1) AS UNSIGNED) ASC")
            ->orderByRaw("CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(REPLACE(COALESCE(NULLIF(d_sub.location_code, ''), NULLIF(d_main.location_code, '')), 'LEGACY-WP-', ''), '-', 7), '-', -1) AS UNSIGNED) ASC")
            ->orderByRaw("CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(REPLACE(COALESCE(NULLIF(d_sub.location_code, ''), NULLIF(d_main.location_code, '')), 'LEGACY-WP-', ''), '-', 8), '-', -1) AS UNSIGNED) ASC")
            ->orderByRaw("CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(REPLACE(COALESCE(NULLIF(d_sub.location_code, ''), NULLIF(d_main.location_code, '')), 'LEGACY-WP-', ''), '-', 9), '-', -1) AS UNSIGNED) ASC")
            ->orderByRaw('COALESCE(d_main.sort_order, 0) ASC')
            ->orderByRaw("COALESCE(NULLIF(d_main.department_name, ''), '') ASC")
            ->orderByRaw('COALESCE(d_sub.sort_order, 0) ASC')
            ->orderByRaw("COALESCE(NULLIF(d_sub.department_name, ''), '') ASC")
            ->orderByRaw('CASE WHEN p_order.position_rank IS NULL THEN 1 ELSE 0 END ASC')
            ->orderByRaw('p_order.position_rank ASC')
            ->orderByRaw("COALESCE(NULLIF(p_order.position_name_km, ''), NULLIF(p_order.position_name, ''), '') ASC")
            ->orderByRaw("COALESCE(NULLIF(employees.last_name, ''), '') ASC")
            ->orderByRaw("COALESCE(NULLIF(employees.first_name, ''), '') ASC")
            ->orderBy('employees.id', 'ASC');
    }

    protected function resolvePayLevelKmLabel($employee): string
    {
        $current = $employee->currentPayGradeHistory?->payLevel;
        if ($current) {
            $km = trim((string) ($current->level_name_km ?? ''));
            if ($km !== '') {
                return $km;
            }

            $code = trim((string) ($current->level_code ?? ''));
            if ($code !== '') {
                return $this->normalizePayCodeToKhmer($code);
            }
        }

        $latest = $employee->latestPayGradeHistory?->payLevel;
        if ($latest) {
            $km = trim((string) ($latest->level_name_km ?? ''));
            if ($km !== '') {
                return $km;
            }

            $code = trim((string) ($latest->level_code ?? ''));
            if ($code !== '') {
                return $this->normalizePayCodeToKhmer($code);
            }
        }

        $legacy = trim((string) ($employee->employee_grade ?? ''));
        if ($legacy === '') {
            return '';
        }

        $byCode = $this->payLevelKmByCode();
        $lookupKey = $this->normalizePayCodeKey($legacy);
        if ($lookupKey !== '' && isset($byCode[$lookupKey]) && trim((string) $byCode[$lookupKey]) !== '') {
            return trim((string) $byCode[$lookupKey]);
        }

        return $this->normalizePayCodeToKhmer($legacy);
    }

    protected function payLevelKmByCode(): array
    {
        static $cache = null;

        if (is_array($cache)) {
            return $cache;
        }

        $cache = [];
        GovPayLevel::query()->select('level_code', 'level_name_km')->get()->each(function ($row) use (&$cache) {
            $key = $this->normalizePayCodeKey((string) ($row->level_code ?? ''));
            $value = trim((string) ($row->level_name_km ?? ''));
            if ($key !== '' && $value !== '') {
                $cache[$key] = $value;
            }
        });

        return $cache;
    }

    protected function normalizePayCodeKey(string $value): string
    {
        return strtoupper(preg_replace('/\s+/', '', trim($value)) ?: '');
    }

    protected function normalizePayCodeToKhmer(string $value): string
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

    protected function resolveEmployeeDisplayUnitPath(Employee $employee): string
    {
        $chain = $this->employeeUnitChain($employee);
        if (empty($chain)) {
            return '-';
        }

        return implode(' | ', $chain);
    }

    protected function employeeUnitChain(Employee $employee): array
    {
        $currentId = (int) ($employee->sub_department_id ?: $employee->department_id ?: 0);
        if ($currentId <= 0) {
            return [];
        }

        $visited = [];
        $chain = [];
        $guard = 0;

        while ($currentId > 0 && $guard < 50) {
            if (isset($visited[$currentId])) {
                break;
            }
            $visited[$currentId] = true;

            $node = $this->employeeUnitNode($currentId);
            if (!$node) {
                break;
            }

            $name = trim((string) ($node['name'] ?? ''));
            if ($name !== '') {
                $chain[] = $name;
            }

            $currentId = (int) ($node['parent_id'] ?? 0);
            $guard++;
        }

        if (empty($chain)) {
            return [];
        }

        return array_values(array_unique(array_reverse($chain)));
    }

    protected function employeeUnitNode(int $departmentId): ?array
    {
        static $cache = [];

        if ($departmentId <= 0) {
            return null;
        }

        if (array_key_exists($departmentId, $cache)) {
            return $cache[$departmentId];
        }

        $unit = Department::withoutGlobalScopes()
            ->select(['id', 'department_name', 'parent_id'])
            ->find($departmentId);

        if (!$unit) {
            $cache[$departmentId] = null;
            return null;
        }

        $cache[$departmentId] = [
            'id' => (int) $unit->id,
            'name' => (string) ($unit->department_name ?? ''),
            'parent_id' => (int) ($unit->parent_id ?? 0),
        ];

        return $cache[$departmentId];
    }

    protected function managedBranchIds(OrgUnitRuleService $orgUnitRuleService): ?array
    {
        return app(OrgHierarchyAccessService::class)->managedBranchIds(auth()->user());
    }

    protected function applyBranchScope(QueryBuilder $query, array $branchIds): QueryBuilder
    {
        return $query->where(function ($q) use ($branchIds) {
            $q->whereIn('department_id', $branchIds)
                ->orWhereIn('sub_department_id', $branchIds);
        });
    }

    /**
     * Optional method if you want to use html builder.
     *
     * @return \Yajra\DataTables\Html\Builder
     */
    public function html(): HtmlBuilder
    {
        return $this->builder()
            ->setTableId('employee-table')
            ->setTableAttribute('class', 'table table-hover table-bordered align-middle')
            ->columns($this->getColumns())
            ->minifiedAjax()
            ->orderBy(1, 'asc')
            ->language([
                'processing' => '<div class="lds-spinner">
                <div></div><div></div><div></div><div></div><div></div><div></div><div></div><div></div><div></div><div></div><div></div><div></div></div>',
                'search' => localize('search') . ':',
                'lengthMenu' => localize('show') . ' _MENU_ ' . localize('entries', 'ធាតុ'),
                'zeroRecords' => localize('no_matching_records', 'មិនមានទិន្នន័យត្រូវគ្នា'),
                'emptyTable' => localize('no_data_available'),
                'info' => localize('showing_records', 'បង្ហាញ _START_ ដល់ _END_ នៃ _TOTAL_ ធាតុ'),
                'infoEmpty' => localize('no_data_available'),
                'infoFiltered' => localize('filtered_from_total', '(ត្រងពី _MAX_ ធាតុសរុប)'),
                'paginate' => [
                    'previous' => localize('previous'),
                    'next' => localize('next'),
                ],
            ])
            ->selectStyleSingle()
            ->lengthMenu([[10, 25, 50, 100, -1], [10, 25, 50, 100, localize('all')]])
            ->dom("<'row mb-3'<'col-md-4'l><'col-md-4 text-center'B><'col-md-4'f>>rt<'bottom'<'row'<'col-md-6'i><'col-md-6'p>>><'clear'>")
            ->buttons([
                Button::make('csv')
                    ->className('btn btn-secondary buttons-csv buttons-html5 btn-sm prints')
                    ->text('<i class="fa fa-file-csv"></i> ' . e(localize('export_csv', 'នាំចេញ CSV')))->exportOptions(['columns' => [0, 1, 2, 3, 4, 5, 6, 7, 8, 9]]),
                Button::make('excel')
                    ->className('btn btn-secondary buttons-excel buttons-html5 btn-sm prints')
                    ->text('<i class="fa fa-file-excel"></i> ' . e(localize('export_excel', 'នាំចេញ Excel')))
                    ->action("function(e, dt, node, config) {
                        var payload = {
                            employee_name: ($('#employee_name').val() || ''),
                            department: ($('#department').val() || ''),
                            designation: ($('#designation').val() || ''),
                            official_id_10: ($('#official_id_10').val() || ''),
                            work_status_name: ($('#work_status_name').val() || ''),
                            employee_status: ($('#employee_status').val() || ''),
                            gender: ($('#gender').val() || ''),
                            search: (dt.search() || '')
                        };
                        var query = $.param(payload);
                        var url = '" . route('employees.export-excel') . "';
                        window.location.href = query ? (url + '?' + query) : url;
                    }"),
            ]);
    }

    /**
     * Get the dataTable columns definition.
     *
     * @return array
     */
    public function getColumns(): array
    {
        return [

            Column::make('DT_RowIndex')
                ->title(localize('sl'))
                ->addClass('text-center')
                ->searchable(false)
                ->orderable(false),

            Column::make('official_id_10')
                ->title(localize('official_id')),

            Column::make('full_name')
                ->title(localize('name_of_employee'))
                ->orderable(false),

            Column::make('gender_id')
                ->title(localize('gender'))
                ->orderable(false),

            Column::make('date_of_birth')
                ->title(localize('date_of_birth'))
                ->orderable(false),

            Column::make('position_id')
                ->title(localize('role'))
                ->orderable(false),

            Column::make('skill')
                ->title(localize('skill_name'))
                ->orderable(false),

            Column::make('pay_level')
                ->title(localize('pay_level_type'))
                ->orderable(false),

            Column::make('phone')
                ->title(localize('mobile_no'))
                ->orderable(false),

            Column::make('work_status_name')
                ->title(localize('work_status'))
                ->orderable(false),

            Column::make('action')
                ->title(localize('action'))->addClass('column-sl')->orderable(false)
                ->searchable(false)
                ->printable(false)
                ->exportable(false),
        ];
    }

}
