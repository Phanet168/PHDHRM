@php
    $aboutTabs = [
        ['label' => 'ទិដ្ឋភាពទូទៅ', 'url' => route('admin.about.index'), 'active' => request()->routeIs('admin.about.index')],
        ['label' => 'Hero', 'url' => route('admin.about.sections.edit', 'hero'), 'active' => request()->routeIs('admin.about.sections.*') && request()->route('key') === 'hero'],
        ['label' => 'ប្រវត្តិ និងបេសកកម្ម', 'url' => route('admin.about.sections.edit', 'history'), 'active' => request()->routeIs('admin.about.sections.*') && request()->route('key') !== 'hero'],
        ['label' => 'ចក្ខុវិស័យ / បេសកកម្ម / តួនាទី / គោលតម្លៃ', 'url' => route('admin.about.items.index', 'cards'), 'active' => request()->routeIs('admin.about.items.*') && request()->route('group') === 'cards'],
        ['label' => 'ប្រវត្តិ និងការអភិវឌ្ឍ', 'url' => route('admin.about.items.index', 'timeline'), 'active' => request()->routeIs('admin.about.items.*') && request()->route('group') === 'timeline'],
        ['label' => 'ថ្នាក់ដឹកនាំ', 'url' => route('admin.about.leaders.index'), 'active' => request()->routeIs('admin.about.leaders.*')],
        ['label' => 'រចនាសម្ព័ន្ធអង្គភាព', 'url' => route('admin.about.organization.index'), 'active' => request()->routeIs('admin.about.organization.*')],
        ['label' => 'ឯកសារពាក់ព័ន្ធ', 'url' => route('admin.about.items.index', 'documents'), 'active' => request()->routeIs('admin.about.items.*') && request()->route('group') === 'documents'],
        ['label' => 'ទីតាំង និងទំនាក់ទំនង', 'url' => route('admin.about.contact.edit'), 'active' => request()->routeIs('admin.about.contact.*')],
    ];
@endphp
<div class="flex flex-col gap-3 mb-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <p class="text-muted text-[13px]">គ្រប់គ្រងខ្លឹមសារទាំងអស់នៃទំព័រសាធារណៈ «អំពីមន្ទីរ»។ ទំព័របង្ហាញតែទិន្នន័យដែលបើកប្រើប៉ុណ្ណោះ។</p>
        <a href="{{ route('about') }}" target="_blank" rel="noopener" class="border border-brand-blue text-brand-blue font-bold text-[13px] rounded-[8px] h-10 px-4 inline-flex items-center gap-2 whitespace-nowrap">មើលជាមុន ↗</a>
    </div>
    <nav aria-label="ផ្នែកអំពីមន្ទីរ" class="flex gap-2 overflow-x-auto pb-1">
        @foreach ($aboutTabs as $tab)
            <a href="{{ $tab['url'] }}" @if ($tab['active']) aria-current="page" @endif
               class="whitespace-nowrap rounded-full px-4 h-9 inline-flex items-center text-[13px] font-bold border {{ $tab['active'] ? 'bg-brand-blue text-white border-brand-blue' : 'bg-white text-ink border-line hover:border-brand-blue hover:text-brand-blue' }}">{{ $tab['label'] }}</a>
        @endforeach
    </nav>
    @if ($errors->any())
        <div class="bg-red-50 text-brand-red text-[13px] rounded-[8px] px-4 py-3">
            <p class="font-bold mb-1">សូមពិនិត្យព័ត៌មានខាងក្រោម៖</p>
            <ul class="list-disc pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif
</div>
