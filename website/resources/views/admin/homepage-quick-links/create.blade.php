<x-admin.layout title="បង្កើតកាតទំព័រសំខាន់">
    <form method="POST" action="{{ route('admin.homepage-quick-links.store') }}" class="bg-white border border-line rounded-[10px] shadow-[0px_3px_10px_0px_rgba(0,0,0,0.06)] p-6 flex flex-col gap-4 max-w-[700px]">
        @csrf
        @include('admin.homepage-quick-links._form')
        <p class="text-muted text-[12px]">អ្នកអាចជ្រើសរើសរូបភាពពី Media Library បន្ទាប់ពីបង្កើតកាតនេះ។</p>
        <div class="flex justify-end gap-3 pt-2">
            <a href="{{ route('admin.homepage-quick-links.index') }}" class="text-muted text-[14px] font-bold px-4 py-2">បោះបង់</a>
            <button type="submit" class="bg-brand-blue text-white font-bold text-[14px] rounded-[8px] h-11 px-6">បង្កើត</button>
        </div>
    </form>
</x-admin.layout>
