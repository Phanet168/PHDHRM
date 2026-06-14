<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Security\EmployeeLoginResolver;
use App\Services\Security\TelegramLinkService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class TelegramLoginController extends Controller
{
    public function __construct(
        private readonly EmployeeLoginResolver $employeeLoginResolver,
        private readonly TelegramLinkService $telegramLinkService
    ) {
    }

    public function requestLink(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'official_id_10' => ['required', 'digits:10'],
        ], [
            'official_id_10.required' => localize('official_id_10_required', 'សូមបញ្ចូលលេខកូដ ១០ ខ្ទង់។'),
            'official_id_10.digits' => localize('official_id_10_digits', 'លេខកូដ ១០ ខ្ទង់ ត្រូវមាន ១០ ខ្ទង់ពេញ។'),
        ]);

        $officialCode = $this->employeeLoginResolver->normalizeOfficialCode($validated['official_id_10']);
        $user = $this->employeeLoginResolver->resolveEmployeeUserByOfficialCode($officialCode);

        if (!$user) {
            return back()
                ->withInput()
                ->withErrors([
                    'telegram' => localize('official_id_10_login_not_found', 'រកមិនឃើញគណនីមន្រ្តីសម្រាប់លេខកូដ ១០ ខ្ទង់នេះទេ។'),
                ]);
        }

        if (!$this->telegramConfigured()) {
            return back()
                ->withInput()
                ->withErrors([
                    'telegram' => localize('telegram_bot_not_configured', 'មិនទាន់បានកំណត់ Telegram Bot នៅឡើយទេ។'),
                ]);
        }

        if (trim((string) $user->telegram_chat_id) === '') {
            return back()
                ->withInput()
                ->with('telegram_login_state', $this->telegramLoginState($user, $officialCode));
        }

        $result = $this->sendLoginLink($user);
        if (!($result['ok'] ?? false)) {
            return back()
                ->withInput()
                ->withErrors([
                    'telegram' => (string) ($result['message'] ?? localize('please_try_again', 'សូមព្យាយាមម្តងទៀត។')),
                ]);
        }

        return back()
            ->withInput()
            ->with('telegram_login_notice', (string) ($result['message'] ?? localize('telegram_login_link_sent', 'បានផ្ញើតំណចូលប្រើទៅ Telegram រួចហើយ។')));
    }

    public function syncAndLogin(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'official_id_10' => ['required', 'digits:10'],
        ], [
            'official_id_10.required' => localize('official_id_10_required', 'សូមបញ្ចូលលេខកូដ ១០ ខ្ទង់។'),
            'official_id_10.digits' => localize('official_id_10_digits', 'លេខកូដ ១០ ខ្ទង់ ត្រូវមាន ១០ ខ្ទង់ពេញ។'),
        ]);

        $officialCode = $this->employeeLoginResolver->normalizeOfficialCode($validated['official_id_10']);
        $user = $this->employeeLoginResolver->resolveEmployeeUserByOfficialCode($officialCode);

        if (!$user) {
            return back()
                ->withInput()
                ->withErrors([
                    'telegram' => localize('official_id_10_login_not_found', 'រកមិនឃើញគណនីមន្រ្តីសម្រាប់លេខកូដ ១០ ខ្ទង់នេះទេ។'),
                ]);
        }

        if (!$this->telegramConfigured()) {
            return back()
                ->withInput()
                ->withErrors([
                    'telegram' => localize('telegram_bot_not_configured', 'មិនទាន់បានកំណត់ Telegram Bot នៅឡើយទេ។'),
                ]);
        }

        if (trim((string) $user->telegram_chat_id) === '') {
            $result = $this->telegramLinkService->syncFromTelegram($user);
            if (!($result['ok'] ?? false)) {
                return back()
                    ->withInput()
                    ->with('telegram_login_state', $this->telegramLoginState($user, $officialCode))
                    ->withErrors([
                        'telegram' => (string) ($result['message'] ?? localize('please_try_again', 'សូមព្យាយាមម្តងទៀត។')),
                    ]);
            }
        }

        return $this->loginUser($request, $user, true);
    }

    public function consume(Request $request, string $token): RedirectResponse
    {
        $payload = Cache::pull($this->telegramLoginCacheKey($token));
        if (!is_array($payload) || empty($payload['user_id'])) {
            return redirect()
                ->route('login')
                ->withErrors([
                    'telegram' => localize('telegram_login_link_invalid', 'តំណចូលប្រើពី Telegram មិនត្រឹមត្រូវ ឬផុតកំណត់ហើយ។'),
                ]);
        }

        $user = User::query()->find((int) $payload['user_id']);
        if (!$user) {
            return redirect()
                ->route('login')
                ->withErrors([
                    'telegram' => localize('telegram_login_link_invalid', 'តំណចូលប្រើពី Telegram មិនត្រឹមត្រូវ ឬផុតកំណត់ហើយ។'),
                ]);
        }

        return $this->loginUser($request, $user, true);
    }

    private function telegramConfigured(): bool
    {
        return trim((string) config('security.otp.telegram.bot_token', '')) !== '';
    }

    private function telegramLoginState(User $user, string $officialCode): array
    {
        return [
            'official_id_10' => $officialCode,
            'telegram_link' => $this->telegramLinkService->deepLink($user),
            'telegram_start_command' => $this->telegramLinkService->startCommand($user),
            'bot_username' => trim((string) config('security.otp.telegram.bot_username', '')),
            'full_name' => trim((string) $user->full_name),
        ];
    }

    private function sendLoginLink(User $user): array
    {
        $chatId = trim((string) $user->telegram_chat_id);
        $botToken = trim((string) config('security.otp.telegram.bot_token', ''));

        if ($chatId === '' || $botToken === '') {
            return [
                'ok' => false,
                'message' => localize('telegram_not_ready_for_login', 'Telegram មិនទាន់រួចរាល់សម្រាប់ការចូលប្រើនៅឡើយទេ។'),
            ];
        }

        $ttlMinutes = (int) config('security.otp.telegram.login_link_ttl_minutes', 10);
        $token = Str::random(64);
        Cache::put($this->telegramLoginCacheKey($token), [
            'user_id' => (int) $user->id,
        ], now()->addMinutes($ttlMinutes));

        $loginUrl = URL::temporarySignedRoute(
            'login.telegram.consume',
            now()->addMinutes($ttlMinutes),
            ['token' => $token]
        );

        $message = localize('telegram_login_message', 'សូមចុចតំណនេះ ដើម្បីចូលប្រើប្រព័ន្ធដោយសុវត្ថិភាព៖') . ' ' . $loginUrl;

        try {
            $response = Http::timeout((int) config('security.otp.telegram.timeout_seconds', 10))
                ->acceptJson()
                ->post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                    'chat_id' => $chatId,
                    'text' => $message,
                ]);

            if ($response->status() === 401) {
                Cache::forget($this->telegramLoginCacheKey($token));

                return [
                    'ok' => false,
                    'message' => localize('telegram_bot_token_invalid', 'Telegram Bot Token មិនត្រឹមត្រូវទេ។ សូមពិនិត្យ Bot Token ម្តងទៀត។'),
                ];
            }

            if ($response->successful() && (bool) data_get($response->json(), 'ok', false)) {
                return [
                    'ok' => true,
                    'message' => localize(
                        'telegram_login_link_sent',
                        'បានផ្ញើតំណចូលប្រើទៅ Telegram រួចហើយ។ សូមបើក Telegram ហើយចុចតំណចូលប្រើ។'
                    ),
                ];
            }

            Log::warning('Telegram quick login sendMessage failed', [
                'status' => $response->status(),
                'body' => $response->body(),
                'user_id' => $user->id,
            ]);
        } catch (\Throwable $e) {
            Log::error('Telegram quick login exception', [
                'message' => $e->getMessage(),
                'user_id' => $user->id,
            ]);
        }

        Cache::forget($this->telegramLoginCacheKey($token));

        return [
            'ok' => false,
            'message' => localize('telegram_login_link_send_failed', 'មិនអាចផ្ញើតំណចូលប្រើទៅ Telegram បាននៅពេលនេះទេ។'),
        ];
    }

    private function telegramLoginCacheKey(string $token): string
    {
        return 'telegram_login_token_' . $token;
    }

    private function loginUser(Request $request, User $user, bool $markOtpVerified = false): RedirectResponse
    {
        Auth::login($user);
        $request->session()->regenerate();

        if ($markOtpVerified) {
            $request->session()->put('otp_verified_user_id', (int) $user->id);
            $request->session()->put('otp_verified_at', now()->toIso8601String());
        }

        if ($user->admin() && $user->can('read_dashboard')) {
            return redirect()->route('home');
        }

        return redirect()->route('staffHome');
    }
}
