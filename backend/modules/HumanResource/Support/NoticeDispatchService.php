<?php

namespace Modules\HumanResource\Support;

use App\Models\User;
use App\Notifications\NoticeBroadcastNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\HumanResource\Entities\Employee;
use Modules\HumanResource\Entities\Notice;
use Modules\HumanResource\Entities\NoticeDelivery;

class NoticeDispatchService
{
    public function __construct(private readonly OrgUnitRuleService $orgUnitRuleService)
    {
    }

    public function resolveRecipients(Notice $notice): Collection
    {
        $audienceType = (string) ($notice->audience_type ?: Notice::AUDIENCE_ALL);
        $targets = (array) ($notice->audience_targets ?? []);

        $query = User::query()->select(['id', 'full_name', 'email', 'telegram_chat_id']);

        if ($audienceType === Notice::AUDIENCE_USERS) {
            $userIds = array_values(array_filter(array_map('intval', (array) data_get($targets, 'users', []))));
            if (empty($userIds)) {
                return collect();
            }

            return $query->whereIn('id', $userIds)->get();
        }

        if ($audienceType === Notice::AUDIENCE_ROLES) {
            $roleIds = array_values(array_filter(array_map('intval', (array) data_get($targets, 'roles', []))));
            if (empty($roleIds)) {
                return collect();
            }

            return $query->whereHas('roles', function ($q) use ($roleIds) {
                $q->whereIn('roles.id', $roleIds);
            })->get();
        }

        if ($audienceType === Notice::AUDIENCE_DEPARTMENTS) {
            $departmentIds = array_values(array_filter(array_map('intval', (array) data_get($targets, 'departments', []))));
            if (empty($departmentIds)) {
                return collect();
            }

            $branchIds = [];
            foreach ($departmentIds as $departmentId) {
                $branchIds = array_merge($branchIds, $this->orgUnitRuleService->branchIdsIncludingSelf($departmentId));
            }
            $branchIds = array_values(array_unique(array_map('intval', $branchIds)));

            if (empty($branchIds)) {
                return collect();
            }

            $employeeUserIds = Employee::query()
                ->where(function ($q) use ($branchIds) {
                    $q->whereIn('department_id', $branchIds)
                        ->orWhereIn('sub_department_id', $branchIds);
                })
                ->whereNotNull('user_id')
                ->pluck('user_id')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
                ->all();

            if (empty($employeeUserIds)) {
                return collect();
            }

            return $query->whereIn('id', $employeeUserIds)->get();
        }

        return $query->get();
    }

    public function deliver(Notice $notice): array
    {
        $channels = $notice->normalized_channels;
        $users = $this->resolveRecipients($notice);

        if ($users->isEmpty()) {
            return [
                'total' => 0,
                'success' => 0,
                'failed' => 0,
                'last_error' => null,
            ];
        }

        $success = 0;
        $failed = 0;
        $lastError = null;

        foreach ($users as $user) {
            $userSuccessful = false;
            $userLastError = null;

            foreach ($channels as $channel) {
                $result = $this->deliverByChannel($notice, $user, $channel);

                NoticeDelivery::updateOrCreate(
                    [
                        'notice_id' => (int) $notice->id,
                        'user_id' => (int) $user->id,
                        'channel' => (string) $channel,
                    ],
                    [
                        'status' => $result['status'],
                        'error_message' => $result['error'] ?? null,
                        'payload' => $result['payload'] ?? null,
                        'sent_at' => $result['status'] === 'sent' ? now() : null,
                    ]
                );

                if ($result['status'] === 'sent') {
                    $userSuccessful = true;
                } else {
                    $userLastError = $result['error'] ?? $userLastError;
                }
            }

            if ($userSuccessful) {
                $success++;
            } else {
                $failed++;
                $lastError = $userLastError;
            }
        }

        return [
            'total' => $users->count(),
            'success' => $success,
            'failed' => $failed,
            'last_error' => $lastError,
        ];
    }

    private function deliverByChannel(Notice $notice, User $user, string $channel): array
    {
        if ($channel === 'in_app') {
            try {
                $user->notify(new NoticeBroadcastNotification($notice));

                return [
                    'status' => 'sent',
                    'payload' => ['channel' => 'database'],
                ];
            } catch (\Throwable $throwable) {
                Log::warning('Notice in-app notification failed', [
                    'notice_id' => $notice->id,
                    'user_id' => $user->id,
                    'error' => $throwable->getMessage(),
                ]);

                return [
                    'status' => 'failed',
                    'error' => $throwable->getMessage(),
                ];
            }
        }

        if ($channel === 'telegram') {
            return $this->sendTelegram($notice, $user);
        }

        return [
            'status' => 'failed',
            'error' => 'Unsupported channel: ' . $channel,
        ];
    }

    private function sendTelegram(Notice $notice, User $user): array
    {
        $botToken = trim((string) config('security.otp.telegram.bot_token', ''));
        $chatId = trim((string) ($user->telegram_chat_id ?? ''));

        if ($botToken === '') {
            return [
                'status' => 'failed',
                'error' => localize('telegram_bot_not_configured', 'Telegram bot is not configured.'),
            ];
        }

        if ($chatId === '') {
            return [
                'status' => 'failed',
                'error' => localize('telegram_not_linked', 'User has not linked Telegram yet.'),
            ];
        }

        $text = $this->buildTelegramText($notice);

        try {
            $response = Http::timeout((int) config('security.otp.telegram.timeout_seconds', 10))
                ->asForm()
                ->post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                    'chat_id' => $chatId,
                    'text' => $text,
                    'parse_mode' => 'HTML',
                ]);

            if (!$response->successful() || !data_get($response->json(), 'ok')) {
                return [
                    'status' => 'failed',
                    'error' => (string) data_get($response->json(), 'description', 'Telegram sendMessage failed.'),
                    'payload' => $response->json(),
                ];
            }

            return [
                'status' => 'sent',
                'payload' => $response->json(),
            ];
        } catch (\Throwable $throwable) {
            Log::warning('Notice Telegram notification failed', [
                'notice_id' => $notice->id,
                'user_id' => $user->id,
                'error' => $throwable->getMessage(),
            ]);

            return [
                'status' => 'failed',
                'error' => $throwable->getMessage(),
            ];
        }
    }

    private function buildTelegramText(Notice $notice): string
    {
        $title = e((string) $notice->notice_type);
        $description = e((string) $notice->notice_descriptiion);
        $date = optional($notice->notice_date)->format('d/m/Y');
        $by = e((string) $notice->notice_by);
        $url = route('notice.index');

        return "<b>{$title}</b>\n"
            . "<b>" . localize('notice_date', 'Notice date') . ":</b> {$date}\n"
            . "<b>" . localize('notice_by', 'Notice by') . ":</b> {$by}\n\n"
            . "{$description}\n\n"
            . "<a href=\"{$url}\">" . localize('view_notice', 'View notice') . "</a>";
    }
}

