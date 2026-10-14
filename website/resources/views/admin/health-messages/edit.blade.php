<x-admin.layout title="កែប្រែសារអប់រំសុខភាព">
    <form method="POST" action="{{ route('admin.health-messages.update', $message) }}" class="bg-white border border-line rounded-[10px] shadow-[0px_3px_10px_0px_rgba(0,0,0,0.06)] p-6 flex flex-col gap-4 max-w-[700px]">
        @csrf @method('PUT')
        @include('admin.health-messages._form')
        <div class="flex justify-end gap-3 pt-2">
            <a href="{{ route('admin.health-messages.index') }}" class="text-muted text-[14px] font-bold px-4 py-2">ត្រឡប់</a>
            <button type="submit" class="bg-brand-blue text-white font-bold text-[14px] rounded-[8px] h-11 px-6">រក្សាទុក</button>
        </div>
    </form>
</x-admin.layout>
