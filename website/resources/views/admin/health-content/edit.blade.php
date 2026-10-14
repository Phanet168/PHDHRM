<x-admin.layout :title="$content->title_km">
    <div class="flex items-center justify-between mb-5 flex-wrap gap-3">
        <div class="flex items-center gap-3">
            <p class="font-bold text-brand-navy text-[18px]">{{ $content->title_km }}</p>
            <x-admin.status-badge :status="$content->status" />
        </div>
        <div class="flex gap-2">
            @if ($content->status === 'draft')
                @can('submit', $content)
                    <x-admin.confirmation-dialog :action="route('admin.health-content.submit', $content)" message="ដាក់ស្នើសម្រាប់ការត្រួតពិនិត្យ?" label="ដាក់ស្នើត្រួតពិនិត្យ" class="bg-brand-blue text-white" />
                @endcan
            @endif
            @if ($content->status === 'pending_review')
                @can('approve', $content)
                    <x-admin.confirmation-dialog :action="route('admin.health-content.approve', $content)" message="អនុម័តមាតិកានេះ?" label="អនុម័ត" class="bg-brand-blue text-white" />
                @endcan
            @endif
            @if (in_array($content->status, ['approved', 'draft']))
                @can('publish', $content)
                    <x-admin.confirmation-dialog :action="route('admin.health-content.publish', $content)" message="ផ្សាយមាតិកានេះ?" label="ផ្សាយ" class="bg-brand-green text-white" />
                @endcan
            @endif
            @if ($content->status === 'published')
                @can('publish', $content)
                    <x-admin.confirmation-dialog :action="route('admin.health-content.archive', $content)" message="ទុកមាតិកានេះជាឯកសារ?" label="ទុកជាឯកសារ" class="bg-gray-200 text-ink" />
                @endcan
            @endif
        </div>
    </div>

    <form method="POST" action="{{ route('admin.health-content.update', $content) }}" enctype="multipart/form-data" class="bg-white border border-line rounded-[10px] shadow-[0px_3px_10px_0px_rgba(0,0,0,0.06)] p-6 flex flex-col gap-4 max-w-[800px]">
        @csrf @method('PUT')
        @include('admin.health-content._form')
        <div class="flex justify-end gap-3 pt-2">
            <a href="{{ route('admin.health-content.index') }}" class="text-muted text-[14px] font-bold px-4 py-2">ត្រឡប់</a>
            <button type="submit" class="bg-brand-blue text-white font-bold text-[14px] rounded-[8px] h-11 px-6">រក្សាទុក</button>
        </div>
    </form>
</x-admin.layout>
