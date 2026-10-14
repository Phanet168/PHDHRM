<?php

namespace Tests\Feature;

use App\Models\HeroSlide;
use App\Models\MediaFolder;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HeroSlideMediaFolderTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(RolePermissionSeeder::class);
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('Super Admin');

        return $admin;
    }

    public function test_creating_a_slide_files_its_image_into_its_own_dedicated_folder(): void
    {
        Storage::fake('public');

        $response = $this->actingAs($this->admin())->post(route('admin.hero-slides.store'), [
            'title_km' => 'ស្លាយសាកល្បង',
            'image' => UploadedFile::fake()->image('slide.jpg'),
        ]);

        $response->assertRedirect();
        $slide = HeroSlide::where('title_km', 'ស្លាយសាកល្បង')->firstOrFail();
        $this->assertNotEmpty($slide->image);
        $this->assertNotSame('', $slide->image);

        $folder = MediaFolder::where('owner_type', HeroSlide::class)->where('owner_id', $slide->id)->first();
        $this->assertNotNull($folder);
        $this->assertSame("#{$slide->id} — ស្លាយសាកល្បង", $folder->name);
        $this->assertSame('Hero Slider', $folder->parent->name);
    }

    public function test_replacing_the_image_on_update_reuses_the_same_slide_folder(): void
    {
        Storage::fake('public');
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.hero-slides.store'), [
            'title_km' => 'ស្លាយទីមួយ',
            'image' => UploadedFile::fake()->image('first.jpg'),
        ]);
        $slide = HeroSlide::firstOrFail();

        $this->actingAs($admin)->put(route('admin.hero-slides.update', $slide), [
            'title_km' => 'ស្លាយទីមួយ',
            'image' => UploadedFile::fake()->image('second.jpg'),
        ]);

        $this->assertSame(
            1,
            MediaFolder::where('owner_type', HeroSlide::class)->where('owner_id', $slide->id)->count()
        );
    }
}
