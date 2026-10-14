<x-admin.layout title="បង្កើតម៉ឺនុយ">
    <form method="POST" action="{{ route('admin.menus.store') }}" class="bg-white border border-line rounded-[10px] shadow-[0px_3px_10px_0px_rgba(0,0,0,0.06)] p-6 flex flex-col gap-4 max-w-[600px]">
        @csrf
        <x-admin.form-input name="name" label="ឈ្មោះម៉ឺនុយ (ឧ. Header Menu)" required />
        <x-admin.form-input name="slug" label="Slug (ឧ. header, footer, mobile)" required />
        <label class="flex items-center gap-2 text-[13px] text-ink">
            <input type="checkbox" name="is_active" value="1" class="rounded border-line">
            ផ្សាយភ្លាមៗ (បើមិនធីក នឹងរក្សាទុកជាសេចក្តីព្រាង)
        </label>
        <div class="flex justify-end gap-3 pt-2">
            <a href="{{ route('admin.menus.index') }}" class="text-muted text-[14px] font-bold px-4 py-2">បោះបង់</a>
            <button type="submit" class="bg-brand-blue text-white font-bold text-[14px] rounded-[8px] h-11 px-6">រក្សាទុក</button>
        </div>
    </form>
</x-admin.layout>
