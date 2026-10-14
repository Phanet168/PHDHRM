<x-admin.layout :title="$news->title_km">
    {{-- Breadcrumb + header --}}
    <nav class="text-muted text-[12px] mb-2" aria-label="breadcrumb">
        <a href="{{ route('admin.dashboard') }}" class="hover:text-brand-blue">Admin</a>
        <span class="mx-1">/</span>
        <a href="{{ route('admin.news.index') }}" class="hover:text-brand-blue">ព័ត៌មាន និងសកម្មភាព</a>
        <span class="mx-1">/</span>
        <span class="text-ink">កែសម្រួល</span>
    </nav>
    <div class="flex items-start justify-between mb-5 flex-wrap gap-3">
        <div>
            <div class="flex items-center gap-3 flex-wrap">
                <p class="font-bold text-brand-navy text-[20px]">កែសម្រួលព័ត៌មាន</p>
                <x-admin.status-badge :status="$news->status" />
            </div>
            <p class="text-muted text-[13px] mt-1">បំពេញព័ត៌មាន និងគ្រប់គ្រងរូបភាព/ឯកសាររបស់អត្ថបទ</p>
        </div>
        <a href="{{ route('admin.news.index') }}" class="text-muted text-[13px] font-bold border border-line rounded-[8px] h-9 px-4 flex items-center">← ត្រឡប់ទៅបញ្ជី</a>
    </div>

    <div x-data="{ tab: 'content' }">
        @include('admin.news._tab-nav')

        {{-- Content / Settings / SEO fields all live in the same form as before — x-show only
             toggles CSS display, hidden tab fields still submit normally. --}}
        <form id="news-edit-form" method="POST" action="{{ route('admin.news.update', $news) }}" enctype="multipart/form-data">
            @csrf @method('PUT')

            <div x-show="tab === 'content'" x-cloak>
                @include('admin.news._content-fields')
            </div>

            <div x-show="tab === 'media'" x-cloak class="flex flex-col gap-5">
                <div class="grid grid-cols-1 lg:grid-cols-[minmax(0,65%)_minmax(0,35%)] gap-5 items-start">
                    <div class="flex flex-col gap-5 min-w-0">
                        {{-- Raw multi-upload for new gallery photos — appends on save, alongside the managed list below. --}}
                        <div class="bg-white border border-line rounded-[10px] shadow-[0px_3px_10px_0px_rgba(0,0,0,0.06)] p-5 flex flex-col gap-2">
                            <label for="news-gallery-images" class="text-ink text-[13px] font-bold">Upload រូបភាពថ្មីទៅវិចិត្រសាល (អាចជ្រើសរើសច្រើន)</label>
                            <input id="news-gallery-images" type="file" name="images[]" multiple accept="image/jpeg,image/png,image/webp"
                                   class="block w-full text-[13px] text-muted file:mr-3 file:py-2 file:px-4 file:rounded-[8px] file:border-0 file:bg-brand-blue-tint file:text-brand-blue file:font-bold file:text-[13px]">
                            <p class="text-muted text-[11px]">JPG, PNG ឬ WEBP, រូបនីមួយៗអតិបរមា៤MB, គ្មានកំណត់ចំនួន។ រូបនឹងបង្ហាញក្នុងបញ្ជីវិចិត្រសាលខាងក្រោម បន្ទាប់ពីរក្សាទុក។</p>
                            @error('images')<p class="text-brand-red text-[12px]">{{ $message }}</p>@enderror
                            @error('images.*')<p class="text-brand-red text-[12px]">{{ $message }}</p>@enderror
                        </div>
                    </div>
                    <div class="flex flex-col gap-5 min-w-0">
                        @include('admin.news._featured-image')
                    </div>
                </div>
            </div>

            <div x-show="tab === 'settings'" x-cloak>
                @include('admin.news._settings-fields')
            </div>

            <div x-show="tab === 'seo'" x-cloak>
                @include('admin.news._seo-fields')
            </div>
        </form>

        {{-- Attachments + gallery manager: need the article's id, so these manage themselves
             independently (their own forms/AJAX), outside the main form above. Shares the same
             "media" tab as the featured-image/upload fields above. --}}
        <div x-show="tab === 'media'" x-cloak class="grid grid-cols-1 lg:grid-cols-[minmax(0,65%)_minmax(0,35%)] gap-5 items-start mt-5">
            <div class="flex flex-col gap-5 min-w-0">
                @include('admin.news._attachments')
            </div>
            <div class="flex flex-col gap-5 min-w-0">
                @include('admin.news._gallery')
            </div>
        </div>
    </div>

    <div class="h-24"></div> {{-- clearance so the fixed action bar never covers the last card --}}

    {{-- Fixed bottom action bar: stays visible while scrolling through a long article, offset
         past the sidebar's own fixed 260px width on desktop (the sidebar is hidden below lg). --}}
    <div class="fixed inset-x-0 lg:left-[260px] bottom-0 z-40 bg-white border-t border-line px-6 md:px-8 py-3 flex items-center justify-between flex-wrap gap-3">
        <x-admin.confirmation-dialog
            :action="route('admin.news.destroy', $news)" method="DELETE"
            message="លុបអត្ថបទនេះ?&#10;&#10;អត្ថបទនឹងត្រូវបានយកចេញពីការបង្ហាញ។ រូបភាព និងឯកសារនៅ Media Library នឹងមិនត្រូវបានលុបទេ។"
            label="លុបអត្ថបទ" class="bg-white text-brand-red border border-brand-red" />

        <div class="flex items-center gap-2 flex-wrap">
            <button type="submit" form="news-edit-form" class="bg-brand-blue text-white font-bold text-[14px] rounded-[8px] h-11 px-6">
                {{ $news->status === 'published' ? 'រក្សាទុកការកែប្រែ' : 'រក្សាទុកជាសេចក្តីព្រាង' }}
            </button>

            @if ($news->status === 'draft')
                @can('submit', $news)
                    <x-admin.confirmation-dialog :action="route('admin.news.submit', $news)" message="ដាក់ស្នើសម្រាប់ការត្រួតពិនិត្យ?" label="ដាក់ស្នើត្រួតពិនិត្យ" class="bg-white text-brand-blue border border-brand-blue" />
                @endcan
            @endif
            @if ($news->status === 'pending_review')
                @can('approve', $news)
                    <x-admin.confirmation-dialog :action="route('admin.news.approve', $news)" message="អនុម័តព័ត៌មាននេះ?" label="អនុម័ត" class="bg-white text-brand-blue border border-brand-blue" />
                @endcan
            @endif
            @if (in_array($news->status, ['approved', 'draft']))
                @can('publish', $news)
                    <x-admin.confirmation-dialog :action="route('admin.news.publish', $news)" message="បោះផ្សាយព័ត៌មាននេះ?" label="បោះផ្សាយ" class="bg-brand-green text-white" />
                @endcan
            @endif
            @if ($news->status === 'published')
                @can('publish', $news)
                    <x-admin.confirmation-dialog :action="route('admin.news.archive', $news)" message="ទុកព័ត៌មាននេះជាឯកសារ?" label="ទុកជាឯកសារ" class="bg-white text-ink border border-line" />
                @endcan
            @endif
        </div>
    </div>
</x-admin.layout>
