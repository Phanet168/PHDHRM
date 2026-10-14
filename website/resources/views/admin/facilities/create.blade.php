<x-admin.layout title="បន្ថែមមូលដ្ឋានសុខាភិបាល">
    <form method="POST" action="{{ route('admin.facilities.store') }}" enctype="multipart/form-data" class="bg-white border border-line rounded-[10px] shadow-[0px_3px_10px_0px_rgba(0,0,0,0.06)] p-6 flex flex-col gap-4 max-w-[800px]">
        @csrf
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <x-admin.form-input name="code" label="លេខកូដ" required />
            <x-admin.select name="facility_type" label="ប្រភេទមូលដ្ឋាន" required :options="[
                'referral_hospital' => 'មន្ទីរពេទ្យបង្អែក',
                'health_center' => 'មណ្ឌលសុខភាព',
                'health_post' => 'ប៉ុស្តិ៍សុខភាព',
                'operational_district_office' => 'ការិយាល័យសុខាភិបាលស្រុកប្រតិបត្តិ',
            ]" />
            <x-admin.form-input name="name_km" label="ឈ្មោះ (ខ្មែរ)" required />
            <x-admin.form-input name="name_en" label="ឈ្មោះ (អង់គ្លេស)" />
            <x-admin.form-input name="slug" label="Slug" required />
            <x-admin.form-input name="phone" label="លេខទូរស័ព្ទ" />
            <x-admin.form-input name="email" label="អ៊ីមែល" type="email" />
            <x-admin.form-input name="opening_hours" label="ម៉ោងបើកសេវា" />
            <x-admin.form-input name="district" label="ស្រុក/ក្រុង" required />
            <x-admin.form-input name="commune" label="ឃុំ/សង្កាត់" required />
            <x-admin.form-input name="village" label="ភូមិ" />
            <x-admin.form-input name="address" label="អាសយដ្ឋាន" />
        </div>

        <x-admin.textarea name="description" label="ការពិពណ៌នា" rows="3" />

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <x-admin.upload-field name="logo" label="រូបសញ្ញា" />
            <x-admin.upload-field name="cover_image" label="រូបគម្រប" />
        </div>

        <div class="flex justify-end gap-3 pt-2">
            <a href="{{ route('admin.facilities.index') }}" class="text-muted text-[14px] font-bold px-4 py-2">បោះបង់</a>
            <button type="submit" class="bg-brand-blue text-white font-bold text-[14px] rounded-[8px] h-11 px-6">រក្សាទុក</button>
        </div>
    </form>
</x-admin.layout>
