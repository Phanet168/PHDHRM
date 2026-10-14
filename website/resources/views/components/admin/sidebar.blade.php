@php
    $menu = [
        ['label' => 'ផ្ទាំងគ្រប់គ្រង', 'icon' => '▦', 'route' => 'admin.dashboard', 'active' => request()->routeIs('admin.dashboard*'), 'can' => null],
        ['label' => 'Hero Slider', 'icon' => '▬', 'route' => 'admin.hero-slides.index', 'active' => request()->routeIs('admin.hero-slides.*'), 'can' => 'hero-slides.manage'],
        ['label' => 'សារអប់រំសុខភាព', 'icon' => '♡', 'route' => 'admin.health-messages.index', 'active' => request()->routeIs('admin.health-messages.*'), 'can' => 'health-messages.manage'],
        ['label' => 'អំពីមន្ទីរ', 'icon' => 'ⓘ', 'route' => 'admin.about.index', 'active' => request()->routeIs('admin.about.*'), 'can' => 'about.manage'],
        ['label' => 'ផ្ទាំងបដាទំព័រ', 'icon' => '▭', 'route' => 'admin.page-banners.index', 'active' => request()->routeIs('admin.page-banners.*'), 'can' => 'page-banners.manage'],
        ['label' => 'អនុសាសន៍ថ្នាក់ដឹកនាំ', 'icon' => '★', 'route' => 'admin.leader-recommendations.index', 'active' => request()->routeIs('admin.leader-recommendations.*'), 'can' => 'leader-recommendations.manage'],
        ['label' => 'ទំព័រសំខាន់ៗ', 'icon' => '⛶', 'route' => 'admin.homepage-quick-links.index', 'active' => request()->routeIs('admin.homepage-quick-links.*'), 'can' => 'homepage-quick-links.manage'],
        ['label' => 'ម៉ឺនុយគេហទំព័រ', 'icon' => '☰', 'route' => 'admin.menus.index', 'active' => request()->routeIs('admin.menus.*'), 'can' => 'menus.manage'],
        ['label' => 'មូលដ្ឋានសុខាភិបាល', 'icon' => '✚', 'route' => 'admin.facilities.index', 'active' => request()->routeIs('admin.facilities.*'), 'can' => 'facilities.view'],
        ['label' => 'ព័ត៌មាន និងសកម្មភាព', 'icon' => '▤', 'route' => 'admin.news.index', 'active' => request()->routeIs('admin.news.*'), 'can' => 'news.view'],
        ['label' => 'លិខិតបទដ្ឋានគតិយុត្ត', 'icon' => '▥', 'route' => 'admin.legal-documents.index', 'active' => request()->routeIs('admin.legal-documents.*'), 'can' => 'legal-documents.view'],
        ['label' => 'មាតិកាអប់រំសុខភាព', 'icon' => '♥', 'route' => 'admin.health-content.index', 'active' => request()->routeIs('admin.health-content.*'), 'can' => 'health-content.view'],
        ['label' => 'អង្គការដៃគូ', 'icon' => '⌂', 'route' => 'admin.partners.index', 'active' => request()->routeIs('admin.partners.*'), 'can' => 'partners.manage'],
        ['group' => 'មាតិកា'],
        ['label' => 'ទំព័រ', 'icon' => '▧', 'route' => 'admin.pages.index', 'active' => request()->routeIs('admin.pages.*'), 'can' => 'page.view'],
        ['label' => 'អត្ថបទ', 'icon' => '▤', 'route' => 'admin.content.index', 'active' => request()->routeIs('admin.content.*'), 'can' => 'content.view'],
        ['label' => 'ប្រភេទមាតិកា', 'icon' => '▥', 'route' => 'admin.content-categories.index', 'active' => request()->routeIs('admin.content-categories.*'), 'can' => 'content.view'],
        ['label' => 'Media Library', 'icon' => '▧', 'route' => 'admin.media.index', 'active' => request()->routeIs('admin.media.*'), 'can' => ['media.manage', 'media.view']],
        ['label' => 'អ្នកប្រើប្រាស់', 'icon' => '☺', 'route' => 'admin.users.index', 'active' => request()->routeIs('admin.users.*'), 'can' => 'users.manage'],
        ['label' => 'តួនាទី និងសិទ្ធិ', 'icon' => '⚙', 'route' => 'admin.roles.index', 'active' => request()->routeIs('admin.roles.*'), 'can' => 'roles.manage'],
        ['label' => 'ការកំណត់ប្រព័ន្ធ', 'icon' => '⚑', 'route' => 'admin.settings.edit', 'active' => request()->routeIs('admin.settings.*'), 'can' => 'settings.manage'],
        ['label' => 'Audit Logs', 'icon' => '☰', 'route' => 'admin.audit-logs.index', 'active' => request()->routeIs('admin.audit-logs.*'), 'can' => 'audit-logs.view'],
    ];
@endphp

<aside class="bg-brand-navy w-[260px] shrink-0 hidden lg:flex flex-col">
    <div class="flex items-center gap-[10px] px-5 h-[76px] border-b border-white/10">
        @if (config('site.logo'))
            <img src="{{ asset('storage/' . config('site.logo')) }}" alt="{{ config('site.name_km') }}" class="rounded-full size-[38px] shrink-0 object-cover">
        @else
            <div class="bg-brand-blue flex items-center justify-center rounded-full size-[38px] shrink-0">
                <span class="font-bold text-[18px] text-white">✚</span>
            </div>
        @endif
        <div class="flex flex-col leading-tight min-w-0">
            <span class="font-bold text-white text-[14px] truncate">{{ config('site.name_km') }}</span>
            <span class="text-footer-link text-[11px]">ប្រព័ន្ធគ្រប់គ្រង</span>
        </div>
    </div>

    <nav class="flex-1 overflow-y-auto py-4 flex flex-col gap-1 px-3">
        @foreach ($menu as $item)
            @if (isset($item['group']))
                <p class="text-footer-link/60 text-[11px] font-bold uppercase tracking-wide px-3 pt-3 pb-1">{{ $item['group'] }}</p>
            @elseif (! $item['can'] || (is_array($item['can']) ? auth()->user()->canAny($item['can']) : auth()->user()->can($item['can'])))
                <a href="{{ route($item['route']) }}"
                   class="flex items-center gap-3 px-3 py-[10px] rounded-[8px] text-[14px] {{ $item['active'] ? 'bg-brand-blue text-white font-bold' : 'text-footer-link hover:bg-white/5 hover:text-white' }}">
                    <span class="text-[16px] w-5 text-center">{{ $item['icon'] }}</span>
                    {{ $item['label'] }}
                </a>
            @endif
        @endforeach
    </nav>
</aside>
