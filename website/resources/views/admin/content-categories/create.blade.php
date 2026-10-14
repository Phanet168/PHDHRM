<x-admin.layout title="បង្កើតប្រភេទមាតិកា">
    <form method="POST" action="{{ route('admin.content-categories.store') }}" class="bg-white border border-line rounded-[10px] shadow-[0px_3px_10px_0px_rgba(0,0,0,0.06)] p-6 flex flex-col gap-4 max-w-[700px]">
        @csrf
        @include('admin.content-categories._form')
        <div class="flex justify-end gap-3 pt-2">
            <a href="{{ route('admin.content-categories.index') }}" class="text-muted text-[14px] font-bold px-4 py-2">ត្រឡប់</a>
            <button type="submit" class="bg-brand-blue text-white font-bold text-[14px] rounded-[8px] h-11 px-6">រក្សាទុក</button>
        </div>
    </form>
</x-admin.layout>
