<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Modules\HumanResource\Entities\Department;
use Modules\HumanResource\Entities\UserOrgRole;

class AssignOrgRole extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hr:assign-org-role
                            {user : User ID or email}
                            {department : Department ID}
                            {role : head|deputy_head|manager}
                            {--scope=self_and_children : self|self_and_children}
                            {--from= : Effective from date (Y-m-d)}
                            {--to= : Effective to date (Y-m-d)}
                            {--inactive : Create as inactive assignment}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Assign organizational role (head/deputy/manager) to a user for a department scope.';

    public function handle(): int
    {
        $userInput = trim((string) $this->argument('user'));
        $departmentId = (int) $this->argument('department');
        $role = trim((string) $this->argument('role'));
        $scopeType = trim((string) $this->option('scope'));
        $fromInput = trim((string) ($this->option('from') ?? ''));
        $toInput = trim((string) ($this->option('to') ?? ''));

        if (!in_array($role, UserOrgRole::roleOptions(), true)) {
            $this->error('Invalid role. Allowed: ' . implode(', ', UserOrgRole::roleOptions()));
            return self::FAILURE;
        }

        if (!in_array($scopeType, UserOrgRole::scopeOptions(), true)) {
            $this->error('Invalid scope. Allowed: ' . implode(', ', UserOrgRole::scopeOptions()));
            return self::FAILURE;
        }

        $user = $this->findUser($userInput);
        if (!$user) {
            $this->error('User not found by ID/email: ' . $userInput);
            return self::FAILURE;
        }

        $department = Department::withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->find($departmentId);
        if (!$department) {
            $this->error('Department not found: ' . $departmentId);
            return self::FAILURE;
        }

        $effectiveFrom = null;
        $effectiveTo = null;

        try {
            if ($fromInput !== '') {
                $effectiveFrom = Carbon::parse($fromInput)->toDateString();
            }
            if ($toInput !== '') {
                $effectiveTo = Carbon::parse($toInput)->toDateString();
            }
        } catch (\Throwable $throwable) {
            $this->error('Invalid date format in --from/--to. Expected Y-m-d');
            return self::FAILURE;
        }

        if ($effectiveFrom && $effectiveTo && $effectiveFrom > $effectiveTo) {
            $this->error('Invalid date range: --from must be <= --to');
            return self::FAILURE;
        }

        $assignment = UserOrgRole::query()
            ->withoutGlobalScope('sortByLatest')
            ->where('user_id', (int) $user->id)
            ->where('department_id', (int) $department->id)
            ->where('org_role', $role)
            ->first();

        $payload = [
            'scope_type' => $scopeType,
            'effective_from' => $effectiveFrom,
            'effective_to' => $effectiveTo,
            'is_active' => !$this->option('inactive'),
        ];

        if ($assignment) {
            $assignment->fill($payload)->save();
            $this->info('Updated existing assignment #' . $assignment->id);
        } else {
            $assignment = UserOrgRole::create(array_merge($payload, [
                'user_id' => (int) $user->id,
                'department_id' => (int) $department->id,
                'org_role' => $role,
            ]));
            $this->info('Created assignment #' . $assignment->id);
        }

        $this->line('User       : ' . $user->id . ' | ' . ($user->full_name ?: $user->email));
        $this->line('Department : ' . $department->id . ' | ' . $department->department_name);
        $this->line('Role       : ' . $role);
        $this->line('Scope      : ' . $scopeType);
        $this->line('Active     : ' . ($assignment->is_active ? 'yes' : 'no'));
        $this->line('From/To    : ' . ($assignment->effective_from ? $assignment->effective_from->toDateString() : '-') . ' / ' . ($assignment->effective_to ? $assignment->effective_to->toDateString() : '-'));

        return self::SUCCESS;
    }

    protected function findUser(string $input): ?User
    {
        if ($input === '') {
            return null;
        }

        if (ctype_digit($input)) {
            return User::query()->find((int) $input);
        }

        return User::query()->where('email', $input)->first();
    }
}

