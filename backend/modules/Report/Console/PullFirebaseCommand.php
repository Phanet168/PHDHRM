<?php

namespace Modules\Report\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Modules\Report\Services\MasterDataImporter;

/**
 * Pulls the PHDReport collections straight from Firestore (project dbreportphd) with a PHDReport login,
 * saves them as one JSON export and optionally imports it.
 *
 *   php artisan report:pull-firebase            → asks for email + password, saves storage/app/phdreport-export/*.json
 *   php artisan report:pull-firebase --import   → same, then imports into the report_* tables
 *   php artisan report:pull-firebase --key=C:\keys\dbreportphd-adminsdk.json --import
 *                                               → uses a service account key instead of a login
 *
 * Credentials are only sent to Google's endpoints and are never stored.
 */
class PullFirebaseCommand extends Command
{
    protected $signature = 'report:pull-firebase
        {--key= : Path to a Firebase service account JSON key (Admin access, ignores Firestore rules)}
        {--email= : PHDReport (Firebase Auth) email, when no --key is given}
        {--import : Import the pulled data right away}
        {--collections=departments,units,indicators,periods,import_mappings,reports,actions : Comma-separated collections}';

    protected $description = 'Read PHDReport data from Firebase/Firestore and save (optionally import) it';

    // Public web config of the PHDReport app (assets/js/firebase.client.js).
    private const API_KEY = 'AIzaSyBq4B-6uPnwMZSdxz8zuERgLIEAyVtYZeo';
    private const PROJECT = 'dbreportphd';

    private string $project = self::PROJECT;

    public function handle(MasterDataImporter $importer): int
    {
        $token = $this->option('key') ? $this->serviceAccountToken($this->option('key')) : $this->passwordToken();
        if (!$token) {
            return self::FAILURE;
        }

        $collections = array_filter(array_map('trim', explode(',', $this->option('collections'))));
        // Accounts are managed by HRM User Management (users + report_user_units); never copy PHDReport users.
        if (in_array('users', $collections, true)) {
            $this->warn('Skipping "users": accounts are managed in HRM User Management.');
            $collections = array_diff($collections, ['users']);
        }

        $export = [];
        foreach ($collections as $collection) {
            $rows = $this->pull($token, $collection);
            if ($rows === null) {
                return self::FAILURE;
            }
            $export[$collection] = $rows;
            $this->line(sprintf('  %-16s %d', $collection, count($rows)));
        }

        $path = 'phdreport-export/phdreport-' . now()->format('Ymd-His') . '.json';
        Storage::put($path, json_encode($export, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        $this->info('Saved ' . Storage::path($path));

        if ($this->option('import')) {
            foreach ($importer->import($export) as $collection => $n) {
                $this->line(sprintf('  imported %-16s %d', $collection, $n));
            }
            $this->info('Import done.');
        } else {
            $this->line('Import it with: php artisan report:import-phdreport "' . Storage::path($path) . '"');
        }

        return self::SUCCESS;
    }

    /** Sign in with a PHDReport user; Firestore rules then decide what can be read. */
    private function passwordToken(): ?string
    {
        $email = $this->option('email') ?: $this->ask('PHDReport email');
        $auth = Http::asJson()->timeout(30)->post(
            'https://identitytoolkit.googleapis.com/v1/accounts:signInWithPassword?key=' . self::API_KEY,
            ['email' => $email, 'password' => $this->secret('Password'), 'returnSecureToken' => true]
        );
        if ($auth->failed()) {
            $this->error('Sign-in failed: ' . ($auth->json('error.message') ?? $auth->status()));

            return null;
        }
        $this->info('Signed in as ' . $email);

        return $auth->json('idToken');
    }

    /**
     * OAuth access token from a service account key (same credential firebase-admin uses):
     * an RS256-signed JWT is exchanged at Google's token endpoint for a Datastore-scoped token.
     */
    private function serviceAccountToken(string $path): ?string
    {
        $key = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;
        if (!is_array($key) || empty($key['private_key']) || empty($key['client_email'])) {
            $this->error("Not a service account JSON key: {$path}");

            return null;
        }
        $this->project = $key['project_id'] ?? self::PROJECT;

        $b64 = fn (string $s) => rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
        $tokenUri = $key['token_uri'] ?? 'https://oauth2.googleapis.com/token';
        $now = time();
        $unsigned = $b64(json_encode(['alg' => 'RS256', 'typ' => 'JWT'])) . '.' . $b64(json_encode([
            'iss' => $key['client_email'],
            'scope' => 'https://www.googleapis.com/auth/datastore',
            'aud' => $tokenUri,
            'iat' => $now,
            'exp' => $now + 3600,
        ]));
        if (!openssl_sign($unsigned, $signature, $key['private_key'], OPENSSL_ALGO_SHA256)) {
            $this->error('Could not sign with the service account private key');

            return null;
        }

        $res = Http::asForm()->timeout(30)->post($tokenUri, [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $unsigned . '.' . $b64($signature),
        ]);
        if ($res->failed()) {
            $this->error('Token request failed: ' . ($res->json('error_description') ?? $res->json('error') ?? $res->status()));

            return null;
        }
        $this->info("Authorised as {$key['client_email']} (project {$this->project})");

        return $res->json('access_token');
    }

    /** All documents of a collection as plain arrays (null on a permission / network error). */
    private function pull(string $token, string $collection): ?array
    {
        $url = sprintf('https://firestore.googleapis.com/v1/projects/%s/databases/(default)/documents/%s', $this->project, $collection);
        $rows = [];
        $pageToken = null;
        do {
            // Large collections take many pages; retry transient network failures instead of aborting.
            $res = Http::withToken($token)->connectTimeout(20)->timeout(90)->retry(5, 3000, throw: false)
                ->get($url, array_filter(['pageSize' => 1000, 'pageToken' => $pageToken]));
            if ($res->failed()) {
                $this->error("{$collection}: " . ($res->json('error.message') ?? $res->status()));

                return null;
            }
            foreach ($res->json('documents') ?? [] as $doc) {
                $row = $this->fields($doc['fields'] ?? []);
                $row['id'] ??= basename($doc['name']);
                $rows[] = $row;
            }
            $pageToken = $res->json('nextPageToken');
        } while ($pageToken);

        return $rows;
    }

    private function fields(array $fields): array
    {
        return array_map(fn ($v) => $this->value($v), $fields);
    }

    /** Firestore typed value → PHP value. */
    private function value(array $v): mixed
    {
        return match (true) {
            array_key_exists('stringValue', $v) => $v['stringValue'],
            array_key_exists('integerValue', $v) => (int) $v['integerValue'],
            array_key_exists('doubleValue', $v) => (float) $v['doubleValue'],
            array_key_exists('booleanValue', $v) => (bool) $v['booleanValue'],
            array_key_exists('timestampValue', $v) => $v['timestampValue'],
            array_key_exists('mapValue', $v) => $this->fields($v['mapValue']['fields'] ?? []),
            array_key_exists('arrayValue', $v) => array_map(fn ($x) => $this->value($x), $v['arrayValue']['values'] ?? []),
            array_key_exists('referenceValue', $v) => $v['referenceValue'],
            default => null,
        };
    }
}
