@once
    <style>
        .site-logo { position: relative; display: inline-flex; align-items: center; justify-content: center; width: var(--site-logo-size, 42px); height: var(--site-logo-size, 42px); flex-shrink: 0; overflow: hidden; border-radius: 0; background: transparent; }
        .site-logo-fallback { display: flex; align-items: center; justify-content: center; width: 100%; height: 100%; border-radius: 50%; background: #005cad; color: white; font: 700 calc(var(--site-logo-size, 42px) * .57)/1 sans-serif; }
        .site-logo-fallback[hidden] { display: none; }
        .site-logo-image { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: contain; background: transparent; opacity: 0; }
    </style>
@endonce
<span {{ $attributes->class('site-logo') }}>
    <span class="site-logo-fallback" aria-hidden="true">✚</span>
    @if (config('site.logo_url'))
        <img class="site-logo-image" src="{{ config('site.logo_url') }}" alt="{{ config('site.name_km') }}"
             @if (config('site.default_logo_url') && config('site.default_logo_url') !== config('site.logo_url')) data-fallback="{{ config('site.default_logo_url') }}" @endif
             onload="this.style.opacity='1'; this.previousElementSibling.hidden=true;"
             onerror="this.style.opacity='0'; this.previousElementSibling.hidden=false; if (this.dataset.fallback) { const fallback=this.dataset.fallback; delete this.dataset.fallback; this.src=fallback; }">
    @endif
</span>
