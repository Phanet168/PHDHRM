<?php

namespace App\Services;

use App\Models\AboutItem;
use App\Models\AboutOrgUnit;
use App\Models\AboutSection;
use App\Models\Facility;
use App\Models\FacilityLeader;
use App\Models\PageBanner;
use App\Support\AboutIcon;
use Illuminate\Support\Collection;

/**
 * Builds the public About page from the Admin-managed About records.
 *
 * A group counts as "managed" once it has any row (active, hidden or trashed);
 * from then on only its active rows are shown, so hiding or deleting demo
 * content really hides it. Groups never touched in Admin fall back to the older
 * Pages-block adapter (AboutPageContent) so previously published content stays.
 */
class AboutContent
{
    private const CARD_ANCHORS = [
        'ចក្ខុវិស័យ' => 'vision',
        'បេសកកម្ម' => 'mission',
        'តួនាទី និងភារកិច្ច' => 'roles',
        'តួនាទី' => 'roles',
        'គោលតម្លៃ' => 'values',
    ];

    public function __construct(private AboutPageContent $legacy) {}

    public function load(): array
    {
        $legacy = $this->legacy->load();
        $sections = AboutSection::with('media')->get()->keyBy('key');
        $items = AboutItem::withTrashed()->with(['media', 'legalDocument'])->ordered()->get()->groupBy('group');

        $data = [
            'legacy' => $legacy,
            'hero' => $this->hero($sections->get('hero'), $legacy),
            'history' => $this->history($sections->get('history'), $legacy),
            'mission' => $this->mission($sections->get('mission'), $legacy),
            'cards' => $this->cards($items->get('cards'), $legacy),
            'timeline' => $this->timeline($items->get('timeline'), $legacy),
            'documents' => $this->documents($items->get('documents'), $legacy),
            'leaders' => FacilityLeader::department()->where('is_active', true)->with('portrait')->orderBy('sort_order')->orderBy('id')->get(),
            'organization' => $this->organization(),
            'map' => $this->map($legacy),
        ];

        // A mission card already anchors #mission; otherwise the highlight takes it.
        $data['missionAnchor'] = $data['cards']->contains('anchor', 'mission') ? null : 'mission';

        return $data;
    }

    private function hero(?AboutSection $hero, array $legacy): ?array
    {
        if ($hero && ! $hero->is_active) {
            return null;
        }

        return [
            'title' => $hero?->title ?: 'អំពីមន្ទីរ',
            'subtitle' => $hero ? $hero->subtitle : ($legacy['page']?->excerpt ?: config('site.name_km')),
            'intro' => $hero?->body,
            'image' => PageBanner::urlFor('about'),
        ];
    }

    private function history(?AboutSection $history, array $legacy): ?array
    {
        if ($history) {
            if (! $history->is_active || (blank(strip_tags((string) $history->body)) && ! $history->media)) {
                return null;
            }

            return [
                'title' => $history->title ?: 'ប្រវត្តិ និងបេសកកម្ម',
                'html' => $history->body,
                'image' => $history->media?->isImage() ? $history->media->url : null,
                'alt' => $history->media?->alt_text ?: ($history->title ?: config('site.name_km')),
                'position' => $history->image_position === 'left' ? 'left' : 'right',
                'legacy' => false,
            ];
        }

        $page = $legacy['page'];
        if ($page && ! $page->content && $legacy['sections']['history']->isEmpty()) {
            return null;
        }

        $blockImage = $legacy['sections']['history']->contains(fn ($block) => in_array($block->block_type, ['image', 'image_text', 'gallery']));

        return [
            'title' => 'ប្រវត្តិ និងបេសកកម្ម',
            'html' => null,
            'image' => $blockImage ? null : ($page?->featuredMedia?->isImage() ? $page->featuredMedia->url : PageBanner::urlFor('about')),
            'alt' => $page?->featuredMedia?->alt_text ?: config('site.name_km'),
            'position' => 'right',
            'legacy' => true,
        ];
    }

    private function mission(?AboutSection $mission, array $legacy): ?array
    {
        if ($mission) {
            return $mission->is_active && filled($mission->body)
                ? ['title' => $mission->title ?: 'បេសកកម្ម', 'text' => $mission->body, 'author' => null, 'icon' => AboutIcon::key($mission->icon_key, 'target')]
                : null;
        }

        $text = $legacy['quote']?->get('text') ?: ($legacy['cards']['mission']['text'] ?? null);

        return $text ? ['title' => 'បេសកកម្ម', 'text' => $text, 'author' => $legacy['quote']?->get('author'), 'icon' => 'target'] : null;
    }

    private function cards(?Collection $rows, array $legacy): Collection
    {
        if ($rows) {
            $used = [];

            return $rows->filter(fn (AboutItem $item) => $item->is_active && ! $item->trashed())->values()->map(function (AboutItem $item) use (&$used) {
                $anchor = self::CARD_ANCHORS[trim($item->title)] ?? 'card-'.$item->id;
                $anchor = isset($used[$anchor]) ? 'card-'.$item->id : $anchor;
                $used[$anchor] = true;

                return [
                    'anchor' => $anchor,
                    'title' => $item->title,
                    'text' => $item->body,
                    'icon' => AboutIcon::key($item->icon_key),
                    'accent' => AboutIcon::accent($item->accent),
                    'link' => $item->link_url,
                ];
            });
        }

        $icons = ['vision' => 'eye', 'mission' => 'target', 'roles' => 'briefcase', 'values' => 'shield'];
        $accents = ['vision' => 'blue', 'mission' => 'green', 'roles' => 'gold', 'values' => 'purple'];

        return collect(['vision', 'mission', 'roles', 'values'])->filter(fn ($key) => $legacy['cards']->has($key))
            ->map(fn ($key) => [
                'anchor' => $key,
                'title' => $legacy['cards'][$key]['title'],
                'text' => $legacy['cards'][$key]['text'],
                'icon' => $icons[$key],
                'accent' => $accents[$key],
                'link' => $legacy['cards'][$key]['link_url'] ?? null,
            ])->values();
    }

    private function timeline(?Collection $rows, array $legacy): Collection
    {
        if ($rows) {
            return $rows->filter(fn (AboutItem $item) => $item->is_active && ! $item->trashed())->values()
                ->map(fn (AboutItem $item) => ['date' => $item->date_label, 'title' => $item->title, 'text' => $item->body, 'icon' => AboutIcon::key($item->icon_key, 'calendar')]);
        }

        return $legacy['timeline']->map(fn ($event) => ['date' => $event['year'], 'title' => $event['title'], 'text' => $event['text'], 'icon' => 'calendar']);
    }

    private function documents(?Collection $rows, array $legacy): Collection
    {
        if ($rows) {
            return $rows->filter(fn (AboutItem $item) => $item->is_active && ! $item->trashed())
                ->map(fn (AboutItem $item) => ($download = $item->download()) ? ['title' => $item->title, ...$download] : null)
                ->filter()->values();
        }

        return $legacy['files']->map(fn ($file) => [
            'title' => $file['title'],
            'url' => $file['media']->url,
            'icon' => $file['media']->iconType(),
            'type' => $file['media']->typeLabel(),
            'size' => $file['media']->formattedSize(),
            'external' => false,
        ]);
    }

    /**
     * @return Collection<int, array{type: string, label: string, icon: string, units: Collection}>
     */
    private function organization(): Collection
    {
        if (AboutOrgUnit::withTrashed()->exists()) {
            $units = AboutOrgUnit::active()->with('facility')->orderBy('sort_order')->orderBy('id')->get();
            $childrenOf = $units->groupBy(fn ($unit) => $unit->parent_id ?? 0);
            $activeIds = $units->pluck('id')->flip();
            // A child whose parent is hidden or deleted surfaces at the top level instead of vanishing.
            $roots = $units->filter(fn ($unit) => ! $unit->parent_id || ! $activeIds->has($unit->parent_id));
            $shape = fn ($unit) => [
                'name' => $unit->name,
                'description' => $unit->description,
                'url' => $unit->publicUrl(),
                'children' => ($childrenOf[$unit->id] ?? collect())->map(fn ($child) => ['name' => $child->name, 'description' => $child->description, 'url' => $child->publicUrl(), 'children' => collect()])->values(),
            ];

            return collect(AboutOrgUnit::TYPES)->map(fn ($label, $type) => [
                'type' => $type,
                'label' => $label,
                'icon' => AboutOrgUnit::TYPE_ICONS[$type],
                'units' => $roots->where('type', $type)->map($shape)->values(),
            ])->filter(fn ($group) => $group['units']->isNotEmpty())->values();
        }

        // Not yet managed in Admin: the published hospitals and OD offices, as before.
        return Facility::published()->whereIn('facility_type', array_keys(AboutOrgUnit::FACILITY_TYPE_MAP))
            ->orderBy('name_km')->get()->groupBy('facility_type')
            ->map(fn ($facilities, $facilityType) => [
                'type' => AboutOrgUnit::FACILITY_TYPE_MAP[$facilityType],
                'label' => Facility::typeLabel($facilityType),
                'icon' => AboutOrgUnit::TYPE_ICONS[AboutOrgUnit::FACILITY_TYPE_MAP[$facilityType]],
                'units' => $facilities->map(fn ($facility) => ['name' => $facility->name_km, 'description' => null, 'url' => route('facilities.show', $facility), 'children' => collect()])->values(),
            ])->values();
    }

    private function map(array $legacy): ?array
    {
        $lat = config('site.map_latitude');
        $lng = config('site.map_longitude');
        if (is_numeric($lat) && is_numeric($lng) && abs((float) $lat) <= 90 && abs((float) $lng) <= 180) {
            return ['lat' => (float) $lat, 'lng' => (float) $lng, 'zoom' => (int) (config('site.map_zoom') ?: 15), 'title' => config('site.name_km')];
        }

        $block = $legacy['map'];

        return $block ? ['lat' => $block->get('lat'), 'lng' => $block->get('lng'), 'zoom' => $block->get('zoom', 14), 'title' => $block->get('marker_title') ?: config('site.name_km')] : null;
    }
}
