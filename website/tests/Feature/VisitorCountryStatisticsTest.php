<?php

namespace Tests\Feature;

use App\Models\News;
use App\Services\CountryResolver;
use App\Services\VisitorStatistics;
use App\Support\CountryNames;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class VisitorCountryStatisticsTest extends TestCase
{
    use RefreshDatabase;

    private function visitor(string $letter, ?string $country): void
    {
        app(VisitorStatistics::class)->record(str_repeat($letter, 64), $country);
    }

    private function localDatabase(): ?string
    {
        $path = storage_path('app/geoip/dbip-country-lite.mmdb');

        return is_file($path) ? $path : null;
    }

    // --- Storage: country code only, first resolved country wins ---

    public function test_only_a_two_letter_country_code_is_stored_per_visitor(): void
    {
        $this->assertEqualsCanonicalizing(['token_hash', 'country_code'], Schema::getColumnListing('visitor_countries'));

        $this->visitor('a', 'KH');
        $this->visitor('a', 'TH'); // same visitor later seen elsewhere: the first country is kept

        $this->assertSame(1, DB::table('visitor_countries')->count());
        $this->assertSame('KH', DB::table('visitor_countries')->value('country_code'));
    }

    public function test_unknown_or_malformed_countries_are_not_stored(): void
    {
        $this->visitor('a', null);
        $this->visitor('b', 'khm');
        $this->visitor('c', 'k1');
        $this->visitor('d', '');

        $this->assertSame(0, DB::table('visitor_countries')->count());
        $this->assertSame(4, app(VisitorStatistics::class)->summary()['total']);
    }

    public function test_country_recording_does_not_change_the_existing_statistics(): void
    {
        $this->visitor('a', 'KH');
        $this->visitor('b', 'TH');
        $this->visitor('b', 'TH');

        $expected = ['online' => 2, 'today' => 2, 'yesterday' => 0, 'month' => 2, 'total' => 2, 'page_views' => 3];
        $this->assertSame($expected, app(VisitorStatistics::class)->summary());
        $this->getJson('/visitor-statistics')->assertOk()->assertExactJson($expected);
    }

    // --- Aggregation ---

    public function test_countries_are_ranked_with_shares_and_the_rest_grouped(): void
    {
        $n = 0;
        foreach (['KH' => 5, 'TH' => 3, 'VN' => 2, 'US' => 1, 'JP' => 1, 'FR' => 1, 'DE' => 1] as $code => $count) {
            for ($i = 0; $i < $count; $i++) {
                DB::table('visitor_countries')->insert(['token_hash' => str_pad((string) ++$n, 64, '0', STR_PAD_LEFT), 'country_code' => $code]);
            }
        }

        $result = app(VisitorStatistics::class)->countries(5);

        $this->assertSame(14, $result['known']);
        $this->assertSame(['KH', 'TH', 'VN', 'DE', 'FR'], array_column($result['countries'], 'code')); // ties broken by code
        $this->assertSame([5, 3, 2, 1, 1], array_column($result['countries'], 'visitors'));
        $this->assertSame(35.7, $result['countries'][0]['share']);
        $this->assertSame('កម្ពុជា', $result['countries'][0]['name']);
        $this->assertSame(2, $result['other']);
    }

    public function test_country_names_fall_back_to_the_code(): void
    {
        $this->assertSame('កម្ពុជា', CountryNames::name('kh'));
        $this->assertSame('ZW', CountryNames::name('ZW'));
    }

    // --- Resolver ---

    public function test_a_country_header_is_only_trusted_when_configured(): void
    {
        $request = Request::create('/', 'GET', server: ['HTTP_CF_IPCOUNTRY' => 'th']);

        config(['services.geoip.country_header' => null]);
        $this->assertNull((new CountryResolver)->resolve($request));

        config(['services.geoip.country_header' => 'CF-IPCountry']);
        $this->assertSame('TH', (new CountryResolver)->resolve($request));

        foreach (['XX', 'T1', 'ABC', ''] as $bad) {
            $this->assertNull((new CountryResolver)->resolve(Request::create('/', 'GET', server: ['HTTP_CF_IPCOUNTRY' => $bad])));
        }
    }

    public function test_private_addresses_and_a_missing_database_give_an_unknown_country(): void
    {
        config(['services.geoip.database' => storage_path('framework/testing/does-not-exist.mmdb')]);
        $resolver = new CountryResolver;

        $this->assertNull($resolver->lookup('127.0.0.1'));
        $this->assertNull($resolver->lookup('192.168.1.10'));
        $this->assertNull($resolver->lookup('8.8.8.8')); // public, but no database available
        $this->assertNull($resolver->lookup(null));
        $this->assertNull($resolver->lookup('not-an-ip'));
    }

    public function test_the_local_database_resolves_public_addresses(): void
    {
        $this->localDatabase() ?: $this->markTestSkipped('No GeoIP database installed (run php artisan geoip:update).');

        $resolver = new CountryResolver;

        $this->assertSame('US', $resolver->lookup('8.8.8.8'));
        $this->assertSame('KH', $resolver->lookup('175.100.1.1'));
        $this->assertNull($resolver->lookup('127.0.0.1'));
        $this->assertSame('KH', $resolver->resolve(Request::create('/', 'GET', server: ['REMOTE_ADDR' => '118.67.200.1'])));
    }

    // --- End to end through the middleware ---

    public function test_a_page_load_records_the_country_once_per_visitor(): void
    {
        $this->mock(CountryResolver::class, fn ($mock) => $mock->shouldReceive('resolve')->andReturn('KH'));

        $first = $this->get('/news')->assertOk();
        $cookie = $first->getCookie(VisitorStatistics::COOKIE)->getValue();
        $this->withCookie(VisitorStatistics::COOKIE, $cookie)->get('/news')->assertOk();

        $this->assertSame(1, DB::table('visitor_countries')->count());
        $this->assertSame('KH', DB::table('visitor_countries')->value('country_code'));
        $this->assertNotSame($cookie, DB::table('visitor_countries')->value('token_hash'));
    }

    public function test_a_failing_country_lookup_never_breaks_the_page(): void
    {
        $this->mock(CountryResolver::class, fn ($mock) => $mock->shouldReceive('resolve')->andThrow(new \RuntimeException('database corrupt')));

        $this->get('/news')->assertOk();

        $this->assertSame(1, DB::table('anonymous_visitors')->count());
        $this->assertSame(0, DB::table('visitor_countries')->count());
    }

    // --- Card ---

    public function test_the_statistics_card_lists_countries_with_khmer_names_and_the_required_credit(): void
    {
        $this->visitor('a', 'KH');
        $this->visitor('b', 'KH');
        $this->visitor('c', 'TH');

        $html = $this->get('/news')->assertOk()->getContent();

        $this->assertStringContainsString('news-country-block', $html);
        $this->assertMatchesRegularExpression('/កម្ពុជា<\/span>\s*<span class="news-country-count">២</u', $html);
        $this->assertMatchesRegularExpression('/ថៃ<\/span>\s*<span class="news-country-count">១</u', $html);
        $this->assertStringContainsString('href="https://db-ip.com"', $html);
    }

    public function test_the_country_block_and_credit_are_hidden_when_no_country_is_known(): void
    {
        $this->visitor('a', null);

        $this->get('/news')->assertOk()
            ->assertDontSee('news-country-block', false)
            ->assertDontSee('db-ip.com', false);
    }

    public function test_the_card_on_article_pages_shows_countries_too(): void
    {
        $this->visitor('a', 'VN');
        $article = News::create(['title_km' => 'ព័ត៌មាន', 'slug' => 'n-1', 'status' => 'published', 'published_at' => now()->subDay()]);

        $this->get(route('news.show', $article))->assertOk()->assertSee('វៀតណាម');
    }

    // --- geoip:update ---

    private function withTemporaryDatabase(): string
    {
        $dir = storage_path('framework/testing/geoip-'.uniqid());
        File::ensureDirectoryExists($dir);
        $path = $dir.'/db.mmdb';
        config(['services.geoip.database' => $path]);

        return $path;
    }

    public function test_the_database_refresh_is_scheduled_monthly_in_cambodian_time(): void
    {
        $event = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())
            ->first(fn ($event) => str_contains($event->command ?? '', 'geoip:update'));

        $this->assertNotNull($event, 'geoip:update is not scheduled');
        $this->assertSame('0 3 2 * *', $event->expression);
        $this->assertSame('Asia/Phnom_Penh', (string) $event->timezone);
        $this->assertTrue($event->withoutOverlapping);
    }

    public function test_update_command_rejects_a_bad_month(): void
    {
        $this->withTemporaryDatabase();

        $this->artisan('geoip:update', ['--month' => '2026-13'])->assertFailed();
    }

    public function test_update_command_fails_cleanly_when_no_database_is_published(): void
    {
        $path = $this->withTemporaryDatabase();
        Http::fake(['download.db-ip.com/*' => Http::response('', 404)]);

        $this->artisan('geoip:update')->assertFailed();

        $this->assertFileDoesNotExist($path);
        Http::assertSentCount(2); // this month, then the previous one
    }

    public function test_update_command_keeps_the_current_database_when_the_download_is_invalid(): void
    {
        $path = $this->withTemporaryDatabase();
        file_put_contents($path, 'existing-database');
        Http::fake(['download.db-ip.com/*' => Http::response('this is not gzip', 200)]);

        $this->artisan('geoip:update', ['--month' => '2026-09'])->assertFailed();
        $this->assertSame('existing-database', file_get_contents($path));

        Http::fake(['download.db-ip.com/*' => Http::response(gzencode('gzip but not a database'), 200)]);
        $this->artisan('geoip:update', ['--month' => '2026-09'])->assertFailed();
        $this->assertSame('existing-database', file_get_contents($path));
        $this->assertFileDoesNotExist($path.'.download');
    }

    public function test_update_command_installs_a_valid_database(): void
    {
        $real = $this->localDatabase() ?: $this->markTestSkipped('No GeoIP database installed to use as the download.');
        $path = $this->withTemporaryDatabase();
        Http::fake(['download.db-ip.com/*' => Http::response(gzencode(file_get_contents($real), 1), 200)]);

        $this->artisan('geoip:update', ['--month' => '2026-09'])->assertSuccessful();

        Http::assertSent(fn ($request) => $request->url() === 'https://download.db-ip.com/free/dbip-country-lite-2026-09.mmdb.gz');
        $this->assertFileExists($path);
        $this->assertSame('KH', (new CountryResolver)->lookup('175.100.1.1'));
    }
}
