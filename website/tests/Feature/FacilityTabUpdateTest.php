<?php

namespace Tests\Feature;

use App\Models\Facility;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FacilityTabUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_general_and_location_tabs_save_independently(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('Super Admin');
        $facility = Facility::create([
            'code' => 'TEST-64', 'name_km' => 'មណ្ឌលសុខភាពចំការលើ',
            'slug' => 'test-64', 'facility_type' => 'health_center',
            'district' => 'Original district', 'commune' => 'Original commune',
        ]);

        $this->actingAs($admin)->put(route('admin.facilities.update', $facility), [
            'tab' => 'general', 'name_km' => $facility->name_km, 'staff_count' => 9,
        ])->assertSessionHasNoErrors();
        $this->assertSame(9, (int) $facility->fresh()->staff_count);
        $this->assertSame('Original district', $facility->fresh()->district);

        $this->put(route('admin.facilities.update', $facility), [
            'tab' => 'location', 'district' => 'Updated district', 'commune' => 'Updated commune',
        ])->assertSessionHasNoErrors();
        $this->assertSame('Updated district', $facility->fresh()->district);
        $this->assertSame(9, (int) $facility->fresh()->staff_count);

        $this->put(route('admin.facilities.update', $facility), ['name_km' => ''])
            ->assertSessionHasErrors('name_km');
    }

    public function test_creation_still_requires_the_complete_identity_and_location(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('Super Admin');
        $this->actingAs($admin)->post(route('admin.facilities.store'), ['staff_count' => 9])
            ->assertSessionHasErrors(['code', 'name_km', 'slug', 'facility_type', 'district', 'commune']);
    }
}
