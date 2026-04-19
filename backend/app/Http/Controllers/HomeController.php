<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Artisan;
use Modules\HumanResource\Entities\ApplyLeave;
use Modules\HumanResource\Entities\Employee;
use Modules\HumanResource\Entities\Position;
use Modules\HumanResource\Entities\Department;
use Modules\HumanResource\Entities\CandidateSelection;
use Modules\HumanResource\Entities\Award;
use Modules\HumanResource\Entities\Loan;
use Modules\HumanResource\Entities\Notice;
use Modules\HumanResource\Entities\PointSettings;
use Modules\HumanResource\Entities\WeekHoliday;
use Modules\HumanResource\Entities\ProjectTasks;
use Modules\HumanResource\Entities\ProjectManagement;
use Modules\HumanResource\Entities\Holiday;
use Modules\Localize\Entities\Langstring;
use Modules\Localize\Entities\Langstrval;

class HomeController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth']);
        $this->middleware('permission:read_dashboard')->only('index');
    }

    /**
     * Public root redirect for /backend.
     */
    public function rootRedirect()
    {
        return redirect()->route('login');
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function index()
    {
        $total_employee   = Employee::where('is_active', 1)->count();
        $present_employee = $this->count_attend_employee();
        $today_leave_data = $this->leave_employee();
        $today_leave      = $today_leave_data ? (int) $today_leave_data->leave_total : 0;

        $departments = Department::query()
            ->whereNull('parent_id')
            ->where('is_active', true)
            ->get(['id', 'department_name']);

        // department wise employee attendances
        $departmentWiseAttendanceReport = $this->attendanceDepartmentWise(0);

        // position wise candidate count
        $positionWiseAttendanceReport = $this->recruitmentPositionWise(0);

        // leave appropriated employee
        $leaveEmployees = ApplyLeave::with([
            'employee:id,employee_id,first_name,middle_name,last_name,email,position_id,department_id',
            'employee.position:id,position_name'
        ])
            ->latest()
            ->take(4)
            ->get();

        // recruitment employees
        $newCandidates = CandidateSelection::with([
            'employee:id,employee_id,first_name,middle_name,last_name,email,position_id,department_id',
            'employee.position:id,position_name'
        ])
            ->latest()
            ->take(3)
            ->get();

        // Notice list
        $notices = Notice::query()
            ->withoutGlobalScope('sortByLatest')
            ->orderByDesc('notice_date')
            ->orderByDesc('id')
            ->take(3)
            ->get();

        // department wise employee awards monthly [Jan, Feb, Mar, Apr, May, Jun, Jul, Aug, Sep, Oct, Nov, Dec]
        $months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
        $monthMap = [
            1 => 'January',
            2 => 'February',
            3 => 'March',
            4 => 'April',
            5 => 'May',
            6 => 'June',
            7 => 'July',
            8 => 'August',
            9 => 'September',
            10 => 'October',
            11 => 'November',
            12 => 'December',
        ];
        $currentYear = Carbon::now()->year;
        $awardReportData = [];
        $departmentNameById = $departments->pluck('department_name', 'id');

        // Initialize the report array with the department names and month-wise data
        foreach ($departments as $department) {
            foreach ($months as $month) {
                $awardReportData[$department->department_name][$month] = 0;
            }
        }

        $awardRows = Award::query()
            ->join('employees', 'employees.id', '=', 'awards.employee_id')
            ->selectRaw('employees.department_id as department_id, MONTH(awards.date) as month_no, COUNT(*) as total')
            ->whereYear('awards.date', $currentYear)
            ->whereIn('employees.department_id', $departmentNameById->keys()->all())
            ->groupBy('employees.department_id', DB::raw('MONTH(awards.date)'))
            ->get();

        foreach ($awardRows as $row) {
            $departmentName = $departmentNameById->get((int) $row->department_id);
            $monthLabel = $monthMap[(int) $row->month_no] ?? null;
            if ($departmentName !== null && $monthLabel !== null) {
                $awardReportData[$departmentName][$monthLabel] = (int) $row->total;
            }
        }

        // Prepare the data for the chart
        $awardChartSeries = [];
        foreach ($awardReportData as $department => $monthlyData) {
            $data = [];
            foreach ($months as $month) {
                $data[] = $monthlyData[$month];
            }
            $awardChartSeries[] = [
                'name' => $department,
                'data' => $data
            ];
        }

        // award list
        $awards = Award::with(['employee:id,first_name,last_name,middle_name,email,department_id', 'employee.department'])
            ->latest()
            ->take(5)
            ->get();

        // loan received from system & loan paid from employee total amount
        $totalLoanPaid = (float) Loan::query()->sum('released_amount');

        return view('backend.layouts.home', compact([
            'present_employee',
            'today_leave',
            'total_employee',
            'departmentWiseAttendanceReport',
            'positionWiseAttendanceReport',
            'leaveEmployees',
            'newCandidates',
            'notices',
            'awards',
            'awardReportData',
            'awardChartSeries',
            'totalLoanPaid',
        ]));
    }

    public function staffHome()
    {
        return redirect()->route('myProfile');
    }

    public function empProfile()
    {
        return view('auth.profile-info');
    }

    public function allClear()
    {
        Artisan::call('route:clear');
        Artisan::call('optimize:clear');
        Artisan::call('storage:unlink');
        Artisan::call('module:asset-link');
        Artisan::call('storage:link');

        return redirect()->intended();
    }

    /**
     * Backward-compatible redirect for legacy profile links.
     */
    public function redirectLegacyEmployeeProfile()
    {
        return redirect()->route('myProfile');
    }

    /**
     * Legacy helper endpoint used during local setup.
     */
    public function storageLinkTools()
    {
        Artisan::call('module:asset-link');
        Artisan::call('storage:unlink');
        Artisan::call('storage:link');

        return response('Storage links refreshed successfully.');
    }

    /**
     * Legacy helper endpoint to seed language keys.
     */
    public function insertLanguage()
    {
        DB::table('langstrings')->truncate();
        $langStrings = __('language');

        foreach ($langStrings as $key => $value) {
            Langstring::query()->create([
                'key' => $key,
            ]);
        }

        return response('Phrase Inserted Successfully..!!');
    }

    /**
     * Legacy helper endpoint to seed language values.
     */
    public function insertLanguageValue()
    {
        $langStrings = __('language');
        $index = 1;

        foreach ($langStrings as $value) {
            Langstrval::query()->create([
                'localize_id' => 2,
                'langstring_id' => $index,
                'phrase_value' => $value,
            ]);
            $index++;
        }

        return response('Phrase Value Inserted Successfully..!!');
    }

    /**
     * Legacy helper endpoint for session smoke test.
     */
    public function testSession()
    {
        session()->put('test1', 'Phrase Value Inserted Successfully..!!');
        return response((string) session()->get('test1'));
    }

    // myProfile
    public function myProfile()
    {
        // Get the authenticated user ID
        $userId = Auth::id();
        $userInfo = Auth::user();
        if ($userInfo->user_type_id == 1) {
            return redirect()->route('empProfile');
        }
        $employee_info = Employee::where('user_id', $userId)
            ->first();

        if (!$employee_info) {
            Toastr::error(localize('employee_profile_not_found', 'Employee profile is not linked to this account.'));
            return redirect()->route('empProfile');
        }

        $point_settings     = PointSettings::first();

        $total_employee     = Employee::where('is_active', 1)->count();
        $present_empl       = $this->count_attend_employee_current_year($employee_info->id);
        $late_attend        = $this->count_late_attend_current_year($employee_info->id, $point_settings);
        $total_working_days = $this->count_total_working_days($employee_info->id);
        $total_absent_days  = $total_working_days - $present_empl;
        $absent_days = 0;
        if ($total_absent_days > 0) {
            $absent_days = $total_absent_days;
        }
        $leave_applications = $this->get_leave_applications($employee_info->id);
        $current_year_holidays = $this->get_holidays_current_year();

        // Notice list
        $notices = Notice::query()
            ->withoutGlobalScope('sortByLatest')
            ->orderByDesc('notice_date')
            ->orderByDesc('id')
            ->take(3)
            ->get();

        $project_tasks = $this->get_project_tasks($employee_info->id);
        $project_complete_status = $this->get_project_complete_status($employee_info->id);

        $user_info = Auth::user();

        return view(
            'backend.layouts.emp-dashboard',
            compact(
                'userInfo',
                'total_employee',
                'present_empl',
                'late_attend',
                'absent_days',
                'leave_applications',
                'notices',
                'project_tasks',
                'project_complete_status',
                'user_info',
                'current_year_holidays',
            )
        );
    }

    public function get_holidays_current_year()
    {
        $year = date('Y');

        // leave applications
        $holidays = Holiday::select('*')
            ->whereYear('start_date', $year)
            ->orderBy('start_date', 'desc')
            ->get();

        $resp_array = [];
        foreach ($holidays as $holiday) {
            $child_array = [];
            // $dates = [];
            // $dates[] = $holiday->start_date;
            // $dates[] = $holiday->end_date;
            $child_array = [$holiday->start_date, $holiday->end_date];

            // Parse the date using Carbon
            $carbonDate = Carbon::parse($holiday->start_date);
            // Get the month name
            $monthName = $carbonDate->format('F');

            if (isset($resp_array[$monthName])) {
                $resp_array[$monthName][count($resp_array[$monthName])] = $child_array;
            } else {
                $resp_array[$monthName][0] = $child_array;
            }
        }

        return $resp_array;
    }

    public function get_project_complete_status($employee_id)
    {
        // Get employee projects id
        $project_ids = ProjectTasks::select('project_id')
            ->where('employee_id', $employee_id)
            ->groupBy('project_id')
            ->get()
            ->pluck('project_id')
            ->toArray();

        $projects = ProjectManagement::with('clientDetail', 'projectLead')
            ->whereIn('id', $project_ids)
            ->get();

        $project_complete_info = [];
        $project_names = [];
        $complete_percentages = [];
        foreach ($projects as $key => $data) {

            $child_array = [];

            // Getting progressbar value for approximate_tasks vs completed_tasks
            $percentage = 100;
            $complete_percentage = 0;
            $remaining_percentage = 0;

            $approximate_tasks = $data->approximate_tasks ?? 0;
            $complete_tasks = $data->complete_tasks ?? 0;

            if ($approximate_tasks != 0) {
                $complete_percentage = ($complete_tasks / $approximate_tasks) * 100;
                $complete_percentage = round($complete_percentage);

                $remaining_percentage = $percentage - $complete_percentage;
                $remaining_percentage = round($remaining_percentage);
            }

            $project_names[] = $data->project_name;
            $complete_percentages[] = $complete_percentage;
        }

        $project_complete_info['project_names'] = $project_names;
        $project_complete_info['complete_percentages'] = $complete_percentages;

        return $project_complete_info;
    }

    public function get_project_tasks($employee_id)
    {
        $tasks = ProjectTasks::select('pm_tasks_list.*', 'p.project_name')
            ->leftJoin('pm_projects as p', 'p.id', '=', 'pm_tasks_list.project_id')
            ->where('pm_tasks_list.employee_id', $employee_id)
            ->latest()
            ->take(10)
            ->get();

        return $tasks;
    }

    public function get_leave_applications($employee_id)
    {
        $year = date('Y');

        // leave applications
        $leaveApplications = ApplyLeave::with([
            'employee:id,employee_id,first_name,middle_name,last_name,email,position_id,department_id',
            'employee.position:id,position_name'
        ])
            ->where('employee_id', $employee_id)
            ->latest()
            ->take(3)
            ->get();

        return $leaveApplications;
    }

    public function count_total_working_days($employee_id)
    {
        $year = date('Y');
        return $this->totalWorkingDays($year, $employee_id);
    }

    /*
    | Numbers of days worked by any employee for particular branch
    */
    public function totalWorkingDays($year, $employee_id)
    {
        $betweendate = $this->betweenDate();

        //Calculate monthly working days for the employee
        $all_weekends = WeekHoliday::first();
        $weekends_days = explode(',', @$all_weekends->dayname);

        $weekends = array();
        $validDays = ["Saturday", "Sunday", "Monday", "Tuesday", "Wednesday", "Thursday", "Friday"];

        foreach ($weekends_days as $value) {
            if (in_array(ucwords($value), $validDays)) {
                $weekends[] = ucwords($value);
            }
        }

        $holidaysWithSum = DB::table('holidays')
            ->select('holidays.*', DB::raw('SUM(total_day) as total_day_sum'))
            ->where('holidays.start_date', '>=', $betweendate['start_date'])
            ->where('holidays.end_date', '<=', $betweendate['end_date'])
            ->whereNull('holidays.deleted_at')
            ->first();

        $totalHolidays = (int)$holidaysWithSum->total_day_sum;

        //Now calculate Monthly Working Days
        $workdays = array();

        $days_and_weekends_count = $this->getFridaysAndSaturdays(
            $betweendate['start_date'],
            $betweendate['end_date'],
            $weekends
        );

        //total leave calculation start
        $getLeaveInfo =  DB::table('apply_leaves')
            ->select('apply_leaves.*', DB::raw('SUM(total_approved_day) as total_day_sum'))
            ->where('apply_leaves.leave_approved_start_date', '>=', $betweendate['start_date'])
            ->where('apply_leaves.leave_approved_end_date', '<=', $betweendate['end_date'])
            ->where('apply_leaves.is_approved_by_manager', 1)
            ->where('apply_leaves.is_approved', 1)
            ->where('apply_leaves.employee_id', $employee_id)
            ->whereNull('apply_leaves.deleted_at')
            ->first();

        $totalLeaveDays = (int)$getLeaveInfo->total_day_sum;

        // total leave calculation start

        $totalWeekends = $days_and_weekends_count['totalWeekends'];
        $totalDays = $days_and_weekends_count['totalDays'];

        $workdays = $totalDays - $totalWeekends - $totalHolidays - $totalLeaveDays;

        if ($workdays > 0) {
            return $workdays;
        }
        return 0;
    }

    public function getFridaysAndSaturdays($startDate, $endDate, $weekends)
    {

        $startTimestamp = strtotime($startDate);
        $endTimestamp = strtotime($endDate);

        if ($weekends && count($weekends) > 0) {

            $result = [];


            $num_days = array();
            foreach ($weekends  as $day) {
                $num_days[] = date('N', strtotime($day));
            }

            $totalDays = 0;
            for ($currentDate = $startTimestamp; $currentDate <= $endTimestamp; $currentDate += 86400) {

                $totalDays++;
                $currentDayOfWeek = date('N', $currentDate);

                in_array($currentDayOfWeek, $num_days);

                // Check if the current day is Friday (5) or Saturday (6)
                if (in_array($currentDayOfWeek, $num_days)) {
                    $result[] = date('Y-m-d', $currentDate);
                }
            }
        } else {
            $totalDays = 0;
            $result = [];
        }

        $response = array('totalDays' => $totalDays, 'totalWeekends' => count($result));
        return $response;
    }

    public function betweenDate($salaryDate = '')
    {

        $startDate = Carbon::now()->firstOfYear();
        $endDate   = Carbon::now()->format('Y-m-d');
        $betweenDate = array('start_date' => $startDate, 'end_date' => $endDate);

        return $betweenDate;
    }

    public function count_late_attend_current_year($employee_id, $point_settings)
    {
        if ($point_settings == null) {
            return 0;
        } else {

            $definedTime = $point_settings->attendance_end . ':00';
            $year = date('Y');

            $result = DB::table('attendances')
                ->select(
                    DB::raw('DATE(time) as date'),
                    'employee_id',
                    DB::raw('MIN(time) as first_time'),
                    DB::raw('COUNT(*) as attendance_count')
                )
                ->where('employee_id', $employee_id)
                ->whereYear('time', $year)
                ->whereNull('deleted_at')
                ->groupBy(DB::raw('DATE(time)'), 'employee_id')
                ->havingRaw('TIME(MIN(time)) > ?', [$definedTime])
                ->get();

            // Count the number of distinct attendance records (days with attendance)
            return $result->count();
        }
    }

    public function count_attend_employee_current_year($employee_id)
    {
        $year = date('Y');

        $result = DB::table('attendances')
            ->select(
                DB::raw('DATE(time) as date'),
                'employee_id',
                DB::raw('MIN(time) as first_time'),
                DB::raw('COUNT(*) as attendance_count')
            )
            ->where('employee_id', $employee_id)
            ->whereYear('time', $year)
            ->whereNull('deleted_at')
            ->groupBy(DB::raw('DATE(time)'))
            ->get();

        return $result->count();
    }

    // editMyProfile
    public function editMyProfile()
    {
        return view('auth.edit-profile');
    }

    public function count_attend_employee()
    {
        $date = date('Y-m-d');
        $result = DB::table('attendances')
            ->select(DB::raw('COUNT(*)'))
            ->whereDate('time', $date)
            ->whereNull('deleted_at') // Check if deleted_at is NULL
            ->groupBy('employee_id')
            ->get();

        $count = $result->count();

        return $count;
    }

    public function leave_employee()
    {
        $date  = date('Y-m-d');

        $result = DB::table('apply_leaves')
            ->select(DB::raw('COUNT(employee_id) as leave_total'))
            ->whereDate('leave_approved_start_date', '<=', $date)
            ->whereDate('leave_approved_end_date', '>=', $date)
            ->whereNull('deleted_at') // Check if deleted_at is NULL
            ->get();

        if ($result->isNotEmpty()) {
            return $result->first();
        } else {
            return null;
        }
    }

    // Get individual employee dashboard box data
    public function get_employee_box_data()
    {
        $data_boxes = array();

        // Get the authenticated user ID
        $userId = Auth::id();
        $employee_info = Employee::where('is_active', 1)
            ->where('user_id', $userId)
            ->first();

        $employee_id = $employee_info->id;

        $currentDate = Carbon::now();
        $dateY = $currentDate->format('Y');
        $dateM = $currentDate->format('m');

        // Total points of employee
        $dataTotal = DB::table('reward_points')
            ->where('employee_id', $employee_id)
            ->whereNull('deleted_at') // Check if deleted_at is NULL
            ->sum('total');

        $dataTotal = $dataTotal ?? 0;

        $data_boxes['total'] = $dataTotal;

        // Attendance point current year
        $dataAttendence = DB::table('point_attendances')
            ->where('employee_id', $employee_id)
            ->whereNull('deleted_at') // Check if deleted_at is NULL
            ->whereYear('created_at', $dateY)
            ->sum('point');

        $dataAttendence = $dataAttendence ?? 0;

        $data_boxes['attendence'] = $dataAttendence;

        // Collaborative point current year
        $dataCollaborative = DB::table('point_collaboratives')
            ->where('point_shared_with', $employee_id)
            ->whereYear('point_date', $dateY)
            ->whereNull('deleted_at') // Check if deleted_at is NULL
            ->sum('point');

        $dataCollaborative = $dataCollaborative ?? 0;

        $data_boxes['collaborative'] = $dataCollaborative;

        //Management point current year
        $dataManagement = DB::table('point_management')
            ->where('employee_id', $employee_id)
            ->whereYear('created_at', $dateY)
            ->whereNull('deleted_at') // Check if deleted_at is NULL
            ->sum('point');

        $dataManagement = $dataManagement ?? 0;

        $data_boxes['management'] = $dataManagement;

        return $data_boxes;
    }

    public function attendanceDepartmentWise($type)
    {
        [$fromDate, $toDate] = $this->resolveDateRangeByType(request()->get('type') ?? $type);

        $departments = Department::query()
            ->whereNull('parent_id')
            ->where('is_active', true)
            ->get(['id', 'department_name']);

        $departmentIds = $departments->pluck('id')->all();
        if (empty($departmentIds)) {
            return [];
        }

        $totalEmployeesByDepartment = Employee::query()
            ->select('department_id', DB::raw('COUNT(*) as total'))
            ->whereIn('department_id', $departmentIds)
            ->groupBy('department_id')
            ->pluck('total', 'department_id');

        $presentByDepartment = DB::table('attendances')
            ->join('employees', 'employees.id', '=', 'attendances.employee_id')
            ->whereIn('employees.department_id', $departmentIds)
            ->whereBetween('attendances.time', [$fromDate, $toDate])
            ->whereNull('employees.deleted_at')
            ->whereNull('attendances.deleted_at')
            ->select('employees.department_id', DB::raw('COUNT(DISTINCT attendances.employee_id) as present_count'))
            ->groupBy('employees.department_id')
            ->pluck('present_count', 'employees.department_id');

        $fromDateOnly = $fromDate->toDateString();
        $toDateOnly = $toDate->toDateString();
        $leaveByDepartment = DB::table('apply_leaves')
            ->join('employees', 'employees.id', '=', 'apply_leaves.employee_id')
            ->whereIn('employees.department_id', $departmentIds)
            ->whereNull('employees.deleted_at')
            ->whereNull('apply_leaves.deleted_at')
            ->where(function ($query) use ($fromDateOnly, $toDateOnly) {
                $query->whereBetween('leave_apply_start_date', [$fromDateOnly, $toDateOnly])
                    ->orWhereBetween('leave_apply_end_date', [$fromDateOnly, $toDateOnly])
                    ->orWhere(function ($subQuery) use ($fromDateOnly, $toDateOnly) {
                        $subQuery->where('leave_apply_start_date', '<=', $fromDateOnly)
                            ->where('leave_apply_end_date', '>=', $toDateOnly);
                    });
            })
            ->select('employees.department_id', DB::raw('COUNT(DISTINCT apply_leaves.employee_id) as leave_count'))
            ->groupBy('employees.department_id')
            ->pluck('leave_count', 'employees.department_id');

        $departmentWiseAttendanceReport = [];
        foreach ($departments as $department) {
            $departmentId = (int) $department->id;
            $totalEmployees = (int) ($totalEmployeesByDepartment[$departmentId] ?? 0);
            $presentCount = (int) ($presentByDepartment[$departmentId] ?? 0);
            $leaveCount = (int) ($leaveByDepartment[$departmentId] ?? 0);
            $absentCount = $totalEmployees - $presentCount - $leaveCount;

            if ($totalEmployees > 0) {
                if ($absentCount < 0) {
                    $absentCount = 0;
                }
                $presentPercentage = round(($presentCount / $totalEmployees) * 100, 2);
                $absentPercentage = round(($absentCount / $totalEmployees) * 100, 2);
                $leavePercentage = round(($leaveCount / $totalEmployees) * 100, 2);
            } else {
                $presentPercentage = 0;
                $absentPercentage = 0;
                $leavePercentage = 0;
            }

            $departmentWiseAttendanceReport[] = [
                'department' => $department->department_name,
                'present' => $presentPercentage,
                'absent' => $absentPercentage,
                'leave' => $leavePercentage,
            ];
        }

        return $departmentWiseAttendanceReport;
    }

    public function recruitmentPositionWise(int $type)
    {
        [$fromDate, $toDate] = $this->resolveDateRangeByType(request()->get('type') ?? $type);

        $positions = Position::query()
            ->where('is_active', true)
            ->get(['id', 'position_name', 'is_active']);

        $candidateCountByPosition = CandidateSelection::query()
            ->select('position_id', DB::raw('COUNT(*) as total'))
            ->whereBetween('created_at', [$fromDate, $toDate])
            ->groupBy('position_id')
            ->pluck('total', 'position_id');

        $positionWiseAttendanceReport = [];
        foreach ($positions as $position) {
            $candidateCount = (int) ($candidateCountByPosition[$position->id] ?? 0);
            $positionWiseAttendanceReport[] = [
                'position' => $position->position_name,
                'candidate' => $candidateCount,
            ];
        }

        return $positionWiseAttendanceReport;
    }

    /**
     * Resolve dashboard period selector to a date range.
     *
     * @return array{0:\Carbon\Carbon,1:\Carbon\Carbon}
     */
    private function resolveDateRangeByType($type): array
    {
        $rangeType = (int) $type;

        return match ($rangeType) {
            1 => [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()],
            2 => [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()],
            3 => [Carbon::now()->startOfYear(), Carbon::now()->endOfYear()],
            default => [Carbon::now()->startOfDay(), Carbon::now()->endOfDay()],
        };
    }
}
