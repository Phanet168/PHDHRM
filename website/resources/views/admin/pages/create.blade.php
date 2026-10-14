@php
    $templates = [
        'default' => 'លំនាំដើម (Default)', 'about' => 'អំពី (About)', 'contact' => 'ទំនាក់ទំនង (Contact)',
        'leadership' => 'ថ្នាក់ដឹកនាំ (Leadership)', 'full_width' => 'ពេញទទឹង (Full Width)', 'landing' => 'ទំព័រចុះចត (Landing)',
    ];
@endphp
<x-admin.layout title="បង្កើតទំព័រថ្មី">
    <form method="POST" action="{{ route('admin.pages.store') }}" class="bg-white border border-line rounded-[10px] shadow-[0px_3px_10px_0px_rgba(0,0,0,0.06)] p-6 flex flex-col gap-4 max-w-[700px]">
        @csrf
        <x-admin.form-input name="title_km" label="ចំណងជើង (ខ្មែរ)" required autofocus />
        <x-admin.form-input name="title_en" label="ចំណងជើង (អង់គ្លេស)" />
        <x-admin.form-input name="slug" label="Slug (ទុកទទេ ដើម្បីបង្កើតដោយស្វ័យប្រវត្តិ)" />
        <x-admin.select name="template" label="Template" :value="'default'" :placeholder="null" :options="$templates" />
        <div class="flex justify-end gap-2 pt-2">
            <a href="{{ route('admin.pages.index') }}" class="text-muted text-[14px] font-bold px-4 py-2">បោះបង់</a>
            <button type="submit" class="bg-brand-blue text-white font-bold text-[14px] rounded-[8px] h-11 px-6">បង្កើត</button>
        </div>
    </form>
</x-admin.layout>
