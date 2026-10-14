<x-admin.layout title="អំពីមន្ទីរ">
    @include('admin.about._nav')

    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
        @foreach ($cards as $card)
            @php
                $state = $card['total'] === 0 ? ['មិនទាន់មានទិន្នន័យ', 'bg-gray-100 text-gray-500'] : ($card['active'] > 0 ? ['បើកប្រើ', 'bg-brand-green-tint text-brand-green'] : ['បិទប្រើ', 'bg-amber-100 text-amber-700']);
            @endphp
            <div class="bg-white border border-line rounded-[10px] shadow-[0px_3px_10px_0px_rgba(0,0,0,0.06)] p-5 flex flex-col gap-3">
                <div class="flex items-start gap-3">
                    <span class="bg-brand-blue-tint text-brand-blue font-bold text-[18px] rounded-[10px] size-[42px] shrink-0 flex items-center justify-center" aria-hidden="true">{{ $card['icon'] }}</span>
                    <div class="min-w-0">
                        <p class="font-bold text-brand-navy text-[15px] leading-snug">{{ $card['title'] }}</p>
                        <span class="{{ $state[1] }} inline-flex rounded-full px-3 py-0.5 mt-1 text-[12px] font-bold">{{ $state[0] }}</span>
                    </div>
                </div>
                <dl class="grid grid-cols-2 gap-2 text-[12px]">
                    <div><dt class="text-muted">ចំនួន (បើក / សរុប)</dt><dd class="font-bold text-ink text-[15px]">{{ $card['active'] }} / {{ $card['total'] }}</dd></div>
                    <div><dt class="text-muted">កែចុងក្រោយ</dt><dd class="font-bold text-ink">{{ $card['updated'] ? \Illuminate\Support\Carbon::parse($card['updated'])->format('d/m/Y H:i') : '—' }}</dd></div>
                </dl>
                @if (! empty($card['note']))<p class="text-muted text-[12px]">{{ $card['note'] }}</p>@endif
                @if ($card['demo'] > 0)<p class="text-amber-700 text-[12px] font-bold">⚠ មានទិន្នន័យគំរូ (DEMO) {{ $card['demo'] }} — សូមជំនួសដោយខ្លឹមសារផ្លូវការ</p>@endif
                <a href="{{ $card['route'] }}" class="mt-auto bg-brand-blue text-white font-bold text-[13px] rounded-[8px] h-9 px-4 inline-flex items-center justify-center">កែសម្រួល</a>
            </div>
        @endforeach
    </div>
</x-admin.layout>
