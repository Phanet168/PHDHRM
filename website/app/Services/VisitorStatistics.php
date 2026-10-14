<?php

namespace App\Services;

use App\Support\CountryNames;
use Illuminate\Support\Facades\DB;

class VisitorStatistics
{
    public const COOKIE = 'phd_visitor';

    public function record(string $token, ?string $country = null): void
    {
        // The identifier is random, never derived from an IP, account, or browser fingerprint.
        $hash = hash_hmac('sha256', $token, config('app.key'));
        $now = now();

        DB::transaction(function () use ($hash, $now, $country) {
            DB::table('anonymous_visitors')->insertOrIgnore([
                'token_hash' => $hash,
                'first_seen_at' => $now,
                'last_seen_at' => $now,
                'page_views' => 0,
            ]);
            DB::table('anonymous_visitors')->where('token_hash', $hash)
                ->increment('page_views', 1, ['last_seen_at' => $now]);
            DB::table('visitor_days')->insertOrIgnore([
                'date' => $now->copy()->timezone('Asia/Phnom_Penh')->toDateString(),
                'token_hash' => $hash,
            ]);

            // The first resolved country of a visitor is kept; only the two-letter code is stored, never the IP.
            if ($country !== null && preg_match('/\A[A-Z]{2}\z/', $country)) {
                DB::table('visitor_countries')->insertOrIgnore(['token_hash' => $hash, 'country_code' => $country]);
            }
        });
    }

    public function summary(): array
    {
        $today = now('Asia/Phnom_Penh');
        $totals = DB::table('anonymous_visitors')->selectRaw('COUNT(*) AS visitors, COALESCE(SUM(page_views), 0) AS page_views')->first();

        return [
            'online' => DB::table('anonymous_visitors')->where('last_seen_at', '>=', now()->subMinutes(5))->count(),
            'today' => DB::table('visitor_days')->where('date', $today->toDateString())->count(),
            'yesterday' => DB::table('visitor_days')->where('date', $today->copy()->subDay()->toDateString())->count(),
            'month' => DB::table('visitor_days')->whereBetween('date', [$today->copy()->startOfMonth()->toDateString(), $today->toDateString()])->distinct()->count('token_hash'),
            'total' => (int) $totals->visitors,
            'page_views' => (int) $totals->page_views,
        ];
    }

    /**
     * Visitors by country (distinct anonymous visitors, first resolved country each).
     * Visitors seen before country detection was enabled, or whose country could not
     * be determined, are simply not included.
     *
     * @return array{countries: list<array{code: string, name: string, visitors: int, share: float}>, other: int, known: int}
     */
    public function countries(int $limit = 5): array
    {
        $rows = DB::table('visitor_countries')
            ->selectRaw('country_code, COUNT(*) AS visitors')
            ->groupBy('country_code')
            ->orderByDesc('visitors')
            ->orderBy('country_code')
            ->get();

        $known = (int) $rows->sum('visitors');

        return [
            'countries' => $rows->take($limit)->map(fn ($row) => [
                'code' => $row->country_code,
                'name' => CountryNames::name($row->country_code),
                'visitors' => (int) $row->visitors,
                'share' => $known > 0 ? round($row->visitors / $known * 100, 1) : 0.0,
            ])->values()->all(),
            'other' => (int) $rows->slice($limit)->sum('visitors'),
            'known' => $known,
        ];
    }
}
