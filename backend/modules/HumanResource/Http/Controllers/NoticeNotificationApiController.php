<?php

namespace Modules\HumanResource\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\HumanResource\Entities\Notice;
use Modules\HumanResource\Entities\NoticeDelivery;

class NoticeNotificationApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $userId = (int) ($request->user()?->id ?? 0);
        if ($userId <= 0) {
            return $this->unauthorizedResponse();
        }

        $perPage = (int) $request->input('per_page', 20);
        $perPage = max(1, min($perPage, 100));

        $rows = $this->baseQuery($userId)
            ->with([
                'notice' => function ($query): void {
                    $query->withoutGlobalScope('sortByLatest');
                },
            ])
            ->whereHas('notice')
            ->orderByRaw('COALESCE(sent_at, created_at) DESC')
            ->orderByDesc('id')
            ->paginate($perPage);

        $items = [];
        foreach ($rows->items() as $row) {
            if ($row instanceof NoticeDelivery) {
                $items[] = $this->transformDelivery($row);
            }
        }

        return response()->json([
            'response' => [
                'status' => 'ok',
                'data' => [
                    'data' => $items,
                    'current_page' => $rows->currentPage(),
                    'per_page' => $rows->perPage(),
                    'total' => $rows->total(),
                    'last_page' => $rows->lastPage(),
                    'unread_count' => $this->unreadCountForUser($userId),
                ],
            ],
        ]);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        $userId = (int) ($request->user()?->id ?? 0);
        if ($userId <= 0) {
            return $this->unauthorizedResponse();
        }

        return response()->json([
            'response' => [
                'status' => 'ok',
                'data' => [
                    'unread_count' => $this->unreadCountForUser($userId),
                ],
            ],
        ]);
    }

    public function read(Request $request, int $notificationDelivery): JsonResponse
    {
        $userId = (int) ($request->user()?->id ?? 0);
        if ($userId <= 0) {
            return $this->unauthorizedResponse();
        }

        $delivery = $this->baseQuery($userId)
            ->where('id', $notificationDelivery)
            ->with([
                'notice' => function ($query): void {
                    $query->withoutGlobalScope('sortByLatest');
                },
            ])
            ->first();

        if (!$delivery) {
            return response()->json([
                'response' => [
                    'status' => 'error',
                    'message' => 'Notification not found',
                ],
            ], 404);
        }

        if ($delivery->read_at === null) {
            $delivery->read_at = now();
            $delivery->save();
        }

        $delivery->refresh();
        $delivery->load([
            'notice' => function ($query): void {
                $query->withoutGlobalScope('sortByLatest');
            },
        ]);

        return response()->json([
            'response' => [
                'status' => 'ok',
                'message' => 'Notification marked as read',
                'data' => $this->transformDelivery($delivery),
                'unread_count' => $this->unreadCountForUser($userId),
            ],
        ]);
    }

    public function readAll(Request $request): JsonResponse
    {
        $userId = (int) ($request->user()?->id ?? 0);
        if ($userId <= 0) {
            return $this->unauthorizedResponse();
        }

        $marked = $this->baseQuery($userId)
            ->whereNull('read_at')
            ->update([
                'read_at' => now(),
                'updated_at' => now(),
            ]);

        return response()->json([
            'response' => [
                'status' => 'ok',
                'message' => 'All notifications marked as read',
                'data' => [
                    'marked_count' => (int) $marked,
                    'unread_count' => $this->unreadCountForUser($userId),
                ],
            ],
        ]);
    }

    private function baseQuery(int $userId)
    {
        return NoticeDelivery::query()
            ->where('user_id', $userId)
            ->where('channel', 'in_app')
            ->where(function ($query): void {
                $query->where('status', 'sent')
                    ->orWhereNotNull('sent_at');
            });
    }

    private function unreadCountForUser(int $userId): int
    {
        return (int) $this->baseQuery($userId)
            ->whereNull('read_at')
            ->count();
    }

    private function transformDelivery(NoticeDelivery $delivery): array
    {
        /** @var Notice|null $notice */
        $notice = $delivery->notice;

        return [
            'id' => (int) $delivery->id,
            'notice_id' => (int) ($delivery->notice_id ?? 0),
            'title' => trim((string) ($notice?->notice_type ?? localize('notice', 'Notice'))),
            'description' => trim((string) ($notice?->notice_descriptiion ?? '')),
            'notice_by' => (string) ($notice?->notice_by ?? ''),
            'notice_date' => optional($notice?->notice_date)->format('Y-m-d') ?? (string) ($notice?->notice_date ?? ''),
            'attachment_url' => $this->attachmentUrl($notice?->notice_attachment),
            'status' => (string) ($delivery->status ?? ''),
            'channel' => (string) ($delivery->channel ?? ''),
            'sent_at' => optional($delivery->sent_at)?->toIso8601String() ?? optional($delivery->created_at)?->toIso8601String(),
            'read_at' => optional($delivery->read_at)?->toIso8601String(),
            'is_unread' => $delivery->read_at === null,
        ];
    }

    private function attachmentUrl(?string $path): ?string
    {
        $normalized = trim((string) $path);
        if ($normalized === '') {
            return null;
        }

        if (str_starts_with($normalized, 'http://') || str_starts_with($normalized, 'https://')) {
            return $normalized;
        }

        if (str_starts_with($normalized, 'storage/') || str_starts_with($normalized, '/storage/')) {
            return url('/' . ltrim($normalized, '/'));
        }

        return url('/storage/' . ltrim($normalized, '/'));
    }

    private function unauthorizedResponse(): JsonResponse
    {
        return response()->json([
            'response' => [
                'status' => 'error',
                'message' => 'Unauthorized',
            ],
        ], 401);
    }
}

