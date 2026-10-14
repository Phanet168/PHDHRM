<x-admin.layout title="កែប្រែអ្នកប្រើប្រាស់">
    <form method="POST" action="{{ route('admin.users.update', $user) }}" class="bg-white border border-line rounded-[10px] shadow-[0px_3px_10px_0px_rgba(0,0,0,0.06)] p-6 flex flex-col gap-4 max-w-[600px]">
        @csrf @method('PUT')
        @include('admin.users._form')
        <div class="flex justify-end gap-3 pt-2">
            <a href="{{ route('admin.users.index') }}" class="text-muted text-[14px] font-bold px-4 py-2">ត្រឡប់</a>
            <button type="submit" class="bg-brand-blue text-white font-bold text-[14px] rounded-[8px] h-11 px-6">រក្សាទុក</button>
        </div>
    </form>
</x-admin.layout>
