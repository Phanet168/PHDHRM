@props(['block'])
@php $items = $block->get('items', []); @endphp
@if (! empty($items))
    <div class="page-block-statistics">
        @foreach ($items as $item)
            <div class="page-block-stat">
                <p class="page-block-stat-value">{{ $item['value'] ?? '' }}{{ $item['suffix'] ?? '' }}</p>
                @if (! empty($item['label']))<p class="page-block-stat-label">{{ $item['label'] }}</p>@endif
            </div>
        @endforeach
    </div>
@endif
