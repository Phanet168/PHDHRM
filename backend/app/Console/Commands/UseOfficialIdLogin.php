<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Modules\HumanResource\Entities\Employee;

class UseOfficialIdLogin extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hr:use-official-id10-login
                            {--dry-run : Preview changes without writing}
                            {--reset-password : Reset password = new username}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Switch employee login usernames to official 10-digit code (fallback to employee ID).';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $resetPassword = (bool) $this->option('reset-password');

        $updatedUsername = 0;
        $updatedPassword = 0;
        $skipped = 0;
        $errors = 0;

        $this->info(($dryRun ? '[Dry run] ' : '') . 'Updating employee login usernames to official_id_10...');

        Employee::query()
            ->with('user')
            ->orderBy('id')
            ->chunkById(100, function ($employees) use (
                $dryRun,
                $resetPassword,
                &$updatedUsername,
                &$updatedPassword,
                &$skipped,
                &$errors
            ) {
                foreach ($employees as $employee) {
                    try {
                        $user = $employee->user;
                        if (!$user || (int) $user->user_type_id !== 2) {
                            $skipped++;
                            continue;
                        }

                        $base = trim((string) $employee->official_id_10);
                        if ($base === '') {
                            $base = trim((string) $employee->employee_id);
                        }
                        if ($base === '') {
                            $skipped++;
                            continue;
                        }

                        $desired = $this->uniqueUsername($base, (int) $user->id);

                        if ($desired !== (string) $user->user_name) {
                            if (!$dryRun) {
                                $user->user_name = $desired;
                                $user->save();
                            }
                            $updatedUsername++;
                        }

                        if ($resetPassword) {
                            if (!$dryRun) {
                                $user->password = Hash::make($desired);
                                $user->save();
                            }
                            $updatedPassword++;
                        }
                    } catch (\Throwable $e) {
                        $errors++;
                        $this->warn('Employee #' . $employee->id . ' failed: ' . $e->getMessage());
                    }
                }
            });

        $this->newLine();
        $this->line('Summary');
        $this->line('Username updated : ' . $updatedUsername);
        $this->line('Password updated : ' . $updatedPassword);
        $this->line('Skipped          : ' . $skipped);
        $this->line('Errors           : ' . $errors);

        if (!$dryRun && $resetPassword) {
            $this->warn('Password policy applied: password = username');
        }

        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }

    protected function uniqueUsername(string $base, int $ignoreUserId): string
    {
        $base = preg_replace('/\s+/u', '', trim($base)) ?: ('emp' . $ignoreUserId);
        $base = Str::lower($base);

        $candidate = $base;
        $i = 1;
        while (
            User::withTrashed()
                ->where('id', '!=', $ignoreUserId)
                ->where('user_name', $candidate)
                ->exists()
        ) {
            $candidate = $base . '_' . $i;
            $i++;
        }

        return $candidate;
    }
}

