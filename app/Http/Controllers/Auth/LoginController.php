<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Providers\RouteServiceProvider;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller {

/*
|--------------------------------------------------------------------------
| Login Controller
|--------------------------------------------------------------------------
|
| This controller handles authenticating users for the application and
| redirecting them to your home screen. The controller uses a trait
| to conveniently provide its functionality to your applications.
|
 */

    use AuthenticatesUsers;

    /**
     * Where to redirect users after login.
     *
     * @var string
     */

    protected $redirectTo = RouteServiceProvider::HOME;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct() {
        $this->middleware('guest')->except('logout');
    }

    /**
     * Use a single login field that accepts email or username.
     */
    public function username(): string
    {
        return 'login';
    }

    /**
     * Determine the auth credentials from request.
     */
    protected function credentials(Request $request): array
    {
        $login = trim((string) $request->input($this->username(), $request->input('email', '')));
        $field = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'user_name';
        $normalizedLogin = $field === 'user_name' ? mb_strtolower($login, 'UTF-8') : $login;

        return [
            $field => $normalizedLogin,
            'password' => (string) $request->input('password'),
        ];
    }

    public function showLoginForm() {

        return view('auth.login');
    }

    /**
     * Reset OTP verification state on every new login and redirect to the right dashboard.
     */
    protected function authenticated(Request $request, $user)
    {
        $request->session()->forget([
            'otp_verified_user_id',
            'otp_verified_at',
        ]);

        if ($user && (int) $user->user_type_id === 1 && $user->can('read_dashboard')) {
            return redirect()->route('home');
        }

        return redirect()->route('staffHome');
    }

}
