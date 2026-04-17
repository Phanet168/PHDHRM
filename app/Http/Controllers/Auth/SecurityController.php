<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Security\TelegramLinkService;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SecurityController extends Controller
{
    public function __construct(private readonly TelegramLinkService $telegramLinkService)
    {
    }

    public function showPhoneRequired(Request $request)
    {
        return view('auth.phone-required', [
            'user' => $request->user(),
        ]);
    }

    public function updatePhoneRequired(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'contact_no' => [
                'required',
                'string',
                'regex:/^[0-9+]{8,20}$/',
                'max:191',
                Rule::unique('users', 'contact_no')->ignore((int) $request->user()->id),
            ],
        ], [
            'contact_no.required' => localize('contact_no_required', 'Mobile number is required.'),
            'contact_no.regex' => localize('contact_no_format_invalid', 'Mobile number format is invalid.'),
            'contact_no.unique' => localize('contact_no_unique', 'This mobile number is already in use.'),
        ]);

        $contactNo = trim((string) $validated['contact_no']);
        $user = $request->user();

        $user->contact_no = $contactNo;
        $user->save();

        // Keep employee phone aligned with login phone when missing.
        $employee = $user->employee;
        if ($employee && trim((string) $employee->phone) === '') {
            $employee->phone = $contactNo;
            $employee->save();
        }

        Toastr::success(localize('phone_saved_successfully', 'Phone number has been saved successfully.'));

        return redirect()->route('staffHome');
    }

    public function showTelegramConnect(Request $request)
    {
        $user = $request->user();
        $telegramLink = $this->telegramLinkService->deepLink($user);

        return view('auth.telegram-connect', [
            'user' => $user,
            'telegramLink' => $telegramLink,
            'botUsername' => trim((string) config('security.otp.telegram.bot_username', '')),
            'isConfigured' => trim((string) config('security.otp.telegram.bot_token', '')) !== '',
        ]);
    }

    public function regenerateTelegramConnect(Request $request): RedirectResponse
    {
        $this->telegramLinkService->regenerateLinkToken($request->user());
        Toastr::success(localize('telegram_link_regenerated', 'Telegram connect link has been regenerated.'));

        return redirect()->route('security.telegram.connect.show');
    }

    public function syncTelegramConnect(Request $request): RedirectResponse
    {
        $result = $this->telegramLinkService->syncFromTelegram($request->user());
        if (!($result['ok'] ?? false)) {
            return back()->withErrors([
                'telegram' => (string) ($result['message'] ?? localize('please_try_again', 'Please try again.')),
            ]);
        }

        Toastr::success(localize('telegram_connected_successfully', 'Telegram linked successfully.'));
        return redirect()->route('security.otp.show');
    }
}
