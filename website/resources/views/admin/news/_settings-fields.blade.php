@php $news = $news ?? null; @endphp

<div class="bg-white border border-line rounded-[10px] shadow-[0px_3px_10px_0px_rgba(0,0,0,0.06)] p-5 flex flex-col gap-4">
    <div class="flex items-center gap-2">
        <span class="flex items-center justify-center size-7 rounded-[6px] bg-brand-blue-tint text-brand-blue text-[14px]">⚙</span>
        <p class="text-brand-navy text-[15px] font-bold">ការកំណត់ផ្សេងៗ</p>
    </div>

    <x-admin.select name="facility_id" label="មូលដ្ឋានពាក់ព័ន្ធ" :value="$news?->facility_id" :options="$facilities->pluck('name_km', 'id')" placeholder="ព័ត៌មានទូទៅ (មិនកំណត់មូលដ្ឋាន)" />

    <label class="flex items-center gap-2 text-[13px] text-ink">
        <input type="checkbox" name="is_featured" value="1" @checked($news?->is_featured) class="rounded border-line">
        ព័ត៌មានលេចធ្លោ (បង្ហាញនៅផ្នែកលេចធ្លោលើទំព័រដើម)
    </label>

    @if ($news?->exists)
        <div class="border-t border-line pt-4 flex flex-col gap-2 text-[12px] text-muted">
            <p class="text-ink text-[13px] font-bold mb-1">ព័ត៌មានតាមដាន</p>
            <p>អ្នកនិពន្ធ៖ {{ $news->author?->name ?? '—' }}</p>
            <p>អ្នកត្រួតពិនិត្យ៖ {{ $news->reviewer?->name ?? '—' }}</p>
            <p>កាលបរិច្ឆេទបង្កើត៖ {{ $news->created_at?->format('d-m-Y H:i') }}</p>
            <p>កែប្រែចុងក្រោយ៖ {{ $news->updated_at?->format('d-m-Y H:i') }}</p>
            @if ($news->approved_at)
                <p>អនុម័តនៅ៖ {{ $news->approved_at->format('d-m-Y H:i') }}</p>
            @endif
        </div>
    @endif
</div>
