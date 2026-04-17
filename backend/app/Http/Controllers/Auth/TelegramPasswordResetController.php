<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;

class TelegramPasswordResetController extends Controller
{
    public function send(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'login' => ['required', 'string', 'max:191'],
        ], [
            'login.required' => localize('email_or_username_required', 'Email or username is required.'),
        ]);

        $login = trim((string) $validated['login']);

        $user = User::query()
            ->where(function ($q) use ($login) {
                $q->where('email', $login)
                    ->orWhere('user_name', $login);
            })
            ->first();

        // Security: do not disclose if user exists or not.
        $genericMessage = localize(
            'password_reset_telegram_sent_if_available',
            'If account is linked with Telegram, reset instructions have been sent.'
        );

        if (!$user) {
            return back()->with('status', $genericMessage);
        }

        $chatId = trim((string) $user->telegram_chat_id);
        $email = trim((string) $user->email);
        $botToken = trim((string) config('security.otp.telegram.bot_token', ''));

        if ($chatId === '' || $email === '' || $botToken === '') {
            return back()->with('status', $genericMessage);
        }

        try {
            $token = Password::broker()->createToken($user);
            $resetUrl = route('password.reset', [
                'token' => $token,
                'email' => $email,
            ]);

            $text = localize('telegram_password_reset_text', 'Reset your password here:') . ' ' . $resetUrl;

            $response = Http::timeout((int) config('security.otp.telegram.timeout_seconds', 10))
                ->acceptJson()
                ->post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                    'chat_id' => $chatId,
                    'text' => $text,
                ]);

            if (!$response->successful() || !(bool) data_get($response->json(), 'ok', false)) {
                Log::warning('Telegram password reset send failed', [
                    'user_id' => $user->id,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('Telegram password reset exception', [
                'user_id' => $user->id,
                'message' => $e->getMessage(),
            ]);
        }

        return back()->with('status', $genericMessage);
    }
}

