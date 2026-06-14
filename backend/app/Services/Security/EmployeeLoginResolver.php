<?php

namespace App\Services\Security;

use App\Models\User;
use Modules\HumanResource\Entities\Employee;

class EmployeeLoginResolver
{
    public function normalizeLogin(?string $login): string
    {
        return trim((string) $login);
    }

    public function normalizeOfficialCode(?string $officialCode): string
    {
        return preg_replace('/\D+/', '', trim((string) $officialCode)) ?: '';
    }

    public function isOfficialCode(?string $login): bool
    {
        return strlen($this->normalizeOfficialCode($login)) === 10;
    }

    public function looksLikeEmail(?string $login): bool
    {
        $login = $this->normalizeLogin($login);
        return $login !== '' && filter_var($login, FILTER_VALIDATE_EMAIL) !== false;
    }

    public function resolveCredentialsLookup(?string $login): array
    {
        $login = $this->normalizeLogin($login);

        $user = $this->resolveUserByLogin($login);
        if ($user) {
            return ['id' => (int) $user->id];
        }

        if ($this->isOfficialCode($login)) {
            $officialCode = $this->normalizeOfficialCode($login);
            return ['user_name' => $officialCode];
        }

        if ($this->looksLikeEmail($login)) {
            return ['email' => mb_strtolower($login, 'UTF-8')];
        }

        return ['user_name' => mb_strtolower($login, 'UTF-8')];
    }

    public function resolveUserByLogin(?string $login): ?User
    {
        $login = $this->normalizeLogin($login);
        if ($login === '') {
            return null;
        }

        if ($this->isOfficialCode($login)) {
            return $this->resolveEmployeeUserByOfficialCode($login);
        }

        if ($this->looksLikeEmail($login)) {
            return $this->resolveEmployeeUserByEmail($login);
        }

        $normalizedUsername = mb_strtolower($login, 'UTF-8');

        return User::query()
            ->whereRaw('LOWER(user_name) = ?', [$normalizedUsername])
            ->first();
    }

    public function resolveEmployeeUserByOfficialCode(?string $officialCode): ?User
    {
        $officialCode = $this->normalizeOfficialCode($officialCode);
        if ($officialCode === '') {
            return null;
        }

        $user = User::query()
            ->where('user_name', $officialCode)
            ->whereHas('employee', function ($query) use ($officialCode) {
                $query->where('official_id_10', $officialCode);
            })
            ->first();

        if ($user) {
            return $user;
        }

        $employee = Employee::query()
            ->with('user')
            ->where('official_id_10', $officialCode)
            ->whereNotNull('user_id')
            ->orderBy('id')
            ->first();

        return $employee?->user;
    }

    public function resolveEmployeeUserByEmail(?string $email): ?User
    {
        $email = mb_strtolower($this->normalizeLogin($email), 'UTF-8');
        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return null;
        }

        $user = User::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->first();

        if ($user) {
            return $user;
        }

        $employee = Employee::query()
            ->with('user')
            ->whereRaw('LOWER(email) = ?', [$email])
            ->whereNotNull('user_id')
            ->orderBy('id')
            ->first();

        return $employee?->user;
    }
}
