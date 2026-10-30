{{-- One person in the organization chart. Variants: chief / deputy (portrait cards), head / member (rows inside a unit card). --}}
<div class="org-person is-{{ $variant ?? 'member' }}">
    @if ($person['photo'] ?? null)
        <img class="org-person-photo" src="{{ $person['photo'] }}" alt="" width="104" height="104" loading="lazy" decoding="async">
    @else
        <span class="org-person-photo" aria-hidden="true"><x-news-icon name="user" /></span>
    @endif
    <span class="org-person-text"><strong>{{ $person['name'] }}</strong><small>{{ $person['title'] }}</small></span>
</div>
