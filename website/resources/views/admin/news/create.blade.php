<x-admin.layout title="បង្កើតព័ត៌មានថ្មី">
    <nav class="text-muted text-[12px] mb-2" aria-label="breadcrumb">
        <a href="{{ route('admin.dashboard') }}" class="hover:text-brand-blue">Admin</a>
        <span class="mx-1">/</span>
        <a href="{{ route('admin.news.index') }}" class="hover:text-brand-blue">ព័ត៌មាន និងសកម្មភាព</a>
        <span class="mx-1">/</span>
        <span class="text-ink">បង្កើតថ្មី</span>
    </nav>
    <div class="flex items-start justify-between mb-5 flex-wrap gap-3">
        <div>
            <p class="font-bold text-brand-navy text-[20px]">បង្កើតព័ត៌មានថ្មី</p>
            <p class="text-muted text-[13px] mt-1">បំពេញព័ត៌មាន និងគ្រប់គ្រងរូបភាព/ឯកសាររបស់អត្ថបទ</p>
        </div>
        <a href="{{ route('admin.news.index') }}" class="text-muted text-[13px] font-bold border border-line rounded-[8px] h-9 px-4 flex items-center">← ត្រឡប់ទៅបញ្ជី</a>
    </div>

    <div x-data="{ tab: 'content' }">
        @include('admin.news._tab-nav')

        <form id="news-create-form" method="POST" action="{{ route('admin.news.store') }}" enctype="multipart/form-data">
            @csrf

            <div x-show="tab === 'content'" x-cloak>
                @include('admin.news._content-fields')
            </div>

            <div x-show="tab === 'media'" x-cloak class="grid grid-cols-1 lg:grid-cols-[minmax(0,65%)_minmax(0,35%)] gap-5 items-start">
                <div class="flex flex-col gap-5 min-w-0">
                    <div class="bg-white border border-line rounded-[10px] shadow-[0px_3px_10px_0px_rgba(0,0,0,0.06)] p-5 flex flex-col gap-2">
                        <p class="text-ink text-[13px] font-bold">ឯកសារភ្ជាប់ (PDF, DOC, DOCX, XLS, XLSX)</p>
                        <input type="file" name="attachments[]" multiple accept=".pdf,.doc,.docx,.xls,.xlsx"
                               class="block w-full text-[13px] text-muted file:mr-3 file:py-2 file:px-4 file:rounded-[8px] file:border-0 file:bg-brand-blue-tint file:text-brand-blue file:font-bold file:text-[13px]">
                        <p class="text-muted text-[11px]">ឯកសារនីមួយៗអតិបរមា ២០MB។ អាចគ្រប់គ្រង (ប្តូរឈ្មោះ/តម្រៀប/ដកចេញ) ក្រោយពីរក្សាទុក។</p>
                        @error('attachments')<p class="text-brand-red text-[12px]">{{ $message }}</p>@enderror
                        @error('attachments.*')<p class="text-brand-red text-[12px]">{{ $message }}</p>@enderror
                    </div>

                    <div class="bg-white border border-line rounded-[10px] shadow-[0px_3px_10px_0px_rgba(0,0,0,0.06)] p-5 flex flex-col gap-2">
                        <p class="text-ink text-[13px] font-bold">រូបភាពបន្ថែម / Gallery Images</p>
                        <input type="file" name="images[]" multiple accept="image/jpeg,image/png,image/webp"
                               class="block w-full text-[13px] text-muted file:mr-3 file:py-2 file:px-4 file:rounded-[8px] file:border-0 file:bg-brand-blue-tint file:text-brand-blue file:font-bold file:text-[13px]">
                        <p class="text-muted text-[11px]">JPG, PNG ឬ WEBP, រូបនីមួយៗអតិបរមា៤MB, គ្មានកំណត់ចំនួន។ អាចតម្រៀប/ដកចេញក្រោយពីរក្សាទុក។</p>
                        @error('images')<p class="text-brand-red text-[12px]">{{ $message }}</p>@enderror
                        @error('images.*')<p class="text-brand-red text-[12px]">{{ $message }}</p>@enderror
                    </div>
                </div>

                <div class="flex flex-col gap-5 min-w-0">
                    @include('admin.news._featured-image')
                </div>
            </div>

            <div x-show="tab === 'settings'" x-cloak>
                @include('admin.news._settings-fields')
            </div>

            <div x-show="tab === 'seo'" x-cloak>
                @include('admin.news._seo-fields')
            </div>

            <div class="h-24"></div>
        </form>
    </div>

    <div class="fixed inset-x-0 lg:left-[260px] bottom-0 z-40 bg-white border-t border-line px-6 md:px-8 py-3 flex items-center justify-end gap-2">
        <a href="{{ route('admin.news.index') }}" class="text-muted text-[14px] font-bold px-4 py-2">បោះបង់</a>
        <button type="submit" form="news-create-form" class="bg-brand-blue text-white font-bold text-[14px] rounded-[8px] h-11 px-6">រក្សាទុកជាសេចក្តីព្រាង</button>
    </div>
</x-admin.layout>
