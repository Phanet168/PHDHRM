<x-admin.layout title="កែប្រែអនុសាសន៍ថ្នាក់ដឹកនាំ">
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
        <form method="POST" action="{{ route('admin.leader-recommendations.update', $recommendation) }}" enctype="multipart/form-data" class="lg:col-span-2 bg-white border border-line rounded-[10px] shadow-[0px_3px_10px_0px_rgba(0,0,0,0.06)] p-6 flex flex-col gap-4">
            @csrf @method('PUT')
            @include('admin.leader-recommendations._form')
            <button type="submit" class="bg-brand-blue text-white font-bold text-[14px] rounded-[8px] h-11 px-6 self-start">រក្សាទុក</button>
        </form>

        <div class="bg-white border border-line rounded-[10px] shadow-[0px_3px_10px_0px_rgba(0,0,0,0.06)] p-6 flex flex-col gap-4">
            <p class="font-bold text-brand-navy text-[15px]">រូបភាពថ្នាក់ដឹកនាំ</p>
            @if ($recommendation->media)
                <img src="{{ $recommendation->media->url }}" class="w-full h-[160px] object-cover rounded-[8px]">
            @endif
            <form method="POST" action="{{ route('admin.leader-recommendations.update', $recommendation) }}" enctype="multipart/form-data" class="flex flex-col gap-2">
                @csrf @method('PUT')
                <input type="hidden" name="title_km" value="{{ $recommendation->title_km }}">
                <input type="hidden" name="leader_name_km" value="{{ $recommendation->leader_name_km }}">
                <x-admin.upload-field name="image_file" label="ជំនួសរូបភាព" accept="image/jpeg,image/png,image/webp" />
                @include('admin.pages._media-picker', [
                    'name' => 'media_id',
                    'selected' => $recommendation->media_id,
                    'defaultFolderId' => $recommendationFolderId,
                ])
                @if ($recommendation->media)
                    <label class="flex items-center gap-2 text-[12px] text-brand-red">
                        <input type="checkbox" name="remove_media" value="1">
                        យកចេញរូបភាព
                    </label>
                @endif
                <button type="submit" class="bg-brand-blue text-white font-bold text-[13px] rounded-[8px] h-9 px-4 self-start">រក្សាទុករូបភាព</button>
            </form>
        </div>
    </div>

    <div class="bg-white border border-line rounded-[10px] shadow-[0px_3px_10px_0px_rgba(0,0,0,0.06)] p-6 mt-6">
        <p class="font-bold text-brand-navy text-[15px] mb-1">អនុសាសន៍ (Recommendation Items)</p>
        <p class="text-muted text-[12px] mb-4">អូសរូបតំណាង ⠿ ដើម្បីតម្រៀបលំដាប់។ ទំព័រដើមបង្ហាញតែ ៥ ដំបូងដែលបានបើកប៉ុណ្ណោះ។</p>

        <div data-block-sort-root data-reorder-url="{{ route('admin.leader-recommendations.items.reorder', $recommendation) }}">
            <ul class="content-block-list flex flex-col gap-2">
                @forelse ($recommendation->items as $item)
                    <li class="block-item border border-line rounded-[8px]" data-id="{{ $item->id }}" x-data="{ editing: false }">
                        <div class="flex items-center gap-3 px-3 py-[10px]">
                            <span class="block-drag-handle cursor-move text-muted select-none">⠿</span>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2">
                                    <span class="text-[11px] text-muted">#{{ $loop->iteration }}</span>
                                    @if (! $item->is_active)
                                        <span class="bg-gray-100 text-gray-500 text-[10px] font-bold rounded-full px-2 py-[2px]">បិទ</span>
                                    @endif
                                </div>
                                <p class="text-ink text-[13px] mt-1">{{ $item->content_km }}</p>
                            </div>
                            <div class="flex items-center gap-2 shrink-0 text-[12px] font-bold">
                                <button type="button" @click="editing = ! editing" class="text-brand-blue">កែប្រែ</button>
                                <form method="POST" action="{{ route('admin.leader-recommendations.items.toggle', [$recommendation, $item]) }}">
                                    @csrf
                                    <button type="submit" class="{{ $item->is_active ? 'text-muted' : 'text-brand-green' }}">{{ $item->is_active ? 'បិទ' : 'បើក' }}</button>
                                </form>
                                <form method="POST" action="{{ route('admin.leader-recommendations.items.destroy', [$recommendation, $item]) }}" onsubmit="return confirm('លុបអនុសាសន៍នេះ?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="text-brand-red">លុប</button>
                                </form>
                            </div>
                        </div>
                        <div x-show="editing" x-cloak class="border-t border-line p-4">
                            <form method="POST" action="{{ route('admin.leader-recommendations.items.update', [$recommendation, $item]) }}" class="flex flex-col gap-3">
                                @csrf @method('PUT')
                                <x-admin.textarea name="content_km" label="អត្ថបទអនុសាសន៍" :value="$item->content_km" rows="2" required />
                                <div class="flex justify-end">
                                    <button type="submit" class="bg-brand-blue text-white font-bold text-[13px] rounded-[8px] h-9 px-4">រក្សាទុក</button>
                                </div>
                            </form>
                        </div>
                    </li>
                @empty
                    <li class="text-muted text-[13px] py-6 text-center list-none">មិនទាន់មានអនុសាសន៍ទេ។</li>
                @endforelse
            </ul>
        </div>

        <form method="POST" action="{{ route('admin.leader-recommendations.items.store', $recommendation) }}" class="border-t border-line mt-4 pt-4 flex flex-col gap-2">
            @csrf
            <x-admin.textarea name="content_km" label="បន្ថែមអនុសាសន៍ថ្មី" rows="2" placeholder="សរសេរអត្ថបទអនុសាសន៍..." required />
            <button type="submit" class="bg-brand-green text-white font-bold text-[13px] rounded-[8px] h-9 px-4 self-start">បន្ថែម</button>
        </form>
    </div>
</x-admin.layout>
