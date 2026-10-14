<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLogin(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('admin.dashboard');
        }

        return view('admin.auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors([
                'email' => 'អ៊ីមែល ឬពាក្យសម្ងាត់មិនត្រឹមត្រូវ។',
            ])->onlyInput('email');
        }

        if (! Auth::user()->is_active) {
            Auth::logout();

            return back()->withErrors([
                'email' => 'គណនីរបស់អ្នកត្រូវបានផ្អាក។ សូមទាក់ទងអ្នកគ្រប់គ្រងប្រព័ន្ធ។',
            ])->onlyInput('email');
        }

        $request->session()->regenerate();

        AuditLog::record('login', 'User', Auth::id(), Auth::user()->name . ' បានចូលប្រព័ន្ធ');

        return redirect()->intended(route('admin.dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        AuditLog::record('logout', 'User', Auth::id(), Auth::user()?->name . ' បានចាកចេញពីប្រព័ន្ធ');

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
