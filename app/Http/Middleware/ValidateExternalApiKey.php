<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ValidateExternalApiKey
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        $configuredKey = (string) env('EXTERNAL_SYNC_API_KEY', '');
        if ($configuredKey === '') {
            return response()->json([
                'ok' => false,
                'message' => 'External API key is not configured on server.',
            ], 503);
        }

        $incomingKey = (string) (
            $request->header('X-API-KEY')
            ?: $request->bearerToken()
            ?: $request->query('api_key')
        );

        if ($incomingKey === '' || !hash_equals($configuredKey, $incomingKey)) {
            return response()->json([
                'ok' => false,
                'message' => 'Unauthorized API key.',
            ], 401);
        }

        return $next($request);
    }
}

