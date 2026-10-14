<?php

namespace App\Http\Middleware;

use App\Services\CountryResolver;
use App\Services\VisitorStatistics;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class CountPublicVisitors
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Count only successful public HTML page loads, never admin, errors, assets or downloads.
        if (! $request->isMethod('GET') || ! $request->route()?->getName()
            || $request->routeIs('admin.*') || $request->user()
            || $response->getStatusCode() !== 200
            || ! str_contains($response->headers->get('Content-Type', ''), 'text/html')
            || $request->ajax() || $request->prefetch()
            || preg_match('/bot|crawler|spider|slurp|headless|preview/i', $request->userAgent() ?? '')) {
            return $response;
        }

        $token = $request->cookie(VisitorStatistics::COOKIE);
        $isNew = ! is_string($token) || ! preg_match('/\A[a-f0-9]{64}\z/', $token);
        if ($isNew) {
            $token = bin2hex(random_bytes(32));
        }

        try {
            app(VisitorStatistics::class)->record($token, $this->country($request));
        } catch (QueryException $exception) {
            // An analytics write must never prevent a successful public page response.
            report($exception);

            return $response;
        }

        if ($isNew) {
            $response->headers->setCookie(cookie(
                VisitorStatistics::COOKIE, $token, 60 * 24 * 365,
                config('session.path', '/'), null, $request->isSecure(), true, false, 'lax'
            ));
        }

        return $response;
    }

    /** Country lookup must never break a page load; unknown is fine. */
    private function country(Request $request): ?string
    {
        try {
            return app(CountryResolver::class)->resolve($request);
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }
    }
}
