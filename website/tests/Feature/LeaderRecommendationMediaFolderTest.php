<?php

namespace Tests\Feature;

use App\Models\LeaderRecommendation;
use App\Models\MediaFolder;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LeaderRecommendationMediaFolderTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(RolePermissionSeeder::class);
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('Super Admin');

        return $admin;
    }

    public function test_creating_a_recommendation_with_an_image_files_it_into_its_own_dedicated_folder(): void
    {
        Storage::fake('public');

        $response = $this->actingAs($this->admin())->post(route('admin.leader-recommendations.store'), [
            'title_km' => 'អនុសាសន៍សាកល្បង',
            'leader_name_km' => 'អ្នកដឹកនាំសាកល្បង',
            'image_file' => UploadedFile::fake()->image('leader.jpg'),
        ]);

        $response->assertRedirect();
        $recommendation = LeaderRecommendation::where('title_km', 'អនុសាសន៍សាកល្បង')->firstOrFail();
        $this->assertNotNull($recommendation->media_id);

        $folder = MediaFolder::where('owner_type', LeaderRecommendation::class)->where('owner_id', $recommendation->id)->first();
        $this->assertNotNull($folder);
        $this->assertSame("#{$recommendation->id} — អនុសាសន៍សាកល្បង", $folder->name);
        $this->assertSame('អនុសាសន៍ថ្នាក់ដឹកនាំ', $folder->parent->name);
    }

    public function test_replacing_the_image_on_update_reuses_the_same_folder(): void
    {
        Storage::fake('public');
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.leader-recommendations.store'), [
            'title_km' => 'អនុសាសន៍ទីមួយ',
            'leader_name_km' => 'អ្នកដឹកនាំ',
            'image_file' => UploadedFile::fake()->image('first.jpg'),
        ]);
        $recommendation = LeaderRecommendation::firstOrFail();

        $this->actingAs($admin)->put(route('admin.leader-recommendations.update', $recommendation), [
            'title_km' => 'អនុសាសន៍ទីមួយ',
            'leader_name_km' => 'អ្នកដឹកនាំ',
            'image_file' => UploadedFile::fake()->image('second.jpg'),
        ]);

        $this->assertSame(
            1,
            MediaFolder::where('owner_type', LeaderRecommendation::class)->where('owner_id', $recommendation->id)->count()
        );
    }
}
