<x-admin.layout title="ការកំណត់ប្រព័ន្ធ">
    <form method="POST" action="{{ route('admin.settings.update') }}" enctype="multipart/form-data" class="bg-white border border-line rounded-[10px] shadow-[0px_3px_10px_0px_rgba(0,0,0,0.06)] p-6 flex flex-col gap-4 max-w-[700px]">
        @csrf @method('PUT')

        <p class="font-bold text-brand-navy text-[16px]">ព័ត៌មានស្ថាប័ន</p>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <x-admin.form-input name="org_name_km" label="ឈ្មោះស្ថាប័ន (ខ្មែរ)" :value="$settings['org_name_km'] ?? ''" required />
            <x-admin.form-input name="org_name_en" label="ឈ្មោះស្ថាប័ន (អង់គ្លេស)" :value="$settings['org_name_en'] ?? ''" />
            <x-admin.form-input name="phone" label="លេខទូរស័ព្ទ" :value="$settings['phone'] ?? ''" />
            <x-admin.form-input name="email" label="អ៊ីមែល" type="email" :value="$settings['email'] ?? ''" />
            <x-admin.form-input name="address" label="អាសយដ្ឋាន" :value="$settings['address'] ?? ''" />
            <x-admin.form-input name="office_hours" label="ម៉ោងបើកការិយាល័យ" :value="$settings['office_hours'] ?? ''" />
        </div>

        <x-admin.form-input name="loading_text_km" label="អត្ថបទពេលកំពុងផ្ទុក (ខ្មែរ — មិនបង្ខំ)" :value="$settings['loading_text_km'] ?? ''" />

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <x-admin.upload-field name="logo" label="រូបសញ្ញា (Logo)" :current="$settings['logo'] ?? null" />
            <x-admin.upload-field name="favicon" label="Favicon" :current="$settings['favicon'] ?? null" />
        </div>

        <p class="font-bold text-brand-navy text-[16px] pt-2">បណ្តាញសង្គម</p>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <x-admin.form-input name="facebook_url" label="Facebook" :value="$settings['facebook_url'] ?? ''" />
            <x-admin.form-input name="telegram_url" label="Telegram" :value="$settings['telegram_url'] ?? ''" />
            <x-admin.form-input name="youtube_url" label="YouTube" :value="$settings['youtube_url'] ?? ''" />
        </div>

        <p class="font-bold text-brand-navy text-[16px] pt-2">SEO & ការតភ្ជាប់</p>
        <x-admin.form-input name="seo_title" label="SEO Title" :value="$settings['seo_title'] ?? ''" />
        <x-admin.textarea name="seo_description" label="SEO Description" :value="$settings['seo_description'] ?? ''" rows="2" />
        <x-admin.form-input name="hrm_url" label="HRM System URL (ចូលប្រព័ន្ធ HRM)" :value="$settings['hrm_url'] ?? ''" />

        <div class="flex justify-end pt-2">
            <button type="submit" class="bg-brand-blue text-white font-bold text-[14px] rounded-[8px] h-11 px-6">រក្សាទុក</button>
        </div>
    </form>
</x-admin.layout>
