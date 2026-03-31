<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

class Language
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */

    public function handle(Request $request, Closure $next)
    {
        $locale = config('app.locale');

        if (!is_string($locale) || trim($locale) === '') {
            $locale = config('app.fallback_locale', 'km');
        }

        App::setLocale($locale);

        return $next($request);
    }
}
