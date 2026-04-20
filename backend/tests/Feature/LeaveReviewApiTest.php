<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Modules\HumanResource\Entities\ApplyLeave;
use Modules\HumanResource\Entities\Employee;
use Modules\HumanResource\Entities\LeaveType;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class LeaveReviewApiTest extends TestCase
{
    /**
     * @var array<int, int>
     */
    private array $createdLeaveTypeIds = [];

    /**
     * @var array<int, int>
     */
    private array $createdLeaveIds = [];

    /**
     * @var array<int, array{user_id:int, permission:string}>
     */
    private array $grantedPermissions = [];

    protected function tearDown(): void
    {
        foreach ($this->createdLeaveIds as $leaveId) {
            $leave = ApplyLeave::withoutGlobalScopes()->find($leaveId);
            if ($leave) {
                $leave->forceDelete();
            }
        }

        foreach ($this->createdLeaveTypeIds as $leaveTypeId) {
            $leaveType = LeaveType::withoutGlobalScopes()->find($leaveTypeId);
            if ($leaveType) {
                $leaveType->forceDelete();
            }
        }

        foreach ($this->grantedPermissions as $grant) {
            $user = User::query()->find($grant['user_id']);
            if ($user && $user->hasDirectPermission($grant['permission'])) {
                $user->revokePermissionTo($grant['permission']);
            }
        }

        parent::tearDown();
    }

    public function test_auth_profile_exposes_leave_review_capability_flag(): void
    {
        $user = User::query()->first();
        if (!$user) {
            return;
        }

        $this->grantPermissionIfNeeded($user, 'create_leave_approval');

        Sanctum::actingAs($user);

        $this->getJson('/api/auth/profile')
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('user.can_review_leave_requests', true);
    }

    public function test_leave_review_api_approves_pending_leave_request(): void
    {
        $reviewer = User::query()->first();
        if (!$reviewer) {
            return;
        }

        $employee = Employee::query()->first();
        if (!$employee) {
            return;
        }

        $this->grantPermissionIfNeeded($reviewer, 'create_leave_approval');

        $suffix = now()->format('YmdHis') . '-' . Str::lower(Str::random(6));

        $leaveType = LeaveType::query()->create([
            'uuid' => (string) Str::uuid(),
            'leave_type' => 'Feature Test Leave ' . $suffix,
            'leave_days' => 5,
            'leave_code' => 'FT-' . Str::upper(Str::random(4)),
        ]);
        $this->createdLeaveTypeIds[] = (int) $leaveType->id;

        $leave = ApplyLeave::query()->create([
            'uuid' => (string) Str::uuid(),
            'employee_id' => (int) $employee->id,
            'leave_type_id' => (int) $leaveType->id,
            'leave_apply_start_date' => now()->toDateString(),
            'leave_apply_end_date' => now()->toDateString(),
            'leave_apply_date' => now()->toDateString(),
            'total_apply_day' => 1,
            'reason' => 'Feature test leave request',
            'is_approved_by_manager' => 0,
            'is_approved' => 0,
            'workflow_status' => 'pending',
        ]);
        $this->createdLeaveIds[] = (int) $leave->id;

        Sanctum::actingAs($reviewer);

        $this->getJson('/api/v1/leave-requests/pending-review')
            ->assertOk()
            ->assertJsonPath('response.status', 'ok');

        $this->postJson('/api/v1/leave-requests/' . $leave->id . '/review', [
            'action' => 'approve',
            'note' => 'Approved from automated feature test',
        ])
            ->assertOk()
            ->assertJsonPath('response.status', 'ok')
            ->assertJsonPath('response.data.status', 'approved');

        $leave->refresh();

        if ((string) $leave->workflow_status !== 'approved') {
            throw new \RuntimeException('Expected approved workflow status after review API approval.');
        }
        if ((int) $leave->is_approved !== 1) {
            throw new \RuntimeException('Expected leave request to be marked approved.');
        }
        if ((int) $leave->is_approved_by_manager !== 1) {
            throw new \RuntimeException('Expected leave request to be marked approved by manager.');
        }
        if ((int) $leave->approved_by !== (int) $reviewer->id) {
            throw new \RuntimeException('Expected approved_by to match reviewer user ID.');
        }
        if ((int) $leave->approved_by_manager !== (int) $reviewer->id) {
            throw new \RuntimeException('Expected approved_by_manager to match reviewer user ID.');
        }
        if ((int) $leave->total_approved_day !== 1) {
            throw new \RuntimeException('Expected total approved day to equal requested day count.');
        }
    }

    private function grantPermissionIfNeeded(User $user, string $permissionName): void
    {
        Permission::findOrCreate($permissionName, 'web');

        if ($user->can($permissionName)) {
            return;
        }

        $user->givePermissionTo($permissionName);
        $this->grantedPermissions[] = [
            'user_id' => (int) $user->id,
            'permission' => $permissionName,
        ];
    }
}