<?php

namespace Tests\Feature;

use App\Services\VisitorStatistics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class VisitorStatisticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_repeat_page_loads_share_an_anonymous_visitor_without_storing_personal_data(): void
    {
        $first = $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.42'])->get('/news')->assertOk();
        $cookie = $first->getCookie(VisitorStatistics::COOKIE);
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertSame('lax', $cookie->getSameSite());
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $cookie->getValue());
        $this->withCookie(VisitorStatistics::COOKIE, $cookie->getValue())->get('/news')->assertOk();

        $this->assertDatabaseCount('anonymous_visitors', 1);
        $this->assertDatabaseCount('visitor_days', 1);
        $this->assertSame(2, app(VisitorStatistics::class)->summary()['page_views']);
        $this->assertSame(1, app(VisitorStatistics::class)->summary()['today']);
        $this->assertNotSame($cookie->getValue(), DB::table('anonymous_visitors')->value('token_hash'));
        $this->assertEqualsCanonicalizing(['token_hash', 'first_seen_at', 'last_seen_at', 'page_views'], Schema::getColumnListing('anonymous_visitors'));
        $this->assertEqualsCanonicalizing(['date', 'token_hash'], Schema::getColumnListing('visitor_days'));
    }

    public function test_counts_are_unique_per_day_and_month_with_cambodian_midnight_boundaries(): void
    {
        $stats = app(VisitorStatistics::class);
        $one = str_repeat('a', 64);
        $two = str_repeat('b', 64);
        $this->travelTo(Carbon::parse('2026-08-31 16:59:00', 'UTC'));
        $stats->record($one);
        $this->travelTo(Carbon::parse('2026-08-31 17:01:00', 'UTC'));
        $stats->record($one);
        $stats->record($two);
        $stats->record($two);
        $this->assertSame(['online' => 2, 'today' => 2, 'yesterday' => 1, 'month' => 2, 'total' => 2, 'page_views' => 4], $stats->summary());
        $this->travel(6)->minutes();
        $this->assertSame(0, $stats->summary()['online']);
        $this->travel(1)->days();
        $stats->record($one);
        $this->assertSame(['online' => 1, 'today' => 1, 'yesterday' => 2, 'month' => 2, 'total' => 2, 'page_views' => 5], $stats->summary());
    }

    public function test_non_page_requests_and_admin_are_not_counted(): void
    {
        $this->get('/admin/login')->assertOk()->assertCookieMissing(VisitorStatistics::COOKIE);
        $this->get('/not-a-real-page')->assertNotFound();
        $this->head('/news')->assertOk();
        $this->get('/news', ['User-Agent' => 'Googlebot'])->assertOk()->assertCookieMissing(VisitorStatistics::COOKIE);
        $this->get('/news', ['Purpose' => 'prefetch'])->assertOk()->assertCookieMissing(VisitorStatistics::COOKIE);
        $this->assertDatabaseCount('anonymous_visitors', 0);
    }

    public function test_distinct_browsers_count_separately(): void
    {
        $stats = app(VisitorStatistics::class);
        $stats->record(str_repeat('a', 64));
        $stats->record(str_repeat('b', 64));
        $this->assertSame(2, $stats->summary()['total']);
    }

    public function test_live_statistics_expose_only_aggregates_and_do_not_count_as_page_views(): void
    {
        app(VisitorStatistics::class)->record(str_repeat('a', 64));
        $this->getJson('/visitor-statistics')->assertOk()->assertExactJson([
            'online' => 1, 'today' => 1, 'yesterday' => 0, 'month' => 1, 'total' => 1, 'page_views' => 1,
        ])->assertCookieMissing(VisitorStatistics::COOKIE);
        $this->assertSame(1, app(VisitorStatistics::class)->summary()['page_views']);
    }
}
