<x-admin.layout title="បង្កើតអត្ថបទ">
    <form method="POST" action="{{ route('admin.content.store') }}" enctype="multipart/form-data" class="bg-white border border-line rounded-[10px] shadow-[0px_3px_10px_0px_rgba(0,0,0,0.06)] p-6 flex flex-col gap-4 max-w-[800px]">
        @csrf
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <x-admin.form-input name="title_km" label="ចំណងជើង (ខ្មែរ)" required />
            <x-admin.form-input name="title_en" label="ចំណងជើង (អង់គ្លេស)" />
            <x-admin.form-input name="slug" label="Slug" required />
            <x-admin.select name="category_id" label="ប្រភេទមាតិកា" :options="$categories->pluck('name_km', 'id')" />
        </div>

        <x-admin.textarea name="excerpt" label="សេចក្តីសង្ខេប" rows="2" />
        <x-admin.upload-field name="featured_image" label="រូបភាពគម្រប" />

        <p class="text-muted text-[12px]">អ្នកនឹងអាចបន្ថែម Block មាតិកា, វិចិត្រសាល, និង SEO បន្ថែមទៀត បន្ទាប់ពីបង្កើតដំបូង។</p>

        <div class="flex justify-end gap-3 pt-2">
            <a href="{{ route('admin.content.index') }}" class="text-muted text-[14px] font-bold px-4 py-2">ត្រឡប់</a>
            <button type="submit" class="bg-brand-blue text-white font-bold text-[14px] rounded-[8px] h-11 px-6">បន្ត →</button>
        </div>
    </form>
</x-admin.layout>
