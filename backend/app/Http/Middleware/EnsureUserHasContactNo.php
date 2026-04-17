<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureUserHasContactNo
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        if (function_exists('otp_required') && !otp_required()) {
            return $next($request);
        }

        if (!function_exists('otp_required') && !(bool) config('security.otp.required', true)) {
            return $next($request);
        }

        $user = $request->user();

        if (!$user) {
            return $next($request);
        }

        $routeName = (string) optional($request->route())->getName();
        $allowRoutes = [
            'logout',
            'security.phone.required.show',
            'security.phone.required.update',
            'security.telegram.connect.show',
            'security.telegram.connect.regenerate',
            'security.telegram.connect.sync',
        ];

        if (in_array($routeName, $allowRoutes, true)) {
            return $next($request);
        }

        $channel = function_exists('otp_channel') ? otp_channel() : (string) config('security.otp.channel', 'log');
        if ($channel === 'telegram') {
            $telegramChatId = trim((string) ($user->telegram_chat_id ?? ''));
            if ($telegramChatId !== '') {
                return $next($request);
            }

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => localize('telegram_required_for_login', 'Telegram link is required before you can access the system.'),
                ], 403);
            }

            return redirect()
                ->route('security.telegram.connect.show')
                ->with('warning', localize('telegram_required_for_login', 'Telegram link is required before you can access the system.'));
        }

        $contactNo = trim((string) $user->contact_no);
        if ($contactNo !== '') {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => localize('phone_required_for_login', 'Phone number is required before you can access the system.'),
            ], 403);
        }

        return redirect()
            ->route('security.phone.required.show')
            ->with('warning', localize('phone_required_for_login', 'Phone number is required before you can access the system.'));
    }
}
