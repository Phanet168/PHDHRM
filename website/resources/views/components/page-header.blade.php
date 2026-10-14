@props(['title', 'breadcrumbs' => null])

@php
    $crumbs = $breadcrumbs ?? [['label' => $title]];
@endphp

<div class="bg-brand-blue public-container py-4 flex flex-col md:flex-row md:items-center md:justify-between gap-2">
    <h1 class="font-bold text-white text-[20px] md:text-[22px] truncate">{{ $title }}</h1>

    <nav aria-label="breadcrumb" class="flex items-center gap-2 text-[13px] text-white/75 flex-wrap md:justify-end">
        <a href="{{ route('home') }}" class="hover:text-white whitespace-nowrap">ទំព័រដើម</a>
        @foreach ($crumbs as $crumb)
            <span aria-hidden="true" class="text-white/50">/</span>
            @if (! $loop->last && ! empty($crumb['url']))
                <a href="{{ $crumb['url'] }}" class="hover:text-white truncate max-w-[220px]">{{ $crumb['label'] }}</a>
            @else
                <span class="text-white font-bold truncate max-w-[260px]" aria-current="page">{{ $crumb['label'] }}</span>
            @endif
        @endforeach
    </nav>
</div>
