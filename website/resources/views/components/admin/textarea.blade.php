@props(['name', 'label' => null, 'value' => null, 'rows' => 5, 'required' => false])

<div>
    @if ($label)
        <label for="{{ $name }}" class="block text-ink text-[13px] font-bold mb-1">
            {{ $label }} @if($required)<span class="text-brand-red">*</span>@endif
        </label>
    @endif
    <textarea name="{{ $name }}" id="{{ $name }}" rows="{{ $rows }}" @if($required) required @endif
              {{ $attributes->merge(['class' => 'bg-white border border-line rounded-[8px] px-[14px] py-3 text-[14px] text-ink w-full']) }}>{{ old($name, $value) }}</textarea>
    @error($name)
        <p class="text-brand-red text-[12px] mt-1">{{ $message }}</p>
    @enderror
</div>
