@props(['image' => null, 'title', 'page' => null])

{{-- Pass `page="about"` (a key of App\Models\PageBanner::PAGES) to use the
     admin-managed banner for that page; pass `image` for a record's own
     hardcoded/derived image (article, facility and CMS-page detail views). --}}
@php
    $src = $page ? \App\Models\PageBanner::urlFor($page) : asset($image);
@endphp
<div class="relative h-[260px] overflow-hidden">
    <img src="{{ $src }}" alt="" class="absolute inset-0 size-full object-cover">
    <div class="absolute inset-0 bg-brand-navy opacity-[0.58]"></div>
    <h1 class="absolute left-[var(--public-gutter)] right-[var(--public-gutter)] top-[90px] font-bold text-[34px] md:text-[40px] text-white max-w-[900px]">
        {{ $title }}
    </h1>
</div>
