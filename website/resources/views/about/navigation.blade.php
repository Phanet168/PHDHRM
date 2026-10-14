@foreach ($menu as $id => $label)
    <a href="#{{ $id }}" data-about-link="{{ $id }}" class="{{ $id === $active ? 'is-active' : '' }}" @if ($id === $active) aria-current="location" @endif><span class="about-nav-icon"><x-news-icon :name="$menuIcons[$id] ?? 'document'" width="17" height="17" /></span><span class="about-nav-label">{{ $label }}</span><x-news-icon name="chevron-right" width="16" height="16" /></a>
@endforeach
