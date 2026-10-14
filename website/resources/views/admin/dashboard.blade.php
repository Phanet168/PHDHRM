<x-admin.layout title="ផ្ទាំងគ្រប់គ្រង">
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <x-admin.stat-card :value="$stats['total_facilities']" label="មូលដ្ឋានសុខាភិបាលសរុប" icon="✚" href="{{ route('admin.facilities.index') }}" />
        <x-admin.stat-card :value="$stats['referral_hospitals']" label="មន្ទីរពេទ្យបង្អែក" icon="✚" color="text-brand-navy" />
        <x-admin.stat-card :value="$stats['health_centers']" label="មណ្ឌលសុខភាព" icon="⌂" color="text-brand-green" />
        <x-admin.stat-card :value="$stats['health_posts']" label="ប៉ុស្តិ៍សុខភាព" icon="✚" />
        <x-admin.stat-card :value="$stats['published_news']" label="ព័ត៌មានដែលបានផ្សាយ" icon="▤" href="{{ route('admin.news.index', ['status' => 'published']) }}" />
        <x-admin.stat-card :value="$stats['legal_documents']" label="លិខិតបទដ្ឋានគតិយុត្ត" icon="▥" href="{{ route('admin.legal-documents.index') }}" />
        <x-admin.stat-card :value="$stats['pending_approval']" label="រង់ចាំការអនុម័ត" icon="◌" color="text-amber-600" href="{{ route('admin.news.index', ['status' => 'pending_review']) }}" />
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 bg-white border border-line rounded-[10px] shadow-[0px_3px_10px_0px_rgba(0,0,0,0.06)] p-5">
            <p class="font-bold text-brand-navy text-[16px] mb-4">មាតិកាថ្មីៗ</p>
            <div class="flex flex-col divide-y divide-line">
                @forelse ($recentContent as $item)
                    <div class="py-3 flex items-center justify-between gap-3">
                        <div class="min-w-0">
                            <a href="{{ route('admin.news.edit', $item) }}" class="font-bold text-ink text-[14px] hover:text-brand-blue truncate block">{{ $item->title_km }}</a>
                            <p class="text-muted text-[12px]">{{ $item->author?->name }} · {{ $item->created_at->diffForHumans() }}</p>
                        </div>
                        <x-admin.status-badge :status="$item->status" />
                    </div>
                @empty
                    <p class="text-muted text-[13px] py-6 text-center">មិនមានមាតិកាថ្មីទេ។</p>
                @endforelse
            </div>
        </div>

        <div class="bg-white border border-line rounded-[10px] shadow-[0px_3px_10px_0px_rgba(0,0,0,0.06)] p-5">
            <p class="font-bold text-brand-navy text-[16px] mb-4">សកម្មភាពថ្មីៗ</p>
            <div class="flex flex-col divide-y divide-line">
                @forelse ($recentActivity as $log)
                    <div class="py-3">
                        <p class="text-ink text-[13px]">{{ $log->description }}</p>
                        <p class="text-muted text-[11px]">{{ $log->user?->name ?? 'System' }} · {{ $log->created_at->diffForHumans() }}</p>
                    </div>
                @empty
                    <p class="text-muted text-[13px] py-6 text-center">មិនមានសកម្មភាពថ្មីទេ។</p>
                @endforelse
            </div>
        </div>
    </div>

    @if ($pendingReview->isNotEmpty())
        <div class="bg-white border border-line rounded-[10px] shadow-[0px_3px_10px_0px_rgba(0,0,0,0.06)] p-5 mt-6">
            <p class="font-bold text-brand-navy text-[16px] mb-4">រង់ចាំការត្រួតពិនិត្យ</p>
            <div class="flex flex-col divide-y divide-line">
                @foreach ($pendingReview as $item)
                    <div class="py-3 flex items-center justify-between gap-3">
                        <div class="min-w-0">
                            <a href="{{ route('admin.news.edit', $item) }}" class="font-bold text-ink text-[14px] hover:text-brand-blue truncate block">{{ $item->title_km }}</a>
                            <p class="text-muted text-[12px]">{{ $item->facility?->name_km ?? 'ទូទៅ' }} · {{ $item->author?->name }}</p>
                        </div>
                        @can('approve', $item)
                            <x-admin.confirmation-dialog :action="route('admin.news.approve', $item)" message="អនុម័តព័ត៌មាននេះ?" label="អនុម័ត" class="bg-brand-green text-white" />
                        @endcan
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</x-admin.layout>
