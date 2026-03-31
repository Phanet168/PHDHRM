<?php

namespace App\Services\Security;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LoginOtpService
{
    public function issue(User $user, bool $force = false): array
    {
        $payload = $this->getPayload($user);
        $now = now();

        if (
            !$force &&
            $payload &&
            !empty($payload['expires_at']) &&
            Carbon::parse((string) $payload['expires_at'])->isFuture()
        ) {
            return [
                'ok' => true,
                'code' => null,
                'cooldown_seconds' => max(0, $this->cooldownSecondsLeft($payload)),
            ];
        }

        if (!$force && $payload && $this->cooldownSecondsLeft($payload) > 0) {
            return [
                'ok' => false,
                'message' => localize('otp_resend_wait', 'Please wait before requesting another code.'),
                'cooldown_seconds' => $this->cooldownSecondsLeft($payload),
            ];
        }

        $code = (string) random_int(100000, 999999);
        $ttlMinutes = (int) config('security.otp.ttl_minutes', 5);
        $cooldownSeconds = (int) config('security.otp.cooldown_seconds', 45);
        $newPayload = [
            'code_hash' => Hash::make($code),
            'expires_at' => $now->copy()->addMinutes($ttlMinutes)->toIso8601String(),
            'attempts' => 0,
            'resent_after' => $now->copy()->addSeconds($cooldownSeconds)->toIso8601String(),
            'phone' => (string) $user->contact_no,
        ];

        if (!$this->sendCode($user, $code)) {
            return [
                'ok' => false,
                'message' => localize('otp_send_failed', 'Unable to send OTP code. Please contact administrator.'),
                'cooldown_seconds' => 0,
            ];
        }

        Cache::put($this->cacheKey($user->id), $newPayload, now()->addMinutes($ttlMinutes + 2));

        return [
            'ok' => true,
            'code' => $code,
            'cooldown_seconds' => $cooldownSeconds,
        ];
    }

    public function verify(User $user, string $code): array
    {
        $payload = $this->getPayload($user);
        if (!$payload) {
            return ['ok' => false, 'message' => localize('otp_not_found', 'OTP code not found. Please request a new code.')];
        }

        if (empty($payload['expires_at']) || Carbon::parse((string) $payload['expires_at'])->isPast()) {
            Cache::forget($this->cacheKey($user->id));
            return ['ok' => false, 'message' => localize('otp_expired', 'OTP code has expired. Please request a new code.')];
        }

        $attempts = (int) ($payload['attempts'] ?? 0);
        if ($attempts >= (int) config('security.otp.max_attempts', 5)) {
            Cache::forget($this->cacheKey($user->id));
            return ['ok' => false, 'message' => localize('otp_too_many_attempts', 'Too many failed attempts. Please request a new code.')];
        }

        if (!Hash::check($code, (string) ($payload['code_hash'] ?? ''))) {
            $payload['attempts'] = $attempts + 1;
            Cache::put($this->cacheKey($user->id), $payload, now()->addMinutes(2));

            return ['ok' => false, 'message' => localize('otp_invalid', 'Invalid OTP code.')];
        }

        Cache::forget($this->cacheKey($user->id));

        return ['ok' => true];
    }

    public function hasActiveChallenge(User $user): bool
    {
        $payload = $this->getPayload($user);
        if (!$payload || empty($payload['expires_at'])) {
            return false;
        }

        return Carbon::parse((string) $payload['expires_at'])->isFuture();
    }

    public function maskedPhone(?string $phone): string
    {
        $raw = trim((string) $phone);
        if ($raw === '') {
            return '***';
        }

        $length = mb_strlen($raw);
        if ($length <= 4) {
            return str_repeat('*', max(0, $length - 1)) . mb_substr($raw, -1);
        }

        return mb_substr($raw, 0, 3) . str_repeat('*', $length - 5) . mb_substr($raw, -2);
    }

    private function cooldownSecondsLeft(array $payload): int
    {
        $resentAfter = (string) ($payload['resent_after'] ?? '');
        if ($resentAfter === '') {
            return 0;
        }

        $seconds = now()->diffInSeconds(Carbon::parse($resentAfter), false);
        return max(0, (int) $seconds);
    }

    private function getPayload(User $user): ?array
    {
        $payload = Cache::get($this->cacheKey($user->id));
        return is_array($payload) ? $payload : null;
    }

    private function cacheKey(int $userId): string
    {
        return 'login_otp_user_' . $userId;
    }

    private function sendCode(User $user, string $code): bool
    {
        $channel = function_exists('otp_channel') ? otp_channel() : (string) config('security.otp.channel', 'log');

        if ($channel === 'log') {
            Log::info('Login OTP issued', [
                'channel' => 'log',
                'user_id' => $user->id,
                'phone' => (string) $user->contact_no,
                'otp_code' => $code,
            ]);
            return true;
        }

        if ($channel === 'sms_http') {
            $endpoint = trim((string) config('security.otp.sms_http.endpoint', ''));
            if ($endpoint === '') {
                Log::warning('OTP sms_http endpoint is not configured.');
                return false;
            }

            $token = trim((string) config('security.otp.sms_http.token', ''));
            $template = (string) config('security.otp.sms_http.template', 'Your OTP code is {code}');
            $message = str_replace('{code}', $code, $template);

            try {
                $request = Http::timeout((int) config('security.otp.sms_http.timeout_seconds', 10))
                    ->acceptJson();

                if ($token !== '') {
                    $request = $request->withToken($token);
                }

                $response = $request->post($endpoint, [
                    'to' => (string) $user->contact_no,
                    'message' => $message,
                ]);

                if ($response->successful()) {
                    return true;
                }

                Log::warning('OTP sms_http request failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            } catch (\Throwable $e) {
                Log::error('OTP sms_http exception', [
                    'message' => $e->getMessage(),
                ]);
            }

            return false;
        }

        if ($channel === 'telegram') {
            $botToken = trim((string) config('security.otp.telegram.bot_token', ''));
            $chatId = trim((string) ($user->telegram_chat_id ?? ''));

            if ($botToken === '' || $chatId === '') {
                Log::warning('OTP telegram channel missing config/user chat_id.', [
                    'has_bot_token' => $botToken !== '',
                    'has_chat_id' => $chatId !== '',
                    'user_id' => $user->id,
                ]);
                return false;
            }

            $template = (string) config('security.otp.telegram.template', 'Your OTP code is {code}');
            $message = str_replace('{code}', $code, $template);

            try {
                $response = Http::timeout((int) config('security.otp.telegram.timeout_seconds', 10))
                    ->acceptJson()
                    ->post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                        'chat_id' => $chatId,
                        'text' => $message,
                    ]);

                if ($response->successful() && (bool) data_get($response->json(), 'ok', false)) {
                    return true;
                }

                Log::warning('OTP telegram sendMessage failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'user_id' => $user->id,
                ]);
            } catch (\Throwable $e) {
                Log::error('OTP telegram exception', [
                    'message' => $e->getMessage(),
                    'user_id' => $user->id,
                ]);
            }

            return false;
        }

        Log::warning('Unsupported OTP channel configured', ['channel' => $channel]);
        return false;
    }
}
