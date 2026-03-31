<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureUserOtpVerified
{
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
            'security.otp.show',
            'security.otp.verify',
            'security.otp.resend',
        ];

        if (in_array($routeName, $allowRoutes, true)) {
            return $next($request);
        }

        $verifiedUserId = (int) $request->session()->get('otp_verified_user_id', 0);
        if ($verifiedUserId === (int) $user->id) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => localize('otp_verification_required', 'OTP verification is required.'),
            ], 403);
        }

        return redirect()->guest(route('security.otp.show'));
    }
}
