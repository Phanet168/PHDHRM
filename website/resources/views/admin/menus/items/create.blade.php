<x-admin.layout :title="'បន្ថែមធាតុម៉ឺនុយ · ' . $menu->name">
    <form method="POST" action="{{ route('admin.menus.items.store', $menu) }}" class="bg-white border border-line rounded-[10px] shadow-[0px_3px_10px_0px_rgba(0,0,0,0.06)] p-6 max-w-[700px]">
        @csrf
        @include('admin.menus.items._form')
        <div class="flex justify-end gap-3 pt-4">
            <a href="{{ route('admin.menus.edit', $menu) }}" class="text-muted text-[14px] font-bold px-4 py-2">បោះបង់</a>
            <button type="submit" class="bg-brand-blue text-white font-bold text-[14px] rounded-[8px] h-11 px-6">រក្សាទុក</button>
        </div>
    </form>
</x-admin.layout>
