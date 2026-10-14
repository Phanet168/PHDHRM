@php
    $facilityTabs = [
        'ទំព័រដើម' => true,
        'អំពីយើង' => false,
        'រចនាសម្ព័ន្ធ' => false,
        'សេវាសុខាភិបាល' => false,
        'អង្គការដៃគូ' => false,
        'ព័ត៌មាន' => false,
        'ឯកសារ' => false,
        'ទីតាំង' => false,
    ];
@endphp
<x-layout :title="$facility->name_km">
    <x-page-banner :image="$facility->cover_image ?? 'images/stock/stock-1.jpg'" :title="$facility->name_km" />
    <x-page-header :title="$facility->name_km" :breadcrumbs="[
        ['label' => 'មូលដ្ឋានសុខាភិបាល', 'url' => route('facilities.index')],
        ['label' => $facility->name_km],
    ]" />

    <div class="bg-white flex gap-[18px] items-center overflow-x-auto public-container h-[62px] text-[16px]">
        @foreach ($facilityTabs as $label => $active)
            <span class="{{ $active ? 'font-bold text-brand-blue' : 'text-ink' }} whitespace-nowrap">{{ $label }}</span>
        @endforeach
    </div>

    <div class="bg-surface flex flex-col lg:flex-row gap-7 public-container py-9">
        <div class="flex flex-col gap-[22px] w-full lg:w-[850px]">
            <h1 class="font-bold text-brand-navy text-[26px]">
                {{ $facility->facility_type === 'referral_hospital' ? 'អំពីមន្ទីរពេទ្យ' : 'អំពីមូលដ្ឋាន' }}
            </h1>
            <p class="text-ink text-[16px]">{{ $facility->description }}</p>

            <div class="grid grid-cols-3 gap-3">
                <x-stat-card :value="\App\Support\KhmerDate::toKhmerDigits(number_format($facility->patients_count))" label="អ្នកជំងឺ" />
                <x-stat-card :value="\App\Support\KhmerDate::toKhmerDigits($facility->staff_count)" label="បុគ្គលិក" />
                <x-stat-card :value="\App\Support\KhmerDate::toKhmerDigits($facility->services_count)" label="សេវាសំខាន់" />
            </div>

            @if ($facility->services->isNotEmpty())
                <h2 class="font-bold text-brand-navy text-[24px]">សេវាសុខាភិបាល</h2>
                <div class="flex flex-wrap gap-[10px]">
                    @foreach ($facility->services as $service)
                        <div class="bg-white border border-line rounded-[10px] shadow-[0px_3px_10px_0px_rgba(0,0,0,0.06)] flex flex-col items-center justify-center gap-[10px] h-[90px] w-[150px]">
                            <span class="font-bold text-brand-blue text-[20px]">{{ $service->icon }}</span>
                            <p class="text-ink text-[13px]">{{ $service->name }}</p>
                        </div>
                    @endforeach
                </div>
            @endif

            @if ($relatedNews->isNotEmpty())
                <h2 class="font-bold text-brand-navy text-[24px]">ព័ត៌មាន និងសកម្មភាពថ្មីៗ</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    @foreach ($relatedNews as $article)
                        <a href="{{ route('news.show', $article) }}" class="bg-white border border-line rounded-[10px] shadow-[0px_3px_10px_0px_rgba(0,0,0,0.06)] flex flex-col overflow-hidden">
                            <div class="h-[120px] w-full">
                                <img src="{{ $article->featuredImageUrl() }}" alt="{{ $article->featuredImageAlt() }}" class="size-full object-cover">
                            </div>
                            <div class="px-3 py-3">
                                <p class="font-bold text-brand-navy text-[15px]">{{ $article->title_km }}</p>
                            </div>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="flex flex-col gap-4 w-full lg:w-[400px]">
            @if ($leaders->isNotEmpty())
                @php $leader = $leaders->first(); @endphp
                <div class="bg-white border border-line rounded-[10px] shadow-[0px_3px_10px_0px_rgba(0,0,0,0.06)] flex flex-col gap-[10px] items-start pt-[22px] px-[22px] pb-[22px]">
                    <p class="font-bold text-brand-navy text-[18px]">ថ្នាក់ដឹកនាំ</p>
                    <div class="bg-brand-blue-tint flex items-center justify-center rounded-full size-[76px]">
                        <span class="text-brand-blue text-[32px]">👤</span>
                    </div>
                    <p class="font-bold text-ink text-[15px]">{{ $leader->name }}</p>
                    <p class="text-muted text-[13px]">{{ $leader->title }}</p>
                </div>
            @endif

            <div class="bg-white border border-line rounded-[10px] shadow-[0px_3px_10px_0px_rgba(0,0,0,0.06)] flex flex-col gap-[10px] pl-5 pt-5 pb-5">
                <p class="font-bold text-brand-navy text-[18px]">ទំនាក់ទំនង</p>
                @if ($facility->phone)<p class="text-ink text-[14px]">☎ {{ $facility->phone }}</p>@endif
                @if ($facility->email)<p class="text-ink text-[14px]">✉ {{ $facility->email }}</p>@endif
                @if ($facility->opening_hours)<p class="text-ink text-[14px]">⏰ {{ $facility->opening_hours }}</p>@endif
                @if ($facility->address)<p class="text-ink text-[14px]">⌖ {{ $facility->address }}</p>@endif
            </div>

            <a href="https://www.google.com/maps/search/?api=1&query={{ urlencode($facility->name_km . ' ' . $facility->district) }}" target="_blank" rel="noopener"
               class="bg-brand-blue-tint border border-line rounded-[10px] shadow-[0px_3px_10px_0px_rgba(0,0,0,0.06)] flex flex-col items-center justify-center gap-[10px] h-[130px]">
                <p class="font-bold text-brand-navy text-[20px]">ផែនទីទីតាំង</p>
                <p class="font-bold text-brand-blue text-[14px]">⌖ {{ $facility->name_km }}</p>
            </a>
        </div>
    </div>
</x-layout>
