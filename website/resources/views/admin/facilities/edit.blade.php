@php
    $tabs = [
        'general' => 'ព័ត៌មានទូទៅ',
        'structure' => 'រចនាសម្ព័ន្ធ',
        'services' => 'សេវាសុខាភិបាល',
        'partners' => 'អង្គការដៃគូ',
        'location' => 'ទីតាំង',
        'gallery' => 'Gallery',
        'news' => 'ព័ត៌មាន',
        'documents' => 'ឯកសារ',
    ];
@endphp
<x-admin.layout :title="$facility->name_km">
    <div class="flex items-center justify-between mb-5">
        <div class="flex items-center gap-3">
            <p class="font-bold text-brand-navy text-[18px]">{{ $facility->name_km }}</p>
            <x-admin.status-badge :status="$facility->status" />
        </div>
        <div class="flex gap-2">
            @can('publish', $facility)
                @if ($facility->status !== 'published')
                    <x-admin.confirmation-dialog :action="route('admin.facilities.publish', $facility)" message="ផ្សាយមូលដ្ឋាននេះ?" label="ផ្សាយ" class="bg-brand-green text-white" />
                @else
                    <x-admin.confirmation-dialog :action="route('admin.facilities.archive', $facility)" method="POST" message="ទុកមូលដ្ឋាននេះជាឯកសារ?" label="ទុកជាឯកសារ" class="bg-gray-200 text-ink" />
                @endif
            @endcan
        </div>
    </div>

    <div class="flex gap-1 border-b border-line mb-6 overflow-x-auto">
        @foreach ($tabs as $key => $label)
            <a href="{{ route('admin.facilities.edit', ['facility' => $facility, 'tab' => $key]) }}"
               class="px-4 py-3 text-[14px] whitespace-nowrap {{ $tab === $key ? 'border-b-2 border-brand-blue text-brand-blue font-bold' : 'text-muted' }}">
                {{ $label }}
            </a>
        @endforeach
    </div>

    @if ($tab === 'general')
        <form method="POST" action="{{ route('admin.facilities.update', $facility) }}" enctype="multipart/form-data" class="bg-white border border-line rounded-[10px] shadow-[0px_3px_10px_0px_rgba(0,0,0,0.06)] p-6 flex flex-col gap-4 max-w-[800px]">
            @csrf @method('PUT')
            <input type="hidden" name="tab" value="general">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <x-admin.form-input name="code" label="លេខកូដ" :value="$facility->code" required />
                <x-admin.select name="facility_type" label="ប្រភេទមូលដ្ឋាន" required :value="$facility->facility_type" :options="[
                    'referral_hospital' => 'មន្ទីរពេទ្យបង្អែក',
                    'health_center' => 'មណ្ឌលសុខភាព',
                    'health_post' => 'ប៉ុស្តិ៍សុខភាព',
                    'operational_district_office' => 'ការិយាល័យសុខាភិបាលស្រុកប្រតិបត្តិ',
                ]" />
                <x-admin.form-input name="name_km" label="ឈ្មោះ (ខ្មែរ)" :value="$facility->name_km" required />
                <x-admin.form-input name="name_en" label="ឈ្មោះ (អង់គ្លេស)" :value="$facility->name_en" />
                <x-admin.form-input name="slug" label="Slug" :value="$facility->slug" required />
                <x-admin.form-input name="phone" label="លេខទូរស័ព្ទ" :value="$facility->phone" />
                <x-admin.form-input name="email" label="អ៊ីមែល" type="email" :value="$facility->email" />
                <x-admin.form-input name="opening_hours" label="ម៉ោងបើកសេវា" :value="$facility->opening_hours" />
            </div>

            <x-admin.textarea name="description" label="ការពិពណ៌នា" :value="$facility->description" rows="3" />
            <x-admin.textarea name="history" label="ប្រវត្តិ" :value="$facility->history" rows="3" />
            <x-admin.textarea name="vision" label="ចក្ខុវិស័យ" :value="$facility->vision" rows="2" />
            <x-admin.textarea name="mission" label="បេសកកម្ម" :value="$facility->mission" rows="2" />

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <x-admin.form-input name="patients_count" label="ចំនួនអ្នកជំងឺ" type="number" :value="$facility->patients_count" />
                <x-admin.form-input name="staff_count" label="ចំនួនបុគ្គលិក" type="number" :value="$facility->staff_count" />
                <x-admin.form-input name="services_count" label="ចំនួនសេវាសំខាន់" type="number" :value="$facility->services_count" />
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <x-admin.upload-field name="logo" label="រូបសញ្ញា" :current="$facility->logo" />
                <x-admin.upload-field name="cover_image" label="រូបគម្រប" :current="$facility->cover_image" />
            </div>

            <div class="flex justify-end pt-2">
                <button type="submit" class="bg-brand-blue text-white font-bold text-[14px] rounded-[8px] h-11 px-6">រក្សាទុក</button>
            </div>
        </form>
    @endif

    @if ($tab === 'structure')
        <div class="bg-white border border-line rounded-[10px] shadow-[0px_3px_10px_0px_rgba(0,0,0,0.06)] p-6 max-w-[700px]">
            <p class="font-bold text-brand-navy text-[16px] mb-4">ថ្នាក់ដឹកនាំ</p>
            <div class="flex flex-col divide-y divide-line mb-4">
                @forelse ($facility->leaders as $leader)
                    <div class="py-3 flex items-center justify-between">
                        <div>
                            <p class="font-bold text-ink text-[14px]">{{ $leader->name }}</p>
                            <p class="text-muted text-[12px]">{{ $leader->title }}</p>
                        </div>
                        <x-admin.confirmation-dialog :action="route('admin.facilities.leaders.destroy', [$facility, $leader])" method="DELETE" message="លុបថ្នាក់ដឹកនាំនេះ?" label="លុប" class="bg-red-50 text-brand-red" />
                    </div>
                @empty
                    <p class="text-muted text-[13px] py-4">មិនទាន់មានថ្នាក់ដឹកនាំទេ។</p>
                @endforelse
            </div>
            <form method="POST" action="{{ route('admin.facilities.leaders.store', $facility) }}" enctype="multipart/form-data" class="flex flex-wrap gap-2 items-end border-t border-line pt-4">
                @csrf
                <div class="flex-1 min-w-[160px]"><x-admin.form-input name="name" label="ឈ្មោះ" required /></div>
                <div class="flex-1 min-w-[160px]"><x-admin.form-input name="title" label="តួនាទី" required /></div>
                <button type="submit" class="bg-brand-blue text-white font-bold text-[13px] rounded-[8px] h-11 px-5">បន្ថែម</button>
            </form>
        </div>
    @endif

    @if ($tab === 'services')
        <div class="bg-white border border-line rounded-[10px] shadow-[0px_3px_10px_0px_rgba(0,0,0,0.06)] p-6 max-w-[700px]">
            <p class="font-bold text-brand-navy text-[16px] mb-4">សេវាសុខាភិបាល</p>
            <div class="flex flex-col divide-y divide-line mb-4">
                @forelse ($facility->services as $service)
                    <div class="py-3 flex items-center justify-between">
                        <p class="text-ink text-[14px]">{{ $service->icon }} {{ $service->name }}</p>
                        <x-admin.confirmation-dialog :action="route('admin.facilities.services.destroy', [$facility, $service])" method="DELETE" message="លុបសេវានេះ?" label="លុប" class="bg-red-50 text-brand-red" />
                    </div>
                @empty
                    <p class="text-muted text-[13px] py-4">មិនទាន់មានសេវាទេ។</p>
                @endforelse
            </div>
            <form method="POST" action="{{ route('admin.facilities.services.store', $facility) }}" class="flex flex-wrap gap-2 items-end border-t border-line pt-4">
                @csrf
                <div class="w-[80px]"><x-admin.form-input name="icon" label="រូបតំណាង" value="✚" /></div>
                <div class="flex-1 min-w-[200px]"><x-admin.form-input name="name" label="ឈ្មោះសេវា" required /></div>
                <button type="submit" class="bg-brand-blue text-white font-bold text-[13px] rounded-[8px] h-11 px-5">បន្ថែម</button>
            </form>
        </div>
    @endif

    @if ($tab === 'partners')
        <form method="POST" action="{{ route('admin.facilities.partners.sync', $facility) }}" class="bg-white border border-line rounded-[10px] shadow-[0px_3px_10px_0px_rgba(0,0,0,0.06)] p-6 max-w-[600px]">
            @csrf
            <p class="font-bold text-brand-navy text-[16px] mb-4">អង្គការដៃគូ</p>
            <div class="flex flex-col gap-2 mb-4">
                @foreach ($partners as $partner)
                    <label class="flex items-center gap-2 text-[14px] text-ink">
                        <input type="checkbox" name="partner_ids[]" value="{{ $partner->id }}" @checked($facility->partners->contains($partner->id)) class="rounded border-line">
                        {{ $partner->name }}
                    </label>
                @endforeach
            </div>
            <button type="submit" class="bg-brand-blue text-white font-bold text-[13px] rounded-[8px] h-10 px-5">រក្សាទុក</button>
        </form>
    @endif

    @if ($tab === 'location')
        <form method="POST" action="{{ route('admin.facilities.update', $facility) }}" class="bg-white border border-line rounded-[10px] shadow-[0px_3px_10px_0px_rgba(0,0,0,0.06)] p-6 flex flex-col gap-4 max-w-[600px]">
            @csrf @method('PUT')
            <input type="hidden" name="tab" value="location">
            @foreach (['code' => $facility->code, 'facility_type' => $facility->facility_type, 'name_km' => $facility->name_km, 'slug' => $facility->slug, 'district' => $facility->district, 'commune' => $facility->commune] as $key => $value)
                <input type="hidden" name="{{ $key }}" value="{{ $value }}">
            @endforeach
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <x-admin.form-input name="district" label="ស្រុក/ក្រុង" :value="$facility->district" required />
                <x-admin.form-input name="commune" label="ឃុំ/សង្កាត់" :value="$facility->commune" required />
                <x-admin.form-input name="village" label="ភូមិ" :value="$facility->village" />
                <x-admin.form-input name="address" label="អាសយដ្ឋាន" :value="$facility->address" />
                <x-admin.form-input name="latitude" label="រយៈទទឹង (Latitude)" :value="$facility->latitude" />
                <x-admin.form-input name="longitude" label="រយៈបណ្តោយ (Longitude)" :value="$facility->longitude" />
            </div>

            <div>
                <p class="text-ink text-[13px] font-bold mb-1">ជ្រើសរើសទីតាំងលើផែនទី (ចុច ឬអូសម្ពាក់)</p>
                <div id="facility-location-picker" data-lat-input="#latitude" data-lng-input="#longitude" data-default-lat="13.5259" data-default-lng="105.9683" class="w-full h-[320px] rounded-[10px] border border-line overflow-hidden"></div>
            </div>

            <div class="flex justify-end pt-2">
                <button type="submit" class="bg-brand-blue text-white font-bold text-[14px] rounded-[8px] h-11 px-6">រក្សាទុក</button>
            </div>
        </form>
    @endif

    @if ($tab === 'gallery')
        <div class="bg-white border border-line rounded-[10px] shadow-[0px_3px_10px_0px_rgba(0,0,0,0.06)] p-6 max-w-[800px]">
            <p class="font-bold text-brand-navy text-[16px] mb-4">Gallery</p>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
                @forelse ($facility->gallery as $image)
                    <div class="relative group">
                        <img src="{{ asset('storage/' . $image->image) }}" class="h-[110px] w-full object-cover rounded-[8px] border border-line">
                        <form method="POST" action="{{ route('admin.facilities.gallery.destroy', [$facility, $image]) }}" onsubmit="return confirm('លុបរូបភាពនេះ?')" class="absolute top-1 right-1">
                            @csrf @method('DELETE')
                            <button type="submit" class="bg-white/90 text-brand-red text-[12px] rounded-full size-6">✕</button>
                        </form>
                    </div>
                @empty
                    <p class="text-muted text-[13px] col-span-4">មិនទាន់មានរូបភាពទេ។</p>
                @endforelse
            </div>
            <form method="POST" action="{{ route('admin.facilities.gallery.store', $facility) }}" enctype="multipart/form-data" class="flex flex-wrap gap-2 items-end border-t border-line pt-4">
                @csrf
                <div class="flex-1 min-w-[220px]"><x-admin.upload-field name="image" label="បន្ថែមរូបភាព" /></div>
                <button type="submit" class="bg-brand-blue text-white font-bold text-[13px] rounded-[8px] h-11 px-5">បញ្ចូល</button>
            </form>
        </div>
    @endif

    @if ($tab === 'news')
        <div class="bg-white border border-line rounded-[10px] shadow-[0px_3px_10px_0px_rgba(0,0,0,0.06)] p-6">
            <div class="flex items-center justify-between mb-4">
                <p class="font-bold text-brand-navy text-[16px]">ព័ត៌មានពាក់ព័ន្ធ</p>
                @can('create', \App\Models\News::class)
                    <a href="{{ route('admin.news.create') }}" class="text-brand-blue text-[13px] font-bold">+ បង្កើតព័ត៌មានថ្មី</a>
                @endcan
            </div>
            <div class="flex flex-col divide-y divide-line">
                @forelse ($facility->news as $article)
                    <div class="py-3 flex items-center justify-between">
                        <a href="{{ route('admin.news.edit', $article) }}" class="font-bold text-ink text-[14px] hover:text-brand-blue">{{ $article->title_km }}</a>
                        <x-admin.status-badge :status="$article->status" />
                    </div>
                @empty
                    <p class="text-muted text-[13px] py-4">មិនទាន់មានព័ត៌មានទេ។</p>
                @endforelse
            </div>
        </div>
    @endif

    @if ($tab === 'documents')
        <div class="bg-white border border-line rounded-[10px] shadow-[0px_3px_10px_0px_rgba(0,0,0,0.06)] p-6">
            <p class="text-muted text-[13px]">លិខិតបទដ្ឋានគតិយុត្តត្រូវបានគ្រប់គ្រងនៅកម្រិតទូទាំងអង្គភាព។ <a href="{{ route('admin.legal-documents.index') }}" class="text-brand-blue font-bold">មើលឯកសារទាំងអស់ →</a></p>
        </div>
    @endif
</x-admin.layout>
