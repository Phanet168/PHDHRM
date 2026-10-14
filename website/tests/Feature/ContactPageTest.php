<?php

namespace Tests\Feature;

use App\Models\ContactMessage;
use App\Models\Facility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ContactPageTest extends TestCase
{
    use RefreshDatabase;

    private function facility(array $attributes = []): Facility
    {
        static $n = 0;
        $n++;

        return Facility::create(array_merge([
            'code' => 'F'.$n,
            'name_km' => 'មូលដ្ឋានសាកល្បង '.$n,
            'slug' => 'facility-'.$n,
            'facility_type' => 'health_center',
            'district' => 'ស្រុកសេសាន',
            'commune' => 'ឃុំសាកល្បង',
            'phone' => '074 210 '.str_pad((string) $n, 3, '0', STR_PAD_LEFT),
            'status' => 'published',
        ], $attributes));
    }

    // --- Page structure & summary cards ---

    public function test_contact_page_loads_with_summary_cards_directory_form_and_map(): void
    {
        config(['site.phone' => '074 111 222', 'site.email' => 'hello@example.org', 'site.address' => 'អាសយដ្ឋានសាកល្បង', 'site.hours' => 'ម៉ោងសាកល្បង']);

        $this->get(route('contact'))
            ->assertOk()
            ->assertSee('contact-summary', false)
            ->assertSee('href="tel:074111222"', false)
            ->assertSee('href="mailto:hello@example.org"', false)
            ->assertSee('អាសយដ្ឋានសាកល្បង')
            ->assertSee('ម៉ោងសាកល្បង')
            ->assertSee('id="hotline-directory"', false)
            ->assertSee('លេខទំនាក់ទំនងបន្ទាន់ និង Hotline')
            ->assertSee('action="'.route('contact.store').'"', false)
            ->assertSee('contact-map', false)
            ->assertSee('google.com/maps/search', false)
            ->assertSee('public-header', false)
            ->assertSee('public-footer', false)
            ->assertSee('<nav aria-label="breadcrumb"', false);
    }

    public function test_emergency_contacts_from_settings_are_still_listed(): void
    {
        config(['site.emergency_contacts' => [['label' => 'សង្គ្រោះបន្ទាន់សាកល្បង', 'phone' => '119'], ['label' => 'ផ្សេង', 'phone' => '012 345 678']]]);

        $this->get(route('contact'))->assertOk()
            ->assertSee('សង្គ្រោះបន្ទាន់សាកល្បង')
            ->assertSee('href="tel:119"', false)
            ->assertSee('href="tel:012345678"', false);
    }

    // --- Which facilities appear ---

    public function test_only_published_facilities_with_a_callable_phone_are_listed(): void
    {
        $this->facility(['name_km' => 'មានលេខទូរស័ព្ទ']);
        $this->facility(['name_km' => 'គ្មានលេខទូរស័ព្ទ', 'phone' => null]);
        $this->facility(['name_km' => 'លេខទទេ', 'phone' => '']);
        $this->facility(['name_km' => 'លេខគ្មានខ្ទង់', 'phone' => 'មិនមាន']);
        $this->facility(['name_km' => 'ព្រាងមានលេខ', 'status' => 'draft']);
        $this->facility(['name_km' => 'ទុកក្នុងឯកសារមានលេខ', 'status' => 'archived']);
        $deleted = $this->facility(['name_km' => 'ត្រូវបានលុបមានលេខ']);
        $deleted->delete();

        $this->get(route('contact'))->assertOk()
            ->assertSee('មានលេខទូរស័ព្ទ')
            ->assertDontSee('គ្មានលេខទូរស័ព្ទ')
            ->assertDontSee('លេខទទេ')
            ->assertDontSee('លេខគ្មានខ្ទង់')
            ->assertDontSee('ព្រាងមានលេខ')
            ->assertDontSee('ទុកក្នុងឯកសារមានលេខ')
            ->assertDontSee('ត្រូវបានលុបមានលេខ')
            ->assertSee('បង្ហាញ 1-1 នៃ 1 មូលដ្ឋានសុខាភិបាល');
    }

    public function test_rows_show_type_badge_district_place_and_correct_tel_links(): void
    {
        $this->facility(['name_km' => 'មន្ទីរពេទ្យសាកល្បង', 'facility_type' => 'referral_hospital', 'district' => 'ក្រុងសាកល្បង', 'commune' => 'សង្កាត់សាកល្បង', 'phone' => '+855 74 210 000']);

        $html = $this->get(route('contact'))->assertOk()->getContent();

        $this->assertStringContainsString('មន្ទីរពេទ្យសាកល្បង', $html);
        $this->assertStringContainsString('contact-badge is-referral_hospital', $html);
        $this->assertStringContainsString('មន្ទីរពេទ្យបង្អែក', $html);
        $this->assertStringContainsString('សង្កាត់សាកល្បង', $html);
        // Call button + phone number both dial the digits (and a leading +) only.
        $this->assertSame(2, substr_count($html, 'href="tel:+85574210000"'));
        $this->assertStringContainsString('aria-label="ហៅទូរស័ព្ទ៖ មន្ទីរពេទ្យសាកល្បង"', $html);
    }

    public function test_facilities_are_ordered_by_type_priority_then_name(): void
    {
        $this->facility(['name_km' => 'ខ ប៉ុស្តិ៍', 'facility_type' => 'health_post']);
        $this->facility(['name_km' => 'ខ មណ្ឌល', 'facility_type' => 'health_center']);
        $this->facility(['name_km' => 'ក មណ្ឌល', 'facility_type' => 'health_center']);
        $this->facility(['name_km' => 'ក មន្ទីរពេទ្យ', 'facility_type' => 'referral_hospital']);

        $html = $this->get(route('contact'))->getContent();
        $positions = array_map(fn ($name) => strpos($html, $name), ['ក មន្ទីរពេទ្យ', 'ក មណ្ឌល', 'ខ មណ្ឌល', 'ខ ប៉ុស្តិ៍']);

        $this->assertSame($positions, collect($positions)->sort()->values()->all());
    }

    // --- Search & filters ---

    public function test_search_matches_name_district_commune_and_phone(): void
    {
        $this->facility(['name_km' => 'ពេទ្យអាល់ហ្វា', 'district' => 'ស្រុកបេតា', 'commune' => 'ឃុំគាម៉ា', 'phone' => '011 222 333']);
        $this->facility(['name_km' => 'ពេទ្យឌែលតា', 'district' => 'ស្រុកអេសិលុង', 'commune' => 'ឃុំហ្សេតា', 'phone' => '099 888 777']);

        $this->get(route('contact', ['q' => 'អាល់ហ្វា']))->assertSee('ពេទ្យអាល់ហ្វា')->assertDontSee('ពេទ្យឌែលតា');
        $this->get(route('contact', ['q' => 'អេសិលុង']))->assertDontSee('ពេទ្យអាល់ហ្វា')->assertSee('ពេទ្យឌែលតា');
        $this->get(route('contact', ['q' => 'គាម៉ា']))->assertSee('ពេទ្យអាល់ហ្វា')->assertDontSee('ពេទ្យឌែលតា');
        $this->get(route('contact', ['q' => '099 888']))->assertDontSee('ពេទ្យអាល់ហ្វា')->assertSee('ពេទ្យឌែលតា');
    }

    public function test_facility_type_filter_narrows_the_list(): void
    {
        $this->facility(['name_km' => 'មន្ទីរពេទ្យក', 'facility_type' => 'referral_hospital']);
        $this->facility(['name_km' => 'មណ្ឌលខ', 'facility_type' => 'health_center']);
        $this->facility(['name_km' => 'ប៉ុស្តិ៍គ', 'facility_type' => 'health_post']);

        $this->get(route('contact', ['facility_type' => 'health_center']))->assertOk()
            ->assertSee('មណ្ឌលខ')->assertDontSee('មន្ទីរពេទ្យក')->assertDontSee('ប៉ុស្តិ៍គ')
            ->assertSee('<option value="health_center" selected>', false);
    }

    public function test_an_unknown_facility_type_is_ignored(): void
    {
        $this->facility(['name_km' => 'មណ្ឌលខ']);

        $this->get(route('contact', ['facility_type' => 'bogus']))->assertOk()->assertSee('មណ្ឌលខ');
    }

    public function test_district_filter_narrows_the_list(): void
    {
        $this->facility(['name_km' => 'នៅក្រុង', 'district' => 'ក្រុងស្ទឹងត្រែង']);
        $this->facility(['name_km' => 'នៅស្រុក', 'district' => 'ស្រុកសេសាន']);

        $this->get(route('contact', ['district' => 'ស្រុកសេសាន']))->assertOk()
            ->assertSee('នៅស្រុក')->assertDontSee('នៅក្រុង')
            ->assertSee('<option value="ស្រុកសេសាន" selected>', false);
    }

    public function test_filter_options_come_from_the_listed_data_only(): void
    {
        $this->facility(['facility_type' => 'health_center', 'district' => 'ស្រុកនៅក្នុងបញ្ជី']);
        // Not listed (no phone / not published), so neither its type nor district may become an option.
        $this->facility(['facility_type' => 'health_post', 'district' => 'ស្រុកគ្មានលេខ', 'phone' => null]);
        $this->facility(['facility_type' => 'referral_hospital', 'district' => 'ស្រុកព្រាង', 'status' => 'draft']);

        $html = $this->get(route('contact'))->assertOk()->getContent();
        preg_match('/<select name="facility_type".*?<\/select>/s', $html, $typeSelect);

        $this->assertStringContainsString('value="health_center"', $typeSelect[0]);
        $this->assertStringNotContainsString('value="health_post"', $typeSelect[0]);
        $this->assertStringNotContainsString('value="referral_hospital"', $typeSelect[0]);
        $this->assertStringContainsString('ស្រុកនៅក្នុងបញ្ជី', $html);
        $this->assertStringNotContainsString('ស្រុកគ្មានលេខ', $html);
        $this->assertStringNotContainsString('ស្រុកព្រាង', $html);
    }

    public function test_group_panel_counts_are_real_and_follow_search_and_type_filters(): void
    {
        $this->facility(['district' => 'ស្រុកក', 'facility_type' => 'health_center']);
        $this->facility(['district' => 'ស្រុកក', 'facility_type' => 'health_center']);
        $this->facility(['district' => 'ស្រុកក', 'facility_type' => 'health_post']);
        $this->facility(['district' => 'ស្រុកខ', 'facility_type' => 'health_center']);

        $all = $this->get(route('contact'))->getContent();
        $this->assertMatchesRegularExpression('/contact-group-name">ទាំងអស់<\/span>\s*<span class="contact-group-count">4</u', $all);
        $this->assertMatchesRegularExpression('/contact-group-name">ស្រុកក<\/span>\s*<span class="contact-group-count">3</u', $all);
        $this->assertMatchesRegularExpression('/contact-group-name">ស្រុកខ<\/span>\s*<span class="contact-group-count">1</u', $all);

        $centers = $this->get(route('contact', ['facility_type' => 'health_center']))->getContent();
        $this->assertMatchesRegularExpression('/contact-group-name">ទាំងអស់<\/span>\s*<span class="contact-group-count">3</u', $centers);
        $this->assertMatchesRegularExpression('/contact-group-name">ស្រុកក<\/span>\s*<span class="contact-group-count">2</u', $centers);
    }

    public function test_group_links_keep_search_and_type_but_drop_the_page(): void
    {
        $this->facility(['district' => 'ស្រុកក']);

        $html = $this->get(route('contact', ['q' => 'សាកល្បង', 'facility_type' => 'health_center', 'page' => 1]))->getContent();

        $this->assertMatchesRegularExpression('/href="[^"]*contact\?[^"]*district=[^"]*"/', html_entity_decode($html));
        $this->assertDoesNotMatchRegularExpression('/class="contact-group[^"]*"[^>]*href/', $html);
        preg_match('/<a href="([^"]*district=[^"]*)" class="contact-group/', $html, $link);
        $this->assertNotEmpty($link, 'district group link not found');
        $url = html_entity_decode($link[1]);
        $this->assertStringContainsString('facility_type=health_center', $url);
        $this->assertStringContainsString('q=', $url);
        $this->assertStringNotContainsString('page=', $url);
        $this->assertStringEndsWith('#hotline-directory', $url);
    }

    public function test_empty_state_replaces_the_list_when_nothing_matches(): void
    {
        $this->facility(['name_km' => 'មានតែមួយ']);

        $this->get(route('contact', ['q' => 'មិនមានទេពិតជាមិនមាន']))->assertOk()
            ->assertSee('មិនមានមូលដ្ឋានសុខាភិបាលដែលត្រូវនឹងការស្វែងរកទេ')
            ->assertSee('contact-empty', false)
            ->assertDontSee('contact-dir-list', false)
            ->assertDontSee('contact-pagination-summary', false);
    }

    // --- Pagination ---

    public function test_directory_paginates_ten_per_page_without_duplicates_and_keeps_filters_in_links(): void
    {
        for ($i = 1; $i <= 12; $i++) {
            $this->facility(['name_km' => sprintf('មូលដ្ឋានលំដាប់ %02d', $i)]);
        }
        $this->facility(['name_km' => 'ប៉ុស្តិ៍ផ្សេង', 'facility_type' => 'health_post']);

        $page1 = $this->get(route('contact', ['facility_type' => 'health_center']))->assertOk();
        $page1->assertSee('បង្ហាញ 1-10 នៃ 12 មូលដ្ឋានសុខាភិបាល')->assertSee('ទំព័រ 1 នៃ 2')->assertDontSee('ប៉ុស្តិ៍ផ្សេង');
        $next = html_entity_decode($page1->getContent());
        $this->assertMatchesRegularExpression('/href="[^"]*facility_type=health_center[^"]*page=2[^"]*#hotline-directory"/', $next);

        $page2 = $this->get(route('contact', ['facility_type' => 'health_center', 'page' => 2]))->assertOk();
        $page2->assertSee('បង្ហាញ 11-12 នៃ 12 មូលដ្ឋានសុខាភិបាល');

        preg_match_all('/មូលដ្ឋានលំដាប់ \d\d/u', $page1->getContent(), $first);
        preg_match_all('/មូលដ្ឋានលំដាប់ \d\d/u', $page2->getContent(), $second);
        $this->assertCount(10, array_unique($first[0]));
        $this->assertCount(2, array_unique($second[0]));
        $this->assertEmpty(array_intersect(array_unique($first[0]), array_unique($second[0])));
    }

    public function test_serial_numbers_continue_across_pages(): void
    {
        for ($i = 1; $i <= 11; $i++) {
            $this->facility();
        }

        $html = $this->get(route('contact', ['page' => 2]))->getContent();

        $this->assertMatchesRegularExpression('/<span class="contact-dir-no">11<\/span>/', $html);
    }

    public function test_query_count_does_not_grow_with_the_number_of_facilities(): void
    {
        for ($i = 1; $i <= 3; $i++) {
            $this->facility();
        }
        $this->get(route('contact'))->assertOk(); // warm the shared menu/ticker caches

        DB::enableQueryLog();
        $this->get(route('contact'))->assertOk();
        $few = count(DB::getQueryLog());

        for ($i = 1; $i <= 30; $i++) {
            $this->facility();
        }
        DB::flushQueryLog();
        $this->get(route('contact'))->assertOk();

        $this->assertSame($few, count(DB::getQueryLog()), 'directory must not run per-row queries');
    }

    // --- Existing contact form is unchanged ---

    public function test_contact_form_still_stores_a_message_and_shows_the_status(): void
    {
        $this->post(route('contact.store'), [
            'name' => 'អ្នកសាកល្បង', 'phone' => '012 000 111', 'email' => 'a@example.org', 'subject' => 'ប្រធានបទ', 'message' => 'សារសាកល្បង',
        ])->assertRedirect(route('contact'));

        $this->assertDatabaseHas('contact_messages', ['name' => 'អ្នកសាកល្បង', 'message' => 'សារសាកល្បង']);
        $this->get(route('contact'))->assertSee('សារបស់អ្នកត្រូវបានផ្ញើដោយជោគជ័យ។');
    }

    public function test_contact_form_validation_is_unchanged(): void
    {
        $this->post(route('contact.store'), ['name' => '', 'message' => '', 'email' => 'not-an-email'])
            ->assertSessionHasErrors(['name', 'message', 'email']);

        $this->assertSame(0, ContactMessage::count());
    }

    public function test_validation_errors_are_shown_next_to_the_form(): void
    {
        $this->from(route('contact'))->post(route('contact.store'), ['name' => '', 'message' => ''])->assertRedirect(route('contact'));

        $this->followingRedirects()->from(route('contact'))->post(route('contact.store'), ['name' => '', 'message' => ''])
            ->assertOk()->assertSee('contact-error', false);
    }
}
