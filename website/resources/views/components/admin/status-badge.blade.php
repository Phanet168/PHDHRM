@props(['status'])

@php
    $styles = [
        'draft' => 'bg-gray-100 text-gray-600',
        'pending_review' => 'bg-amber-100 text-amber-700',
        'approved' => 'bg-brand-blue-tint text-brand-blue',
        'published' => 'bg-brand-green-tint text-brand-green',
        'archived' => 'bg-gray-200 text-gray-500',
    ];

    $labels = [
        'draft' => 'សេចក្តីព្រាង',
        'pending_review' => 'កំពុងរង់ចាំត្រួតពិនិត្យ',
        'approved' => 'បានអនុម័ត',
        'published' => 'បានផ្សាយ',
        'archived' => 'បានទុកក្នុងឯកសារ',
    ];
@endphp

<span class="{{ $styles[$status] ?? 'bg-gray-100 text-gray-600' }} inline-flex items-center rounded-full px-3 py-1 text-[12px] font-bold whitespace-nowrap">
    {{ $labels[$status] ?? $status }}
</span>
