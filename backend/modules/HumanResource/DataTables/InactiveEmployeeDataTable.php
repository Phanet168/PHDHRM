<?php

namespace Modules\HumanResource\DataTables;

use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Modules\HumanResource\Entities\Employee;
use Modules\HumanResource\Entities\GovPayLevel;
use Modules\HumanResource\Support\OrgHierarchyAccessService;
use Modules\HumanResource\Support\OrgUnitRuleService;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Button;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class InactiveEmployeeDataTable extends DataTable
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
            ->editColumn('position_id', function ($employee) {
                if (!$employee->position) {
                    return '-';
                }
                return $employee->position->position_name_km ?: ucwords($employee->position->position_name ?? '');
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
            ->addColumn('unit_name', function ($employee) {
                $unit = $employee->sub_department ?: $employee->department;
                return $unit?->department_name ?? '';
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
            ->filterColumn('position_id', function ($query, $keyword) {
                $query->whereHas('position', function ($q) use ($keyword) {
                    $q->where('position_name', 'like', "%{$keyword}%")
                        ->orWhere('position_name_km', 'like', "%{$keyword}%");
                });
            })
            ->filterColumn('gender_id', function ($query, $keyword) {
                $query->whereHas('gender', function ($q) use ($keyword) {
                    $q->where('gender_name', 'like', "%{$keyword}%");
                });
            })
            ->addColumn('status', function ($employee) {
                return '<span class="badge badge-danger-soft">' . localize('inactive') . '</span>';
            })

            ->addColumn('action', function ($employee) {

                $button = '';
                if (auth()->user()->can('read_inactive_employees_list')) {
                    $button .= '<a href="' . route('employees.show', $employee->id) . '" class="btn btn-primary-soft btn-sm me-1" title="Show"><i class="fa fa-eye"></i></a>';
                }
                if (auth()->user()->can('update_inactive_employees_list')) {
                    $button .= '<a href="' . route('employees.edit', $employee->id) . '" class="btn btn-success-soft btn-sm me-1" title="Edit"><i class="fa fa-edit"></i></a>';

                }
                if (auth()->user()->can('delete_inactive_employees_list')) {
                    $button .= '<a href="javascript:void(0)" class="btn btn-danger-soft btn-sm delete-confirm" data-bs-toggle="tooltip" title="Delete" data-route="' . route('employees.destroy', $employee->id) . '" data-csrf="' . csrf_token() . '"><i class="fas fa-trash-alt"></i></a>';

                }

                return $button;

            })

            ->rawColumns(['status', 'action']);

    }

    /**
     * Get query source of dataTable.
     */
    public function query(Employee $model): QueryBuilder
    {
        $employee_name = $this->request->get('employee_name');
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
            ->with([
                'department',
                'sub_department',
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
            ->when($employee_name, function ($query) use ($employee_name) {
                return $query->where('id', $employee_name);
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
            ->when($blood_group, function ($query) use ($blood_group) {
                return $query->where('blood_group', $blood_group);
            })
            ->when($gender, function ($query) use ($gender) {
                return $query->where('gender_id', $gender);
            })
            ->when($marital_status, function ($query) use ($marital_status) {
                return $query->where('marital_status_id', $marital_status);
            })
            ->when(
                $employeeStatus !== null && $employeeStatus !== '',
                function ($query) use ($employeeStatus) {
                    return $query->where('employees.is_active', (int) $employeeStatus);
                },
                function ($query) {
                    return $query->where('employees.is_active', 0);
                }
            )
            ->leftJoin('positions as p_order', 'employees.position_id', '=', 'p_order.id')
            ->select('employees.*')
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
            ->language([
                //change preloader icon
                'processing' => '<div class="lds-spinner">
                <div></div><div></div><div></div><div></div><div></div><div></div><div></div><div></div><div></div><div></div><div></div><div></div></div>',
            ])
            ->selectStyleSingle()
            ->lengthMenu([[10, 25, 50, 100, -1], [10, 25, 50, 100, 'All']])
            ->dom("<'row mb-3'<'col-md-4'l><'col-md-4 text-center'B><'col-md-4'f>>rt<'bottom'<'row'<'col-md-6'i><'col-md-6'p>>><'clear'>")
            ->buttons([
                Button::make('csv')
                    ->className('btn btn-secondary buttons-csv buttons-html5 btn-sm prints')
                    ->text('<i class="fa fa-file-csv"></i> CSV'),
                Button::make('excel')
                    ->className('btn btn-secondary buttons-excel buttons-html5 btn-sm prints')
                    ->text('<i class="fa fa-file-excel"></i> Excel'),
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
                ->title(localize('name_of_employee')),

            Column::make('gender_id')
                ->title(localize('gender')),

            Column::make('date_of_birth')
                ->title(localize('date_of_birth')),

            Column::make('position_id')
                ->title(localize('role')),

            Column::make('pay_level')
                ->title(localize('pay_level_type')),

            Column::make('phone')
                ->title(localize('mobile_no')),

            Column::make('unit_name')
                ->title(localize('department')),

            Column::make('work_status_name')
                ->title(localize('work_status')),

            Column::make('action')
                ->title(localize('action'))->addClass('column-sl')->orderable(false)
                ->searchable(false)
                ->printable(false)
                ->exportable(false),
        ];
    }

    /**
     * Get filename for export.
     *
     * @return string
     */
    protected function filename(): string
    {
        return 'Employee-' . date('YmdHis');
    }
}
