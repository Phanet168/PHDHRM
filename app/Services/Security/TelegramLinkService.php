<?php

namespace App\Services\Security;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TelegramLinkService
{
    public function ensureLinkToken(User $user): string
    {
        $token = trim((string) $user->telegram_link_token);
        if ($token !== '') {
            return $token;
        }

        $token = Str::random(40);
        $user->telegram_link_token = $token;
        $user->save();

        return $token;
    }

    public function regenerateLinkToken(User $user): string
    {
        $token = Str::random(40);
        $user->telegram_link_token = $token;
        $user->save();

        return $token;
    }

    public function deepLink(User $user): ?string
    {
        $botUsername = trim((string) config('security.otp.telegram.bot_username', ''));
        if ($botUsername === '') {
            return null;
        }

        $token = $this->ensureLinkToken($user);
        return sprintf('https://t.me/%s?start=link_%s', ltrim($botUsername, '@'), $token);
    }

    public function syncFromTelegram(User $user): array
    {
        $botToken = trim((string) config('security.otp.telegram.bot_token', ''));
        if ($botToken === '') {
            return [
                'ok' => false,
                'message' => localize('telegram_bot_not_configured', 'Telegram bot is not configured yet.'),
            ];
        }

        $linkToken = trim((string) $user->telegram_link_token);
        if ($linkToken === '') {
            return [
                'ok' => false,
                'message' => localize('telegram_link_token_missing', 'Please generate Telegram connect link first.'),
            ];
        }

        try {
            $response = Http::timeout((int) config('security.otp.telegram.timeout_seconds', 10))
                ->acceptJson()
                ->get("https://api.telegram.org/bot{$botToken}/getUpdates", [
                    'limit' => (int) config('security.otp.telegram.get_updates_limit', 100),
                ]);

            if (!$response->successful()) {
                if ($response->status() === 409) {
                    return [
                        'ok' => false,
                        'message' => localize('telegram_webhook_conflict', 'Telegram bot has an active webhook. Please disable webhook before using Sync.'),
                    ];
                }

                Log::warning('Telegram getUpdates failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return [
                    'ok' => false,
                    'message' => localize('telegram_fetch_failed', 'Cannot fetch Telegram updates right now.'),
                ];
            }

            $payload = $response->json();
            if (!is_array($payload) || !($payload['ok'] ?? false)) {
                return [
                    'ok' => false,
                    'message' => localize('telegram_invalid_response', 'Invalid response from Telegram API.'),
                ];
            }

            $targetStart = 'link_' . $linkToken;
            $updates = (array) ($payload['result'] ?? []);

            foreach (array_reverse($updates) as $update) {
                if (!is_array($update)) {
                    continue;
                }

                $message = $update['message'] ?? $update['edited_message'] ?? null;
                if (!is_array($message)) {
                    continue;
                }

                $chat = $message['chat'] ?? [];
                if (!is_array($chat) || (($chat['type'] ?? '') !== 'private')) {
                    continue;
                }

                $text = trim((string) ($message['text'] ?? ''));
                if ($text === '') {
                    continue;
                }

                if (!$this->isTargetStartCommand($text, $targetStart)) {
                    continue;
                }

                $chatId = trim((string) ($chat['id'] ?? ''));
                if ($chatId === '') {
                    continue;
                }

                $duplicate = User::query()
                    ->where('id', '!=', $user->id)
                    ->where('telegram_chat_id', $chatId)
                    ->exists();

                if ($duplicate) {
                    return [
                        'ok' => false,
                        'message' => localize('telegram_chat_already_used', 'This Telegram account is already linked to another user.'),
                    ];
                }

                $user->telegram_chat_id = $chatId;
                $user->telegram_linked_at = now();
                $user->telegram_link_token = null;
                $user->save();

                $this->sendBotMessage(
                    $chatId,
                    (string) localize('telegram_link_success_message', 'Telegram connected successfully. You can now receive OTP codes here.')
                );

                return [
                    'ok' => true,
                    'chat_id' => $chatId,
                ];
            }
        } catch (\Throwable $e) {
            Log::error('Telegram sync exception', [
                'message' => $e->getMessage(),
            ]);
        }

        return [
            'ok' => false,
            'message' => localize('telegram_start_not_found', 'Not found yet. Please open bot and press Start, then sync again.'),
        ];
    }

    private function sendBotMessage(string $chatId, string $text): void
    {
        $botToken = trim((string) config('security.otp.telegram.bot_token', ''));
        if ($botToken === '' || trim($chatId) === '' || trim($text) === '') {
            return;
        }

        try {
            Http::timeout((int) config('security.otp.telegram.timeout_seconds', 10))
                ->acceptJson()
                ->post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                    'chat_id' => $chatId,
                    'text' => $text,
                ]);
        } catch (\Throwable $e) {
            Log::warning('Telegram sendBotMessage failed', [
                'message' => $e->getMessage(),
            ]);
        }
    }

    private function isTargetStartCommand(string $text, string $targetStart): bool
    {
        // Examples:
        // /start link_xxx
        // /start@mybot link_xxx
        $parts = preg_split('/\s+/', $text);
        $first = strtolower((string) ($parts[0] ?? ''));
        $second = (string) ($parts[1] ?? '');

        if (!Str::startsWith($first, '/start')) {
            return false;
        }

        return hash_equals($targetStart, $second);
    }
}
