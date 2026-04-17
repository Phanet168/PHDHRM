<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\HumanResource\Entities\Employee;

class SyncUserPhonesFromEmployees extends Command
{
    protected $signature = 'hr:sync-user-phones {--dry-run : Preview only}';

    protected $description = 'Fill users.contact_no from linked employee phone when possible.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $updated = 0;
        $skippedHasUserPhone = 0;
        $skippedNoEmployeePhone = 0;
        $skippedNoLinkedUser = 0;
        $skippedDuplicate = 0;
        $errors = 0;

        $this->info(($dryRun ? '[Dry run] ' : '') . 'Syncing user phone numbers from employees...');

        Employee::query()
            ->whereNotNull('user_id')
            ->orderBy('id')
            ->chunkById(100, function ($employees) use (
                $dryRun,
                &$updated,
                &$skippedHasUserPhone,
                &$skippedNoEmployeePhone,
                &$skippedNoLinkedUser,
                &$skippedDuplicate,
                &$errors
            ) {
                foreach ($employees as $employee) {
                    try {
                        $user = User::withTrashed()->find((int) $employee->user_id);
                        if (!$user) {
                            $skippedNoLinkedUser++;
                            continue;
                        }

                        if (trim((string) $user->contact_no) !== '') {
                            $skippedHasUserPhone++;
                            continue;
                        }

                        $phone = trim((string) $employee->phone);
                        if ($phone === '') {
                            $skippedNoEmployeePhone++;
                            continue;
                        }

                        $alreadyUsed = User::withTrashed()
                            ->where('contact_no', $phone)
                            ->where('id', '!=', $user->id)
                            ->exists();

                        if ($alreadyUsed) {
                            $skippedDuplicate++;
                            continue;
                        }

                        if (!$dryRun) {
                            DB::transaction(function () use ($user, $phone) {
                                $user->contact_no = $phone;
                                $user->save();
                            });
                        }

                        $updated++;
                    } catch (\Throwable $e) {
                        $errors++;
                        $this->warn('Employee #' . $employee->id . ' failed: ' . $e->getMessage());
                    }
                }
            });

        $this->newLine();
        $this->line('Summary');
        $this->line('Updated users                    : ' . $updated);
        $this->line('Skipped (user already has phone) : ' . $skippedHasUserPhone);
        $this->line('Skipped (employee phone empty)   : ' . $skippedNoEmployeePhone);
        $this->line('Skipped (linked user missing)    : ' . $skippedNoLinkedUser);
        $this->line('Skipped (duplicate phone)        : ' . $skippedDuplicate);
        $this->line('Errors                           : ' . $errors);

        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }
}

