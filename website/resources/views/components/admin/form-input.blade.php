@props(['name', 'label' => null, 'type' => 'text', 'value' => null, 'required' => false])

<div>
    @if ($label)
        <label for="{{ $name }}" class="block text-ink text-[13px] font-bold mb-1">
            {{ $label }} @if($required)<span class="text-brand-red">*</span>@endif
        </label>
    @endif
    <input type="{{ $type }}" name="{{ $name }}" id="{{ $name }}"
           value="{{ old($name, $value) }}" @if($required) required @endif
           {{ $attributes->merge(['class' => 'bg-white border border-line rounded-[8px] h-[44px] px-[14px] text-[14px] text-ink w-full']) }}>
    @error($name)
        <p class="text-brand-red text-[12px] mt-1">{{ $message }}</p>
    @enderror
</div>
