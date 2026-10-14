<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AboutItem;
use App\Models\AboutOrgUnit;
use App\Models\AboutSection;
use App\Models\AuditLog;
use App\Models\FacilityLeader;
use App\Models\Media;
use App\Models\PageBanner;
use App\Models\Setting;
use App\Services\HtmlSanitizer;
use App\Services\MediaManagerService;
use App\Support\AboutIcon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** About-page overview, the singleton sections (hero / history / mission) and contact + map settings. */
class AboutController extends Controller
{
    /** Contact keys shared with the header, footer and contact page (same settings rows). */
    public const CONTACT_KEYS = ['phone', 'email', 'address', 'office_hours', 'website_url', 'map_url', 'map_latitude', 'map_longitude', 'map_zoom'];

    public function index(): View
    {
        $this->authorize('about.manage');

        $sections = AboutSection::all()->keyBy('key');
        $items = AboutItem::withTrashed()->get()->groupBy('group');
        $banner = PageBanner::where('page_key', 'about')->first();
        $leaders = FacilityLeader::department()->withTrashed()->get();
        $units = AboutOrgUnit::withTrashed()->get();
        $settings = Setting::whereIn('key', self::CONTACT_KEYS)->get();

        $itemCard = function (string $group) use ($items) {
            $rows = ($items[$group] ?? collect())->whereNull('deleted_at');

            return ['active' => $rows->where('is_active', true)->count(), 'total' => $rows->count(), 'updated' => $rows->max('updated_at'), 'demo' => $rows->where('is_demo', true)->count()];
        };
        $sectionCard = fn (array $keys) => [
            'active' => $sections->only($keys)->where('is_active', true)->count(),
            'total' => $sections->only($keys)->count(),
            'updated' => $sections->only($keys)->max('updated_at'),
            'demo' => $sections->only($keys)->where('is_demo', true)->count(),
        ];
        $liveLeaders = $leaders->whereNull('deleted_at');
        $liveUnits = $units->whereNull('deleted_at');
        $mapReady = is_numeric(config('site.map_latitude')) && is_numeric(config('site.map_longitude'));

        $cards = [
            ['title' => 'ផ្ទាំងខាងលើ (Hero)', 'icon' => '▬', 'route' => route('admin.about.sections.edit', 'hero'), ...$sectionCard(['hero']), 'updated' => collect([$sections['hero']->updated_at ?? null, $banner?->updated_at])->filter()->max()],
            ['title' => 'ប្រវត្តិ និងបេសកកម្ម', 'icon' => '▤', 'route' => route('admin.about.sections.edit', 'history'), ...$sectionCard(['history', 'mission'])],
            ['title' => 'ចក្ខុវិស័យ / បេសកកម្ម / តួនាទី / គោលតម្លៃ', 'icon' => '◎', 'route' => route('admin.about.items.index', 'cards'), ...$itemCard('cards')],
            ['title' => 'ប្រវត្តិ និងការអភិវឌ្ឍ', 'icon' => '⋯', 'route' => route('admin.about.items.index', 'timeline'), ...$itemCard('timeline')],
            ['title' => 'ថ្នាក់ដឹកនាំ', 'icon' => '☺', 'route' => route('admin.about.leaders.index'), 'active' => $liveLeaders->where('is_active', true)->count(), 'total' => $liveLeaders->count(), 'updated' => $liveLeaders->max('updated_at'), 'demo' => $liveLeaders->where('is_demo', true)->count()],
            ['title' => 'រចនាសម្ព័ន្ធអង្គភាព', 'icon' => '▦', 'route' => route('admin.about.organization.index'), 'active' => $liveUnits->where('is_active', true)->count(), 'total' => $liveUnits->count(), 'updated' => $liveUnits->max('updated_at'), 'demo' => $liveUnits->where('is_demo', true)->count()],
            ['title' => 'ឯកសារពាក់ព័ន្ធ', 'icon' => '▥', 'route' => route('admin.about.items.index', 'documents'), ...$itemCard('documents')],
            ['title' => 'ទីតាំង និងទំនាក់ទំនង', 'icon' => '⌖', 'route' => route('admin.about.contact.edit'), 'active' => $mapReady ? 1 : 0, 'total' => 1, 'updated' => $settings->max('updated_at'), 'demo' => 0, 'note' => $mapReady ? 'ផែនទីបានកំណត់រួច' : 'មិនទាន់កំណត់ទីតាំងផែនទី'],
        ];

        return view('admin.about.index', compact('cards'));
    }

    public function editSection(string $key): View
    {
        $this->authorize('about.manage');

        $section = AboutSection::for($key)->loadMissing('media', 'editor');
        $banner = $key === 'hero' ? PageBanner::firstOrNew(['page_key' => 'about'])->loadMissing('media') : null;
        $mission = $key === 'history' ? AboutSection::for('mission') : null;

        return view('admin.about.section', [
            'key' => $key,
            'section' => $section,
            'mission' => $mission,
            'banner' => $banner,
            'folderId' => app(MediaManagerService::class)->resolveFolderForCollection('about'),
        ]);
    }

    public function updateSection(Request $request, string $key): RedirectResponse
    {
        $this->authorize('about.manage');
        $section = AboutSection::for($key);

        $rules = match ($key) {
            'hero' => [
                'title' => ['required', 'string', 'max:255'],
                'subtitle' => ['nullable', 'string', 'max:500'],
                'body' => ['nullable', 'string', 'max:300'],
                'media_id' => ['nullable', 'integer'],
            ],
            'history' => [
                'title' => ['required', 'string', 'max:255'],
                'body' => ['nullable', 'string', 'max:20000'],
                'media_id' => ['nullable', 'integer'],
                'image_position' => ['required', Rule::in(['left', 'right'])],
                'sort_order' => ['nullable', 'integer', 'min:0'],
            ],
            'mission' => [
                'title' => ['required', 'string', 'max:255'],
                'body' => ['required', 'string', 'max:1000'],
                'icon_key' => ['required', Rule::in(array_keys(AboutIcon::ICONS))],
                'sort_order' => ['nullable', 'integer', 'min:0'],
            ],
        };
        $data = $request->validate($rules);
        $data['is_active'] = $request->boolean('is_active');
        $data['updated_by'] = auth()->id();
        $data['is_demo'] = false;

        if ($key === 'history') {
            $data['body'] = HtmlSanitizer::clean($data['body'] ?? null);
            $data['media_id'] = $this->imageId($request->input('media_id'));
        } elseif ($key === 'hero') {
            // The hero image is the existing "about" page banner, not a second copy.
            PageBanner::updateOrCreate(['page_key' => 'about'], ['media_id' => $this->imageId($request->input('media_id'))]);
            unset($data['media_id']);
        } else {
            $data['body'] = strip_tags($data['body']);
        }

        $wasActive = $section->exists ? $section->is_active : null;
        $section->fill($data)->save();

        AuditLog::record($section->wasRecentlyCreated ? 'created' : 'updated', 'AboutSection', $section->id, 'កែប្រែផ្នែកអំពីមន្ទីរ៖ '.AboutSection::KEYS[$key]);
        if ($wasActive !== null && $wasActive !== $section->is_active) {
            AuditLog::record($section->is_active ? 'published' : 'unpublished', 'AboutSection', $section->id, ($section->is_active ? 'បើកប្រើ' : 'បិទប្រើ').'ផ្នែក៖ '.AboutSection::KEYS[$key]);
        }

        $back = $key === 'mission' ? 'history' : $key;

        return redirect()->route('admin.about.sections.edit', $back)->with('status', 'បានរក្សាទុកដោយជោគជ័យ។');
    }

    public function editContact(): View
    {
        $this->authorize('about.manage');

        return view('admin.about.contact', ['settings' => Setting::allSettings()]);
    }

    public function updateContact(Request $request): RedirectResponse
    {
        $this->authorize('about.manage');
        // These rows also drive the header, footer and contact page.
        $this->authorize('settings.manage');

        $data = $request->validate([
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'office_hours' => ['nullable', 'string', 'max:255'],
            'website_url' => ['nullable', 'url:http,https', 'max:255'],
            'map_url' => ['nullable', 'url:https', 'max:2048'],
            'map_latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:map_longitude'],
            'map_longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:map_latitude'],
            'map_zoom' => ['nullable', 'integer', 'between:1,19'],
        ]);

        foreach (self::CONTACT_KEYS as $key) {
            Setting::set($key, isset($data[$key]) && $data[$key] !== '' ? (string) $data[$key] : null);
        }

        AuditLog::record('updated', 'Setting', null, 'កែប្រែទីតាំង និងទំនាក់ទំនង (អំពីមន្ទីរ)');

        return redirect()->route('admin.about.contact.edit')->with('status', 'បានរក្សាទុកដោយជោគជ័យ។');
    }

    private function imageId(mixed $id): ?int
    {
        if (blank($id)) {
            return null;
        }
        $media = Media::find($id);
        abort_unless($media && $media->isImage(), 422, 'ឯកសារដែលបានជ្រើសរើសមិនមែនជារូបភាពទេ។');

        return $media->id;
    }
}
