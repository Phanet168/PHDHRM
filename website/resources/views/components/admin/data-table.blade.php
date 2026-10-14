@props(['headers' => []])

<div class="bg-white border border-line rounded-[10px] shadow-[0px_3px_10px_0px_rgba(0,0,0,0.06)] overflow-x-auto">
    <table class="min-w-full">
        <thead>
            <tr class="bg-surface border-b border-line text-[12px] text-muted">
                {{-- A page can override the plain header row with a `header` slot (sortable/filterable
                     columns — see admin/news/index.blade.php) while every other caller keeps the
                     simple `:headers="[...]"` array untouched. --}}
                @isset($header)
                    {{ $header }}
                @else
                    @foreach ($headers as $header)
                        <th class="text-left font-bold px-4 py-3 whitespace-nowrap">{{ $header }}</th>
                    @endforeach
                @endisset
            </tr>
        </thead>
        <tbody class="divide-y divide-line">
            {{ $slot }}
        </tbody>
    </table>
</div>
