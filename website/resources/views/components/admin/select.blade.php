@props(['name', 'label' => null, 'options' => [], 'value' => null, 'required' => false, 'placeholder' => '— ជ្រើសរើស —'])

@php $selected = old($name, $value); @endphp

<div>
    @if ($label)
        <label for="{{ $name }}" class="block text-ink text-[13px] font-bold mb-1">
            {{ $label }} @if($required)<span class="text-brand-red">*</span>@endif
        </label>
    @endif
    <select name="{{ $name }}" id="{{ $name }}" @if($required) required @endif
            {{ $attributes->merge(['class' => 'bg-white border border-line rounded-[8px] h-[44px] px-[14px] text-[14px] text-ink w-full']) }}>
        @if ($placeholder)
            <option value="">{{ $placeholder }}</option>
        @endif
        @foreach ($options as $optionValue => $optionLabel)
            <option value="{{ $optionValue }}" @selected((string) $selected === (string) $optionValue)>{{ $optionLabel }}</option>
        @endforeach
    </select>
    @error($name)
        <p class="text-brand-red text-[12px] mt-1">{{ $message }}</p>
    @enderror
</div>
