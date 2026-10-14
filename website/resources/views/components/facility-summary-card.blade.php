@props(['icon', 'count', 'label', 'color'])

<div class="facility-summary-card">
    <div>
        <p class="facility-summary-count">{{ \App\Support\KhmerDate::toKhmerDigits((string) $count) }}</p>
        <p class="facility-summary-label">{{ $label }}</p>
    </div>
    <div class="facility-summary-icon" style="background: {{ $color }}1a; color: {{ $color }};">
        <span class="text-[20px]">{{ $icon }}</span>
    </div>
</div>
