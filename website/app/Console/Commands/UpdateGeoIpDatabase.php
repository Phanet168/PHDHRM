<?php

namespace App\Console\Commands;

use App\Services\CountryResolver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use MaxMind\Db\Reader;
use Throwable;

class UpdateGeoIpDatabase extends Command
{
    protected $signature = 'geoip:update {--month= : Month to download as YYYY-MM (default: the current month, then the previous one)}';

    protected $description = 'Download the free DB-IP Lite country database used for the visitor country statistics';

    public function handle(CountryResolver $resolver): int
    {
        $month = $this->option('month');
        if ($month !== null && ! preg_match('/\A\d{4}-(0[1-9]|1[0-2])\z/', (string) $month)) {
            $this->error('--month must look like 2026-09.');

            return self::FAILURE;
        }

        $months = $month ? [$month] : [now()->format('Y-m'), now()->subMonthNoOverflow()->format('Y-m')];
        $path = $resolver->databasePath();

        foreach ($months as $candidate) {
            $url = "https://download.db-ip.com/free/dbip-country-lite-{$candidate}.mmdb.gz";
            $this->line("Downloading {$url}");

            try {
                $response = Http::timeout(180)->get($url);
            } catch (Throwable $exception) {
                $this->error('Download failed: '.$exception->getMessage());

                return self::FAILURE;
            }

            if ($response->status() === 404) {
                continue; // this month's file is not published yet
            }
            if (! $response->successful()) {
                $this->error("Download failed with HTTP {$response->status()}.");

                return self::FAILURE;
            }

            return $this->install($response->body(), $path, $candidate);
        }

        $this->error('No DB-IP Lite country database was found for the requested month(s).');

        return self::FAILURE;
    }

    private function install(string $gzipped, string $path, string $month): int
    {
        $binary = @gzdecode($gzipped);
        if ($binary === false || $binary === '') {
            $this->error('The downloaded file is not a valid gzip archive; the current database was left untouched.');

            return self::FAILURE;
        }

        if (! is_dir(dirname($path)) && ! mkdir(dirname($path), 0775, true) && ! is_dir(dirname($path))) {
            $this->error('Cannot create '.dirname($path));

            return self::FAILURE;
        }

        // Validate before replacing anything: it must open as a MaxMind DB that holds country data.
        $temporary = $path.'.download';
        file_put_contents($temporary, $binary);
        try {
            $type = (new Reader($temporary))->metadata()->databaseType;
            if (! str_contains($type, 'Country')) {
                throw new \RuntimeException("unexpected database type {$type}");
            }
        } catch (Throwable $exception) {
            @unlink($temporary);
            $this->error('The downloaded file is not a country database ('.$exception->getMessage().'); the current database was left untouched.');

            return self::FAILURE;
        }

        if (! @rename($temporary, $path)) {
            @unlink($temporary);
            $this->error("Cannot replace {$path}; is it in use?");

            return self::FAILURE;
        }

        $this->info("Installed the {$month} database at {$path}.");
        $this->line('IP Geolocation by DB-IP (https://db-ip.com), CC BY 4.0. The credit is shown on the statistics card.');

        return self::SUCCESS;
    }
}
