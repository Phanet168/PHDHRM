<?php

namespace Tests\Feature;

use App\Models\HealthMessage;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class HealthMessageTickerTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(RolePermissionSeeder::class);
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('Super Admin');

        return $admin;
    }

    private function message(array $attributes = []): HealthMessage
    {
        return HealthMessage::create(array_merge([
            'message_km' => 'សារសាកល្បង',
            'icon' => 'Apple',
            'is_active' => true,
            'sort_order' => 0,
        ], $attributes));
    }

    // --- Public rendering ---

    public function test_only_active_messages_are_displayed(): void
    {
        $this->message(['message_km' => 'សារសកម្ម', 'is_active' => true]);
        $this->message(['message_km' => 'សារអសកម្ម', 'is_active' => false]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('សារសកម្ម');
        $response->assertDontSee('សារអសកម្ម');
    }

    public function test_messages_scheduled_in_the_future_are_excluded(): void
    {
        $this->message(['message_km' => 'សារពេលក្រោយ', 'published_at' => now()->addDay()]);
        $this->message(['message_km' => 'សារឥឡូវនេះ', 'published_at' => now()->subHour()]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertDontSee('សារពេលក្រោយ');
        $response->assertSee('សារឥឡូវនេះ');
    }

    public function test_messages_render_in_sort_order(): void
    {
        $this->message(['message_km' => 'ទីបី', 'sort_order' => 3]);
        $this->message(['message_km' => 'ទីមួយ', 'sort_order' => 1]);
        $this->message(['message_km' => 'ទីពីរ', 'sort_order' => 2]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSeeInOrder(['ទីមួយ', 'ទីពីរ', 'ទីបី']);
    }

    public function test_fallback_message_shows_when_no_active_messages_exist(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('សុខភាពល្អ');
    }

    public function test_a_single_active_message_renders_without_the_rotation_interval(): void
    {
        $this->message(['message_km' => 'សារតែមួយ']);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('សារតែមួយ');
        $response->assertSee('is-static', false);
        $response->assertDontSee('setInterval', false);
    }

    public function test_multiple_active_messages_render_with_the_rotation_interval(): void
    {
        $this->message(['message_km' => 'សារទីមួយ', 'sort_order' => 1]);
        $this->message(['message_km' => 'សារទីពីរ', 'sort_order' => 2]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('សារទីមួយ');
        $response->assertSee('សារទីពីរ');
        $response->assertSee('setInterval', false);
        $response->assertSee('6000', false);
    }

    public function test_whitelisted_icon_renders_its_svg(): void
    {
        $this->message(['icon' => 'Dumbbell', 'message_km' => 'លំហាត់ប្រាណ']);

        $response = $this->get('/');

        $response->assertOk()->assertSee('<svg', false);
    }

    public function test_an_invalid_icon_saved_directly_falls_back_to_heart_pulse_without_erroring(): void
    {
        // Bypasses HealthMessageRequest's whitelist validation on purpose,
        // to prove the *render* path is defensively safe too.
        $this->message(['icon' => 'NotARealIcon<script>', 'message_km' => 'សារមានរូបតំណាងខុស']);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('សារមានរូបតំណាងខុស');
        // The invalid icon key itself must never be echoed back verbatim —
        // only ever used as a lookup, with HeartPulse as the safe fallback.
        $response->assertDontSee('NotARealIcon<script>', false);
    }

    public function test_long_khmer_message_stays_within_the_single_line_ticker_container(): void
    {
        $this->message(['message_km' => str_repeat('សារវែងខ្លាំងណាស់ ', 15)]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('public-header-ticker', false);
    }

    public function test_contact_info_language_switch_and_hrm_button_are_unaffected(): void
    {
        $this->message(['message_km' => 'សារសាកល្បង']);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee(config('site.phone'));
        $response->assertSee(config('site.email'));
        $response->assertSee('ខ្មែរ', false);
        $response->assertSee('EN', false);
        if (config('site.hrm_url')) {
            $response->assertSee('ចូលប្រព័ន្ធ HRM');
        }
    }

    // --- Admin CRUD ---

    public function test_admin_can_create_a_message_and_it_invalidates_the_homepage_cache(): void
    {
        $this->get('/'); // warm the cache with zero messages
        Cache::put('health-messages.active', collect(), now()->addMinutes(15));

        $this->actingAs($this->admin())->post(route('admin.health-messages.store'), [
            'message_km' => 'សារថ្មី',
            'icon' => 'Apple',
            'sort_order' => 1,
            'is_active' => '1',
        ])->assertRedirect();

        $this->assertSame(1, HealthMessage::count());
        $this->get('/')->assertSee('សារថ្មី');
    }

    public function test_admin_request_rejects_an_icon_outside_the_whitelist(): void
    {
        $response = $this->actingAs($this->admin())->post(route('admin.health-messages.store'), [
            'message_km' => 'សារខុស',
            'icon' => 'NotWhitelisted',
            'is_active' => '1',
        ]);

        $response->assertSessionHasErrors('icon');
        $this->assertSame(0, HealthMessage::count());
    }

    public function test_unauthorized_user_cannot_manage_health_messages(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $editor = User::factory()->create(['is_active' => true]);
        $editor->assignRole('Reviewer');

        $this->actingAs($editor)->get(route('admin.health-messages.index'))->assertForbidden();
    }
}
