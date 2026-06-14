<?php

namespace Modules\HumanResource\Http\Controllers;

use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Modules\HumanResource\Entities\Department;
use Modules\HumanResource\Entities\Employee;

class IdcardController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:read_id_card')->only(['employeeindex', 'employeeshow']);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function employeeindex(): Renderable
    {
        $dbData = Employee::with(['position'])
            ->where('is_active', 1)
            ->paginate(30);

        return view('humanresource::idprint.employeeindex', compact('dbData'));
    }

    public function employeeshow(Employee $idprint): Renderable
    {
        $dbData = $this->prepareEmployeeForCard($idprint);

        return view('humanresource::idprint.employeeid', $this->employeeCardViewData($dbData, true));
    }

    public function selfEmployeeCard()
    {
        $user = Auth::user();
        if (!$user) {
            return redirect()->route('login');
        }

        $employee = $user->employee()->with(['position', 'department', 'sub_department'])->first();
        if (!$employee || (int) ($employee->is_active ?? 0) !== 1) {
            Toastr::error(localize('employee_profile_not_found', 'Employee profile is not linked to this account.'));

            return redirect()->route('myProfile');
        }

        $dbData = $this->prepareEmployeeForCard($employee);

        return view('humanresource::idprint.employeeid', $this->employeeCardViewData($dbData, true));
    }

    public function publicProfile(Employee $employee): Renderable
    {
        if ((int) ($employee->is_active ?? 0) !== 1) {
            abort(404);
        }

        $employee = $this->prepareEmployeeForCard($employee);

        return view('humanresource::idprint.public-profile', $this->employeeCardViewData($employee, false));
    }

    protected function employeeCardViewData(Employee $employee, bool $autoPrint = true): array
    {
        $publicProfileUrl = route('idprint.public-profile', $employee->uuid);
        $qrCodePng = app('DNS2D')->getBarcodePNG($publicProfileUrl, 'QRCODE', 4, 4);
        $employeeName = $this->employeeDisplayName($employee);
        $qualificationLabel = $this->employeeQualificationLabel($employee);
        $positionName = $this->employeePositionLabel($employee);
        $employeeNumber = $this->employeeBadgeNumber($employee);
        $phoneNumber = $this->employeePhoneNumber($employee);
        $organizationLabel = $this->employeeOrganizationLabel($employee);

        return [
            'dbData' => $employee,
            'autoPrint' => $autoPrint,
            'publicProfileUrl' => $publicProfileUrl,
            'qrCodePng' => $qrCodePng,
            'profileImageUrl' => $this->employeeProfileImageUrl($employee),
            'employeeName' => $employeeName,
            'positionName' => $positionName,
            'employeeNumber' => $employeeNumber,
            'cardRows' => [
                [
                    'label' => localize('employee_id', 'អត្តលេខមន្ត្រី'),
                    'value' => $employeeNumber,
                ],
                [
                    'label' => localize('contact_no', 'លេខទំនាក់ទំនង'),
                    'value' => $phoneNumber,
                ],
                [
                    'label' => localize('qualification', 'គុណវុឌ្ឍិ'),
                    'value' => $qualificationLabel,
                ],
                [
                    'label' => localize('organization', 'អង្គភាព'),
                    'value' => $organizationLabel,
                ],
            ],
        ];
    }

    protected function prepareEmployeeForCard(Employee $employee): Employee
    {
        return $employee->loadMissing([
            'position',
            'department.unitType',
            'sub_department.unitType',
            'profileExtra',
            'educationHistories',
        ]);
    }

    protected function employeeProfileImageUrl(Employee $employee): string
    {
        if (!empty($employee->profile_img_location)) {
            return asset('storage/' . ltrim((string) $employee->profile_img_location, '/'));
        }

        return asset('backend/assets/dist/img/avatar-1.jpg');
    }

    protected function employeeDisplayName(Employee $employee): string
    {
        $fullName = trim((string) ($employee->full_name ?? ''));
        if ($fullName === '') {
            $fullName = trim((string) (($employee->last_name ?? '') . ' ' . ($employee->first_name ?? '')));
        }

        return $fullName !== '' ? $fullName : '-';
    }

    protected function employeePositionLabel(Employee $employee): string
    {
        return trim((string) ($employee->position?->position_name_km ?? $employee->position?->position_name ?? ''))
            ?: localize('not_available', 'Not available');
    }

    protected function employeeOrganizationLabel(Employee $employee): string
    {
        $unit = $employee->sub_department ?: $employee->department;
        if (!$unit) {
            return '-';
        }

        $allowedTypeCodes = [
            'office',
            'bureau',
            'program',
            'phd',
            'operational_district',
            'district_hospital',
            'provincial_hospital',
            'health_center',
            'health_center_with_bed',
            'health_center_without_bed',
            'health_post',
        ];

        $cursor = $this->departmentForCard((int) $unit->id);
        $visited = [];
        $fallbackName = trim((string) ($unit->department_name ?? ''));

        while ($cursor) {
            $cursorId = (int) ($cursor->id ?? 0);
            if ($cursorId <= 0 || isset($visited[$cursorId])) {
                break;
            }

            $visited[$cursorId] = true;
            $typeCode = strtolower(trim((string) optional($cursor->unitType)->code));
            $name = trim((string) ($cursor->department_name ?? ''));

            if ($name !== '' && in_array($typeCode, $allowedTypeCodes, true)) {
                return $name;
            }

            $parentId = (int) ($cursor->parent_id ?? 0);
            if ($parentId <= 0) {
                break;
            }

            $cursor = $this->departmentForCard($parentId);
        }

        return $fallbackName !== '' ? $fallbackName : '-';
    }

    protected function employeeBadgeNumber(Employee $employee): string
    {
        return trim((string) ($employee->official_id_10 ?? $employee->employee_id ?? $employee->employee_code ?? ''))
            ?: localize('employee', 'Employee');
    }

    protected function employeePhoneNumber(Employee $employee): string
    {
        return trim((string) ($employee->phone ?? $employee->cell_phone ?? $employee->business_phone ?? ''))
            ?: '-';
    }

    protected function employeeQualificationLabel(Employee $employee): string
    {
        $highestQualification = trim((string) ($employee->highest_educational_qualification ?? ''));
        $skill = trim((string) ($employee->skill_name ?: $employee->profileExtra?->current_work_skill ?: ''));

        $latestEducation = $employee->educationHistories
            ->sortBy(function ($row) {
                return sprintf(
                    '%s|%s|%s',
                    (string) ($row->end_date ?? ''),
                    (string) ($row->start_date ?? ''),
                    str_pad((string) ($row->id ?? 0), 10, '0', STR_PAD_LEFT)
                );
            })
            ->last();

        $majorSubject = trim((string) ($latestEducation->major_subject ?? ''));
        $degreeLevel = trim((string) ($latestEducation->degree_level ?? ''));

        foreach ([$highestQualification, $majorSubject, $degreeLevel, $skill] as $value) {
            if ($value !== '') {
                return $value;
            }
        }

        return '-';
    }

    protected function departmentForCard(int $departmentId): ?Department
    {
        static $cache = [];

        if ($departmentId <= 0) {
            return null;
        }

        if (array_key_exists($departmentId, $cache)) {
            return $cache[$departmentId];
        }

        $cache[$departmentId] = Department::withoutGlobalScopes()
            ->with('unitType:id,code,name,name_km')
            ->select(['id', 'department_name', 'parent_id', 'unit_type_id'])
            ->find($departmentId);

        return $cache[$departmentId];
    }
}
