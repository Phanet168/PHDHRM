<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Security\LoginOtpService;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class OtpVerificationController extends Controller
{
    public function __construct(private readonly LoginOtpService $otpService)
    {
    }

    public function show(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return redirect()->route('login');
        }

        $channel = function_exists('otp_channel') ? otp_channel() : (string) config('security.otp.channel', 'log');
        $targetLabel = localize('phone', 'Phone');
        $maskedTarget = '';

        if ($channel === 'telegram') {
            $chatId = trim((string) $user->telegram_chat_id);
            if ($chatId === '') {
                return redirect()->route('security.telegram.connect.show');
            }

            $targetLabel = localize('telegram', 'Telegram');
            $maskedTarget = localize('telegram_connected', 'Connected');
        } else {
            $contactNo = trim((string) $user->contact_no);
            if ($contactNo === '') {
                return redirect()->route('security.phone.required.show');
            }

            $maskedTarget = $this->otpService->maskedPhone($contactNo);
        }

        $issued = $this->otpService->issue($user, (bool) config('security.otp.debug_show', false));
        if (!$issued['ok']) {
            Toastr::warning((string) ($issued['message'] ?? localize('please_try_again', 'Please try again.')));
        }

        $debugOtp = null;
        if ((bool) config('security.otp.debug_show', false)) {
            $debugOtp = (string) ($issued['code'] ?? '');
        }

        return view('auth.otp-verify', [
            'targetLabel' => $targetLabel,
            'maskedTarget' => $maskedTarget,
            'debugOtp' => $debugOtp,
        ]);
    }

    public function verify(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'otp_code' => ['required', 'digits:6'],
        ], [
            'otp_code.required' => localize('otp_required', 'OTP code is required.'),
            'otp_code.digits' => localize('otp_code_digits', 'OTP code must be 6 digits.'),
        ]);

        $user = $request->user();
        if (!$user) {
            return redirect()->route('login');
        }

        $result = $this->otpService->verify($user, (string) $validated['otp_code']);
        if (!$result['ok']) {
            return back()->withErrors(['otp_code' => (string) ($result['message'] ?? localize('otp_invalid', 'Invalid OTP code.'))]);
        }

        $request->session()->put('otp_verified_user_id', (int) $user->id);
        $request->session()->put('otp_verified_at', now()->toIso8601String());

        Toastr::success(localize('otp_verified_successfully', 'Verification completed successfully.'));

        return redirect()->intended(route('staffHome'));
    }

    public function resend(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (!$user) {
            return redirect()->route('login');
        }

        $result = $this->otpService->issue($user, true);
        if (!$result['ok']) {
            return back()->withErrors(['otp_code' => (string) ($result['message'] ?? localize('please_try_again', 'Please try again.'))]);
        }

        if ((bool) config('security.otp.debug_show', false) && !empty($result['code'])) {
            Toastr::info('DEV OTP: ' . $result['code']);
        } else {
            Toastr::success(localize('otp_resent_success', 'OTP code has been sent.'));
        }

        return back();
    }
}
