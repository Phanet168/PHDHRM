<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Modules\HumanResource\Entities\Employee;
use Spatie\Permission\Models\Role;

class ProvisionEmployeeUsers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hr:provision-employee-users {--dry-run : Preview changes without writing}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create/link dedicated user accounts for employees (prefer official 10-digit code as login username).';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $employeeRole = Role::query()
            ->where('name', 'Employee')
            ->first()
            ?: Role::query()->find(2);

        if (!$employeeRole) {
            $this->error('Employee role not found (expected role name "Employee" or id 2).');
            return self::FAILURE;
        }

        $usageByUser = Employee::query()
            ->whereNotNull('user_id')
            ->selectRaw('user_id, COUNT(*) as total')
            ->groupBy('user_id')
            ->pluck('total', 'user_id');

        $created = 0;
        $relinked = 0;
        $kept = 0;
        $renamed = 0;
        $passwordReset = 0;
        $errors = 0;

        $this->info(($dryRun ? '[Dry run] ' : '') . 'Provisioning employee user accounts...');

        Employee::query()
            ->orderBy('id')
            ->chunkById(100, function ($employees) use (
                $dryRun,
                $employeeRole,
                $usageByUser,
                &$created,
                &$relinked,
                &$kept,
                &$renamed,
                &$passwordReset,
                &$errors
            ) {
                foreach ($employees as $employee) {
                    try {
                        $linkedUser = $employee->user()->withTrashed()->first();
                        $isSharedUser = $linkedUser && ((int) ($usageByUser[$linkedUser->id] ?? 0) > 1);
                        $isAdminUser = $linkedUser && (int) $linkedUser->user_type_id === 1;

                        $needsDedicatedUser = !$linkedUser || $isSharedUser || $isAdminUser;

                        if ($needsDedicatedUser) {
                            $username = $this->generateUniqueUsername($employee);
                            $email = $this->resolveAvailableEmail((string) $employee->email);
                            $contactNo = $this->resolveAvailableContact((string) $employee->phone);
                            $fullName = trim((string) $employee->full_name) !== ''
                                ? trim((string) $employee->full_name)
                                : ('Employee ' . $employee->id);

                            if (!$dryRun) {
                                $user = new User();
                                $user->user_type_id = 2;
                                $user->full_name = $fullName;
                                $user->user_name = $username;
                                $user->email = $email;
                                $user->contact_no = $contactNo;
                                $user->password = Hash::make($username);
                                $user->is_active = 1;
                                $user->save();
                                $user->syncRoles([$employeeRole->id]);

                                $employee->user_id = $user->id;
                                $employee->save();
                            }

                            $created++;
                            $relinked++;
                            continue;
                        }

                        // Existing dedicated user: ensure employee role exists.
                        if ($linkedUser) {
                            $changed = false;
                            $preferredUsername = $this->generateUniqueUsername($employee, $linkedUser->id);

                            if ($linkedUser->user_name !== $preferredUsername) {
                                if (!$dryRun) {
                                    $linkedUser->user_name = $preferredUsername;
                                    $linkedUser->password = Hash::make($preferredUsername);
                                    $linkedUser->save();
                                }

                                $renamed++;
                                $passwordReset++;
                                $changed = true;
                            }

                            if (!$dryRun && !$linkedUser->hasRole($employeeRole->name)) {
                                $linkedUser->assignRole($employeeRole->id);
                            }

                            if (!$changed) {
                                $kept++;
                            }
                        }
                    } catch (\Throwable $e) {
                        $errors++;
                        $this->warn('Employee #' . $employee->id . ' failed: ' . $e->getMessage());
                    }
                }
            });

        $this->newLine();
        $this->line('Summary');
        $this->line('Created users   : ' . $created);
        $this->line('Re-linked       : ' . $relinked);
        $this->line('Renamed login   : ' . $renamed);
        $this->line('Password reset  : ' . $passwordReset);
        $this->line('Kept as-is      : ' . $kept);
        $this->line('Errors          : ' . $errors);

        if (!$dryRun) {
            $this->newLine();
            $this->warn('Default login for newly created employees:');
            $this->warn('Username = Official 10-digit code (or fallback), Password = same as username');
        }

        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }

    protected function generateUniqueUsername(Employee $employee, ?int $ignoreUserId = null): string
    {
        $officialId10 = preg_replace('/\D+/', '', (string) $employee->official_id_10) ?? '';
        $officialId10 = trim($officialId10);

        $base = '';
        if ($officialId10 !== '' && strlen($officialId10) === 10) {
            $base = $officialId10;
        }

        if ($base === '') {
            $base = trim((string) $employee->employee_id);
        }
        if ($base === '') {
            $base = 'emp' . $employee->id;
        }

        $base = preg_replace('/\s+/u', '', $base) ?: ('emp' . $employee->id);
        $base = Str::lower($base);

        $candidate = $base;
        $i = 1;
        while (
            User::withTrashed()
                ->where('user_name', $candidate)
                ->when($ignoreUserId, function ($query) use ($ignoreUserId) {
                    $query->where('id', '!=', $ignoreUserId);
                })
                ->exists()
        ) {
            $candidate = $base . '_' . $i;
            $i++;
        }

        return $candidate;
    }

    protected function resolveAvailableEmail(string $email): ?string
    {
        $email = trim($email);
        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return null;
        }

        $email = Str::lower($email);
        if (User::withTrashed()->where('email', $email)->exists()) {
            return null;
        }

        return $email;
    }

    protected function resolveAvailableContact(string $contact): ?string
    {
        $contact = trim($contact);
        if ($contact === '') {
            return null;
        }

        if (User::withTrashed()->where('contact_no', $contact)->exists()) {
            return null;
        }

        return $contact;
    }
}
