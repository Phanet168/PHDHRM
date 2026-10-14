@props(['name', 'placeholder', 'value' => null, 'type' => 'text', 'options' => null])

@if ($options)
    <select name="{{ $name }}" class="bg-white border border-line rounded-[8px] h-[46px] px-[14px] text-[14px] text-muted w-full md:w-[230px]">
        <option value="">{{ $placeholder }}</option>
        @foreach ($options as $optionValue => $optionLabel)
            <option value="{{ $optionValue }}" @selected($value === (string) $optionValue)>{{ $optionLabel }}</option>
        @endforeach
    </select>
@else
    <input type="{{ $type }}" name="{{ $name }}" value="{{ $value }}" placeholder="{{ $placeholder }}"
           class="bg-white border border-line rounded-[8px] h-[46px] px-[14px] text-[14px] text-ink placeholder:text-muted w-full md:w-[230px]">
@endif
