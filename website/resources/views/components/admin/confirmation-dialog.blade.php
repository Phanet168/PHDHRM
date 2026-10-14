@props(['action', 'method' => 'POST', 'message' => 'តើអ្នកប្រាកដទេ?', 'label', 'class' => 'bg-brand-blue text-white'])

<form method="POST" action="{{ $action }}" onsubmit="return confirm('{{ $message }}')">
    @csrf
    @if (strtoupper($method) !== 'POST')
        @method($method)
    @endif
    <button type="submit" {{ $attributes->merge(['class' => $class . ' font-bold text-[13px] rounded-[8px] h-9 px-4']) }}>
        {{ $label }}
    </button>
</form>
