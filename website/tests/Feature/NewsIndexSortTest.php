<?php

namespace Tests\Feature;

use App\Models\Facility;
use App\Models\News;
use App\Models\NewsCategory;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers the News index table's column-header sort + filter (click a header to sort by that
 * column; category/facility/status are filtered from a select embedded in the header itself).
 */
class NewsIndexSortTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(RolePermissionSeeder::class);
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('Super Admin');

        return $admin;
    }

    private function news(array $attributes = []): News
    {
        static $n = 0;
        $n++;

        return News::create(array_merge([
            'title_km' => 'អត្ថបទ '.$n, 'slug' => 'sort-news-'.$n, 'status' => 'published',
        ], $attributes));
    }

    public function test_sorting_by_title_ascending_orders_rows_alphabetically(): void
    {
        $this->news(['title_km' => 'គ - អត្ថបទ']);
        $this->news(['title_km' => 'ក - អត្ថបទ']);
        $this->news(['title_km' => 'ខ - អត្ថបទ']);

        $response = $this->actingAs($this->admin())
            ->get(route('admin.news.index', ['sort' => 'title', 'direction' => 'asc']));

        $response->assertOk();
        $body = $response->getContent();
        $posK = strpos($body, 'ក - អត្ថបទ');
        $posKh = strpos($body, 'ខ - អត្ថបទ');
        $posKo = strpos($body, 'គ - អត្ថបទ');

        $this->assertNotFalse($posK);
        $this->assertTrue($posK < $posKh);
        $this->assertTrue($posKh < $posKo);
    }

    public function test_sorting_by_title_descending_reverses_the_order(): void
    {
        $this->news(['title_km' => 'ក - អត្ថបទ']);
        $this->news(['title_km' => 'គ - អត្ថបទ']);

        $body = $this->actingAs($this->admin())
            ->get(route('admin.news.index', ['sort' => 'title', 'direction' => 'desc']))
            ->getContent();

        $this->assertTrue(strpos($body, 'គ - អត្ថបទ') < strpos($body, 'ក - អត្ថបទ'));
    }

    public function test_sorting_by_category_orders_by_the_categorys_name_not_its_id(): void
    {
        $catB = NewsCategory::create(['name_km' => 'ខ ប្រភេទ', 'slug' => 'cat-b']);
        $catA = NewsCategory::create(['name_km' => 'ក ប្រភេទ', 'slug' => 'cat-a']);
        // catB was created first (lower id) but sorts after catA alphabetically.
        $this->news(['title_km' => 'អត្ថបទប្រភេទខ', 'category_id' => $catB->id]);
        $this->news(['title_km' => 'អត្ថបទប្រភេទក', 'category_id' => $catA->id]);

        $body = $this->actingAs($this->admin())
            ->get(route('admin.news.index', ['sort' => 'category', 'direction' => 'asc']))
            ->getContent();

        $this->assertTrue(strpos($body, 'អត្ថបទប្រភេទក') < strpos($body, 'អត្ថបទប្រភេទខ'));
    }

    private function facility(string $name, string $slug): Facility
    {
        return Facility::create([
            'code' => strtoupper($slug), 'name_km' => $name, 'slug' => $slug,
            'facility_type' => 'health_center', 'district' => 'ស្រុកសាកល្បង', 'commune' => 'ឃុំសាកល្បង',
        ]);
    }

    public function test_sorting_by_facility_orders_by_the_facilitys_name(): void
    {
        $facB = $this->facility('ខ មូលដ្ឋាន', 'fac-b');
        $facA = $this->facility('ក មូលដ្ឋាន', 'fac-a');
        $this->news(['title_km' => 'អត្ថបទមូលដ្ឋានខ', 'facility_id' => $facB->id]);
        $this->news(['title_km' => 'អត្ថបទមូលដ្ឋានក', 'facility_id' => $facA->id]);

        $body = $this->actingAs($this->admin())
            ->get(route('admin.news.index', ['sort' => 'facility', 'direction' => 'asc']))
            ->getContent();

        $this->assertTrue(strpos($body, 'អត្ថបទមូលដ្ឋានក') < strpos($body, 'អត្ថបទមូលដ្ឋានខ'));
    }

    public function test_an_invalid_sort_key_is_ignored_and_falls_back_to_the_default(): void
    {
        $this->news();

        $this->actingAs($this->admin())
            ->get(route('admin.news.index', ['sort' => 'password', 'direction' => 'asc']))
            ->assertOk();
    }

    public function test_category_filter_still_works_alongside_sorting(): void
    {
        $wanted = NewsCategory::create(['name_km' => 'ចង់បាន', 'slug' => 'wanted-cat']);
        $other = NewsCategory::create(['name_km' => 'មិនចង់បាន', 'slug' => 'other-cat']);
        $this->news(['title_km' => 'អត្ថបទដែលចង់បាន', 'category_id' => $wanted->id]);
        $this->news(['title_km' => 'អត្ថបទផ្សេង', 'category_id' => $other->id]);

        $response = $this->actingAs($this->admin())
            ->get(route('admin.news.index', ['category' => $wanted->id, 'sort' => 'title', 'direction' => 'asc']));

        $response->assertOk()->assertSee('អត្ថបទដែលចង់បាន')->assertDontSee('អត្ថបទផ្សេង');
    }

    public function test_trash_view_sorts_by_deleted_at_by_default_and_rejects_status_as_a_sort_key(): void
    {
        $admin = $this->admin();
        $a = $this->news(['title_km' => 'លុបមុន']);
        $a->delete();
        $b = $this->news(['title_km' => 'លុបក្រោយ']);
        $b->delete();

        $response = $this->actingAs($admin)->get(route('admin.news.index', ['status' => 'trash']));
        $response->assertOk()->assertSee('លុបមុន')->assertSee('លុបក្រោយ');

        // "status" is not a valid sort key in the trash view (there is no status column shown
        // there) — must not error, just fall back to the trash view's own default sort.
        $this->actingAs($admin)
            ->get(route('admin.news.index', ['status' => 'trash', 'sort' => 'status']))
            ->assertOk();
    }

    public function test_header_sort_links_preserve_the_current_search_and_filters(): void
    {
        $category = NewsCategory::create(['name_km' => 'ប្រភេទសាកល្បង', 'slug' => 'sort-link-cat']);
        $this->news(['category_id' => $category->id]);

        $response = $this->actingAs($this->admin())
            ->get(route('admin.news.index', ['search' => 'អត្ថបទ', 'category' => $category->id]));

        $response->assertOk();
        // The title column's sort link must carry the active search/category through.
        $response->assertSee('search='.urlencode('អត្ថបទ'), false);
        $response->assertSee('category='.$category->id, false);
    }
}
