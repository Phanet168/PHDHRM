<?php

namespace Modules\HumanResource\DataTables;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Modules\HumanResource\Entities\ApplyLeave;
use Modules\HumanResource\Entities\Attendance;
use Modules\HumanResource\Entities\Employee;
use Modules\HumanResource\Entities\ManualAttendance;
use Modules\HumanResource\Support\OrgUnitRuleService;
use Yajra\DataTables\CollectionDataTable;
use Yajra\DataTables\Html\Button;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class AttendanceSummaryDataTable extends DataTable
{
    /**
     * Build DataTable Class
     *
     * @param mixed $query Results from query() method.
     * @return \Yajra\DataTables\DataTableAbstract
     */
    public function dataTable(): CollectionDataTable
    {
        $query = (new CollectionDataTable($this->collection()))->addIndexColumn();

        return $query->escapeColumns([]);
    }

    public function collection()
    {

        $startDate = Carbon::now()->startOfMonth()->format('Y-m-d');
        $endDate = Carbon::now()->format('Y-m-d');

        $workplaceId = (int) ($this->request->get('workplace_id') ?: $this->request->get('department_id') ?: 0);
        $branchIds = $workplaceId > 0
            ? app(OrgUnitRuleService::class)->branchIdsIncludingSelf($workplaceId)
            : [];

        $date = $this->request->get('date');
        $string = $date ? explode('-', $date) : [];

        if ($date && count($string) >= 2) {
            $startDate = date('Y-m-d', strtotime($string[0]));
            $endDate = date('Y-m-d', strtotime($string[1]));
        }

        $startDate = new \DateTime($startDate);
        $endDate = new \DateTime($endDate);

        $activeEmployeesQuery = Employee::query()
            ->where('is_active', true)
            ->where('is_left', false);

        if ($workplaceId > 0) {
            $this->applyEmployeeBranchScope($activeEmployeesQuery, $branchIds);
        }

        $activeEmployeeIds = $activeEmployeesQuery->pluck('id')->all();
        $activeEmployeeCount = count($activeEmployeeIds);

        $data = [];

        for ($date = $startDate; $date <= $endDate; $date->modify('+1 day')) {
            $attendanceDate = Carbon::parse($date)->format('Y-m-d');

            $presentEmployeeQuery = ManualAttendance::query()
                ->whereDate('time', '=', $attendanceDate)
                ->whereNotNull('time');

            if ($activeEmployeeCount > 0) {
                $presentEmployeeQuery->whereIn('employee_id', $activeEmployeeIds);
            } else {
                $presentEmployeeQuery->whereRaw('1 = 0');
            }

            $totalPresentEmployees = (int) $presentEmployeeQuery
                ->groupBy('employee_id')
                ->get()
                ->count();

            $totalLateEmployees = 0;
            if ($activeEmployeeCount > 0) {
                $lateRows = Attendance::query()
                    ->selectRaw('employee_id, MIN(time) as first_time')
                    ->whereDate('time', $attendanceDate)
                    ->whereIn('employee_id', $activeEmployeeIds)
                    ->groupBy('employee_id')
                    ->with([
                        'employee:id,attendance_time_id,department_id,sub_department_id',
                        'employee.attendance_time:id,start_time',
                    ])
                    ->get();

                foreach ($lateRows as $lateRow) {
                    $expectedStart = optional(optional($lateRow->employee)->attendance_time)->start_time;
                    if (!$expectedStart || !$lateRow->first_time) {
                        continue;
                    }

                    if (Carbon::parse($lateRow->first_time)->format('H:i:s') > Carbon::parse($expectedStart)->format('H:i:s')) {
                        $totalLateEmployees++;
                    }
                }
            }

            $leaveQuery = ApplyLeave::query()
                ->where('is_approved', true)
                ->whereDate('leave_approved_start_date', '<=', $attendanceDate)
                ->whereDate('leave_approved_end_date', '>=', $attendanceDate);

            if ($activeEmployeeCount > 0) {
                $leaveQuery->whereIn('employee_id', $activeEmployeeIds);
            } else {
                $leaveQuery->whereRaw('1 = 0');
            }

            $totalLeaveEmployees = (int) $leaveQuery
                ->groupBy('employee_id')
                ->get()
                ->count();

            $totalAbsentEmployees = $activeEmployeeCount > 0 ? $activeEmployeeCount - $totalPresentEmployees : 0;

            $data[] = [
                'date' => $attendanceDate,
                'totalPresentEmployees' => $totalPresentEmployees,
                'totalAbsentEmployees' => $totalAbsentEmployees > 0 ? $totalAbsentEmployees - $totalLeaveEmployees : 0,
                'totalLeaveEmployees' => $totalLeaveEmployees,
                'totalLateEmployees' => $totalLateEmployees,
            ];
        }

        return collect($data);
    }

    protected function applyEmployeeBranchScope($query, array $branchIds, string $relation = null)
    {
        if (empty($branchIds)) {
            return $query->whereRaw('1 = 0');
        }

        if ($relation) {
            return $query->whereHas($relation, function ($q) use ($branchIds) {
                $q->where(function ($sq) use ($branchIds) {
                    $sq->whereIn('department_id', $branchIds)
                        ->orWhereIn('sub_department_id', $branchIds);
                });
            });
        }

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
    public function html()
    {
        return $this->builder()
            ->setTableId('attendance-summary-table')
            ->setTableAttribute('class', 'table table-hover table-bordered align-middle')
            ->columns($this->getColumns())
            ->minifiedAjax()
            ->language([
                //change preloader icon
                'processing' => '<div class="lds-spinner">
                <div></div><div></div><div></div><div></div><div></div><div></div><div></div><div></div><div></div><div></div><div></div><div></div></div>',
            ])
            ->responsive(true)
            ->selectStyleSingle()
            ->lengthMenu([[30, 50, 100, -1], [30, 50, 100, 'All']])
            ->dom("<'row mb-3'<'col-md-4'l><'col-md-4 text-center'B><'col-md-4'f>>rt<'bottom'<'row'<'col-md-6'i><'col-md-6'p>>><'clear'>")
            ->buttons([

                Button::make('csv')
                    ->className('btn btn-secondary buttons-csv buttons-html5 btn-sm prints')
                    ->text('<i class="fa fa-file-csv"></i> CSV')->exportOptions(['columns' => [0, 1, 2, 3, 4, 5]]),
                Button::make('excel')
                    ->className('btn btn-secondary buttons-excel buttons-html5 btn-sm prints')
                    ->text('<i class="fa fa-file-excel"></i> Excel')
                    ->extend('excelHtml5')->exportOptions(['columns' => [0, 1, 2, 3, 4, 5]]),
            ]);
    }

    /**
     * Get columns.
     *
     * @return array
     */
    protected function getColumns(): array
    {
        return [
            Column::make('DT_RowIndex')
                ->title(localize('sl'))
                ->addClass('text-center column-sl')
                ->searchable(false)
                ->orderable(false),

            Column::make('date')
                ->title(localize('date'))
                ->addClass('text-center')
                ->searchable(true)
                ->orderable(false),

            Column::make('totalPresentEmployees')
                ->title(localize('present'))
                ->addClass('text-center')
                ->searchable(true)
                ->orderable(false),

            Column::make('totalAbsentEmployees')
                ->title(localize('absent'))
                ->addClass('text-center')
                ->searchable(true)
                ->orderable(false),

            Column::make('totalLeaveEmployees')
                ->title(localize('leave'))
                ->addClass('text-center')
                ->searchable(true)
                ->orderable(false),

            Column::make('totalLateEmployees')
                ->title(localize('late'))
                ->addClass('text-center')
                ->searchable(true)
                ->orderable(false),
        ];
    }

    /**
     * Get filename for export.
     *
     * @return string
     */
    protected function filename(): string
    {
        return 'attendance-summery' . date('YmdHis');
    }
}
