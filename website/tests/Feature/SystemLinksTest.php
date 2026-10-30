<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Providers\AppServiceProvider;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The header's sign-in buttons to the systems beside the website: HRM and drugs management. */
class SystemLinksTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(RolePermissionSeeder::class);
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('Super Admin');

        return $admin;
    }

    private function settings(array $values): void
    {
        foreach ($values as $key => $value) {
            Setting::set($key, $value);
        }
        $this->app->getProvider(AppServiceProvider::class)->boot();
    }

    public function test_the_drugs_system_sits_next_to_the_hrm_system_on_desktop_and_mobile(): void
    {
        $this->settings(['hrm_url' => '/login', 'drugs_url' => '/drugs/login']);

        $html = $this->get('/')->assertOk()
            ->assertSeeInOrder(['ចូលប្រព័ន្ធ HRM', 'ចូលប្រព័ន្ធឱសថ'])
            ->getContent();
        $this->assertSame(2, substr_count($html, 'href="/drugs/login"'));   // top bar and mobile menu
        $this->assertSame(2, substr_count($html, 'href="/login"'));
    }

    public function test_no_drugs_button_without_an_address(): void
    {
        $this->settings(['hrm_url' => '/login', 'drugs_url' => null]);

        $this->get('/')->assertOk()->assertSee('ចូលប្រព័ន្ធ HRM')->assertDontSee('ចូលប្រព័ន្ធឱសថ');
    }

    public function test_admin_sets_full_or_same_server_addresses_only(): void
    {
        $admin = $this->admin();
        $form = ['org_name_km' => 'មន្ទីរសុខាភិបាល'];

        $this->actingAs($admin)->put(route('admin.settings.update'), $form + ['hrm_url' => '/login', 'drugs_url' => 'https://drugs.example.org/login'])
            ->assertSessionHasNoErrors();
        $this->assertSame(['/login', 'https://drugs.example.org/login'], [Setting::get('hrm_url'), Setting::get('drugs_url')]);

        $this->actingAs($admin)->put(route('admin.settings.update'), $form + ['drugs_url' => 'javascript:alert(1)'])
            ->assertSessionHasErrors('drugs_url');
        $this->assertSame('https://drugs.example.org/login', Setting::get('drugs_url'));
    }
}
