<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->is_active) {
            auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('admin.login')->withErrors([
                'email' => 'គណនីរបស់អ្នកត្រូវបានផ្អាក។ សូមទាក់ទងអ្នកគ្រប់គ្រងប្រព័ន្ធ។',
            ]);
        }

        return $next($request);
    }
}
