@props(['name', 'label' => null, 'accept' => 'image/*', 'current' => null])

<div>
    @if ($label)
        <label for="{{ $name }}" class="block text-ink text-[13px] font-bold mb-1">{{ $label }}</label>
    @endif

    @if ($current)
        <img src="{{ asset('storage/' . $current) }}" alt="" class="h-[90px] w-[140px] object-cover rounded-[8px] border border-line mb-2">
    @endif

    <input type="file" name="{{ $name }}" id="{{ $name }}" accept="{{ $accept }}"
           class="block w-full text-[13px] text-muted file:mr-3 file:py-2 file:px-4 file:rounded-[8px] file:border-0 file:bg-brand-blue-tint file:text-brand-blue file:font-bold file:text-[13px]">
    @error($name)
        <p class="text-brand-red text-[12px] mt-1">{{ $message }}</p>
    @enderror
</div>
