<?php

namespace Database\Seeders;

use App\Models\AboutItem;
use App\Models\AboutOrgUnit;
use App\Models\AboutSection;
use App\Models\Facility;
use App\Models\FacilityLeader;
use App\Models\LegalDocument;
use App\Models\PageBanner;
use Illuminate\Database\Seeder;

/**
 * DEMO ABOUT CONTENT — placeholder records that only make the About page layout
 * visible. Every row is flagged is_demo (shown as "DEMO" in Admin) and can be
 * edited, hidden or deleted there. Nothing here is an official fact.
 *
 * Idempotent: a section or group that already has rows (including hidden or
 * trashed ones) is left alone, so re-running never duplicates or resurrects content.
 */
class AboutDemoSeeder extends Seeder
{
    private const NOTE = 'ខ្លឹមសារគំរូ — សូមជំនួសដោយព័ត៌មានផ្លូវការនៅក្នុង Admin › អំពីមន្ទីរ។';

    public function run(): void
    {
        $this->sections();
        $this->cards();
        $this->timeline();
        $this->leaders();
        $this->organization();
        $this->documents();
    }

    private function sections(): void
    {
        AboutSection::firstOrCreate(['key' => 'hero'], [
            'title' => 'អំពីមន្ទីរ',
            'subtitle' => config('site.name_km'),
            'body' => config('site.tagline'),
            'is_active' => true,
            'is_demo' => true,
        ]);

        AboutSection::firstOrCreate(['key' => 'history'], [
            'title' => 'ប្រវត្តិ និងបេសកកម្ម',
            'body' => '<p>'.e(config('about.history')).'</p><p>['.self::NOTE.']</p>',
            // Reuses the image already chosen for the About banner, if any.
            'media_id' => PageBanner::where('page_key', 'about')->value('media_id'),
            'image_position' => 'right',
            'is_active' => true,
            'is_demo' => true,
        ]);

        AboutSection::firstOrCreate(['key' => 'mission'], [
            'title' => 'បេសកកម្ម',
            'body' => config('about.mission'),
            'icon_key' => 'target',
            'is_active' => true,
            'is_demo' => true,
        ]);
    }

    private function cards(): void
    {
        if (AboutItem::withTrashed()->where('group', 'cards')->exists()) {
            return;
        }

        $cards = [
            ['ចក្ខុវិស័យ', 'vision', 'eye', 'blue'],
            ['បេសកកម្ម', 'mission', 'target', 'green'],
            ['តួនាទី និងភារកិច្ច', 'roles', 'briefcase', 'gold'],
            ['គោលតម្លៃ', 'values', 'shield', 'purple'],
        ];
        foreach ($cards as $order => [$title, $key, $icon, $accent]) {
            AboutItem::create([
                'group' => 'cards', 'title' => $title, 'body' => config("about.$key"),
                'icon_key' => $icon, 'accent' => $accent, 'sort_order' => $order, 'is_active' => true, 'is_demo' => true,
            ]);
        }
    }

    private function timeline(): void
    {
        if (AboutItem::withTrashed()->where('group', 'timeline')->exists()) {
            return;
        }

        // Placeholder labels on purpose: no invented historical years or milestones.
        $icons = ['calendar', 'hospital', 'users', 'trending-up'];
        foreach (['១', '២', '៣', '៤'] as $order => $number) {
            AboutItem::create([
                'group' => 'timeline', 'date_label' => "ឆ្នាំ {$number}", 'title' => "ព្រឹត្តិការណ៍គំរូទី {$number}",
                'body' => self::NOTE, 'icon_key' => $icons[$order], 'sort_order' => $order, 'is_active' => true, 'is_demo' => true,
            ]);
        }
    }

    private function leaders(): void
    {
        $department = FacilityLeader::withTrashed()->department();

        if ($department->exists()) {
            // Placeholder rows from FacilityLeaderSeeder carry this generic name; flag them so Admin shows DEMO.
            FacilityLeader::department()->where('name', 'វេជ្ជបណ្ឌិត ថ្នាក់ដឹកនាំ')->update(['is_demo' => true]);

            return;
        }

        $titles = ['ប្រធានមន្ទីរ', 'អនុប្រធានមន្ទីរ', 'អនុប្រធានមន្ទីរ', 'ប្រធានការិយាល័យ'];
        foreach (['១', '២', '៣', '៤'] as $order => $number) {
            FacilityLeader::create([
                'facility_id' => null, 'name' => "ថ្នាក់ដឹកនាំទី {$number}", 'title' => $titles[$order],
                'sort_order' => $order, 'is_active' => true, 'is_featured' => $order === 0, 'is_demo' => true,
            ]);
        }
    }

    private function organization(): void
    {
        if (AboutOrgUnit::withTrashed()->exists()) {
            return;
        }

        $order = 0;
        foreach (['១', '២', '៣'] as $number) {
            AboutOrgUnit::create(['name' => "ការិយាល័យគំរូទី {$number}", 'type' => 'office', 'description' => self::NOTE, 'sort_order' => $order++, 'is_active' => true, 'is_demo' => true]);
        }

        // Real hospitals / OD offices already published under Facilities: linked, not re-typed.
        Facility::published()->whereIn('facility_type', array_keys(AboutOrgUnit::FACILITY_TYPE_MAP))->orderBy('name_km')->get()
            ->each(function (Facility $facility) use (&$order) {
                AboutOrgUnit::create([
                    'facility_id' => $facility->id, 'name' => $facility->name_km,
                    'type' => AboutOrgUnit::FACILITY_TYPE_MAP[$facility->facility_type], 'sort_order' => $order++, 'is_active' => true,
                ]);
            });
    }

    private function documents(): void
    {
        if (AboutItem::withTrashed()->where('group', 'documents')->exists()) {
            return;
        }

        LegalDocument::published()->latest('id')->take(3)->get()->filter->hasDownload()->values()
            ->each(fn (LegalDocument $document, int $order) => AboutItem::create([
                'group' => 'documents', 'title' => $document->title, 'legal_document_id' => $document->id,
                'sort_order' => $order, 'is_active' => true, 'is_demo' => true,
            ]));
    }
}
