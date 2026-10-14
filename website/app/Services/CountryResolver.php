<?php

namespace App\Services;

use Illuminate\Http\Request;
use MaxMind\Db\Reader;
use Throwable;

/**
 * Resolves a visitor's ISO country code without storing anything about them.
 *
 * Sources, in order:
 *  1. A country header set by a CDN/proxy we trust (only when VISITOR_COUNTRY_HEADER
 *     is configured, e.g. "CF-IPCountry" behind Cloudflare; otherwise a client could spoof it).
 *  2. The local DB-IP Lite country database (MaxMind DB format), refreshed by `php artisan geoip:update`.
 * If neither is available the country is simply unknown (null).
 */
class CountryResolver
{
    private ?Reader $reader = null;

    private bool $readerTried = false;

    public function resolve(Request $request): ?string
    {
        $header = config('services.geoip.country_header');
        if (is_string($header) && $header !== '') {
            $code = strtoupper(trim((string) $request->header($header)));
            if (preg_match('/\A[A-Z]{2}\z/', $code) && ! in_array($code, ['XX', 'T1'], true)) {
                return $code;
            }
        }

        return $this->lookup($request->ip());
    }

    public function lookup(?string $ip): ?string
    {
        // Private, loopback and reserved addresses (local dev, internal proxies) have no country.
        if (! $ip || ! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return null;
        }

        $reader = $this->reader();
        if (! $reader) {
            return null;
        }

        try {
            $code = $reader->get($ip)['country']['iso_code'] ?? null;
        } catch (Throwable) {
            return null;
        }

        return is_string($code) && preg_match('/\A[A-Z]{2}\z/', $code) ? $code : null;
    }

    public function databasePath(): string
    {
        return (string) config('services.geoip.database');
    }

    private function reader(): ?Reader
    {
        if (! $this->readerTried) {
            $this->readerTried = true;
            $path = $this->databasePath();

            try {
                $this->reader = is_file($path) ? new Reader($path) : null;
            } catch (Throwable $exception) {
                report($exception);
                $this->reader = null;
            }
        }

        return $this->reader;
    }
}
