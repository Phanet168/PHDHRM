@php $canSave = auth()->user()->can('settings.manage'); @endphp
<x-admin.layout title="អំពីមន្ទីរ · ទីតាំង និងទំនាក់ទំនង">
    @include('admin.about._nav')

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6 items-start">
        <form method="POST" action="{{ route('admin.about.contact.update') }}" class="xl:col-span-2 bg-white border border-line rounded-[10px] shadow-[0px_3px_10px_0px_rgba(0,0,0,0.06)] p-6 flex flex-col gap-4">
            @csrf @method('PUT')
            <fieldset @disabled(! $canSave) class="flex flex-col gap-4">
                <p class="font-bold text-brand-navy text-[16px]">ព័ត៌មានទំនាក់ទំនង</p>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <x-admin.form-input name="phone" label="លេខទូរស័ព្ទ" :value="$settings['phone'] ?? ''" />
                    <x-admin.form-input name="email" label="អ៊ីមែល" type="email" :value="$settings['email'] ?? ''" />
                    <x-admin.form-input name="address" label="អាសយដ្ឋាន" :value="$settings['address'] ?? ''" />
                    <x-admin.form-input name="office_hours" label="ម៉ោងធ្វើការ" :value="$settings['office_hours'] ?? ''" />
                    <x-admin.form-input name="website_url" label="គេហទំព័រ (https://...)" type="url" :value="$settings['website_url'] ?? ''" />
                </div>

                <p class="font-bold text-brand-navy text-[16px] pt-2">ផែនទី</p>
                <p class="text-muted text-[12px] -mt-2">សូមបញ្ចូលកូអរដោនេពិតប្រាកដនៃទីស្នាក់ការ (ចម្លងពី Google Maps៖ ចុចស្ដាំលើទីតាំង → លេខដំបូងជា latitude)។ ប្រសិនបើទុកទទេ ផែនទីនឹងមិនបង្ហាញទេ — មានតែតំណ Google Maps ប៉ុណ្ណោះ។</p>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <x-admin.form-input name="map_latitude" label="Latitude" type="number" step="any" min="-90" max="90" :value="$settings['map_latitude'] ?? ''" placeholder="ឧ. 13.52" />
                    <x-admin.form-input name="map_longitude" label="Longitude" type="number" step="any" min="-180" max="180" :value="$settings['map_longitude'] ?? ''" placeholder="ឧ. 105.97" />
                    <x-admin.form-input name="map_zoom" label="កម្រិតពង្រីក (1–19)" type="number" min="1" max="19" :value="$settings['map_zoom'] ?? ''" placeholder="15" />
                </div>
                <x-admin.form-input name="map_url" label="តំណ Google Maps (https://...)" type="url" :value="$settings['map_url'] ?? ''" placeholder="https://maps.app.goo.gl/..." />
            </fieldset>

            <div class="flex justify-end gap-3 pt-2">
                @if ($canSave)
                    <button type="submit" class="bg-brand-blue text-white font-bold text-[14px] rounded-[8px] h-11 px-6">រក្សាទុក</button>
                @else
                    <p class="text-amber-700 text-[13px] font-bold">ត្រូវការសិទ្ធិ «ការកំណត់ប្រព័ន្ធ» ដើម្បីកែសម្រួល។</p>
                @endif
            </div>
        </form>

        <div class="bg-white border border-line rounded-[10px] shadow-[0px_3px_10px_0px_rgba(0,0,0,0.06)] p-6 flex flex-col gap-2">
            <p class="font-bold text-brand-navy text-[15px]">ប្រភពទិន្នន័យតែមួយ</p>
            <p class="text-muted text-[12px]">ទូរស័ព្ទ អ៊ីមែល អាសយដ្ឋាន និងម៉ោងធ្វើការ គឺជាការកំណត់ដដែលដែលប្រើនៅ Header, Footer, ទំព័រទំនាក់ទំនង និង «ការកំណត់ប្រព័ន្ធ»។ ការកែនៅទីនេះប្ដូរនៅគ្រប់កន្លែង។</p>
            @if (is_numeric($settings['map_latitude'] ?? null) && is_numeric($settings['map_longitude'] ?? null))
                <a href="https://www.openstreetmap.org/?mlat={{ $settings['map_latitude'] }}&mlon={{ $settings['map_longitude'] }}#map=16/{{ $settings['map_latitude'] }}/{{ $settings['map_longitude'] }}" target="_blank" rel="noopener" class="text-brand-blue text-[13px] font-bold mt-2">ពិនិត្យទីតាំងលើផែនទី ↗</a>
            @else
                <p class="text-amber-700 text-[12px] font-bold mt-2">មិនទាន់កំណត់ទីតាំងផែនទី។</p>
            @endif
        </div>
    </div>
</x-admin.layout>
