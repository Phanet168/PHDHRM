<x-admin.layout title="កែប្រែកាតទំព័រសំខាន់">
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
        <form method="POST" action="{{ route('admin.homepage-quick-links.update', $link) }}" class="lg:col-span-2 bg-white border border-line rounded-[10px] shadow-[0px_3px_10px_0px_rgba(0,0,0,0.06)] p-6 flex flex-col gap-4">
            @csrf @method('PUT')
            @include('admin.homepage-quick-links._form')
            <button type="submit" class="bg-brand-blue text-white font-bold text-[14px] rounded-[8px] h-11 px-6 self-start">រក្សាទុក</button>
        </form>

        {{-- Image panel: reuses the same Media Library picker partial as the Page/Content
             block editors (admin.pages._media-picker), unmodified — only the surrounding
             preview here is specific to this card. --}}
        <div class="bg-white border border-line rounded-[10px] shadow-[0px_3px_10px_0px_rgba(0,0,0,0.06)] p-6 flex flex-col gap-4"
             x-data="{ mediaUrl: @js($link->imageUrl()) }"
             @change="if ($event.target.type === 'radio') { mediaUrl = $event.target.closest('label')?.querySelector('img')?.getAttribute('src') ?? null }">
            <p class="font-bold text-brand-navy text-[15px]">រូបភាព</p>

            <template x-if="mediaUrl">
                <img :src="mediaUrl" class="w-full aspect-[16/7] object-cover rounded-[8px] border border-line">
            </template>
            <template x-if="!mediaUrl">
                <div class="w-full aspect-[16/7] rounded-[8px] border border-dashed border-line flex items-center justify-center text-muted text-[12px]">
                    គ្មានរូបភាព
                </div>
            </template>

            @if ($link->media)
                <p class="text-muted text-[11px]">
                    {{ $link->media->original_filename }}
                    @if ($link->media->dimensions()) · {{ $link->media->dimensions() }} @endif
                    · {{ $link->media->formattedSize() }}
                </p>
            @endif

            <form method="POST" action="{{ route('admin.homepage-quick-links.update', $link) }}" class="flex flex-col gap-2">
                @csrf @method('PUT')
                {{-- This is a second, independent form, so the main card fields (still
                     required by validation) are re-submitted unchanged as hidden inputs. --}}
                <input type="hidden" name="title_km" value="{{ $link->title_km }}">
                <input type="hidden" name="icon" value="{{ $link->icon }}">
                <input type="hidden" name="link_type" value="{{ $link->link_type }}">
                <input type="hidden" name="route_name" value="{{ $link->route_name }}">
                <input type="hidden" name="url" value="{{ $link->url }}">
                <input type="hidden" name="is_active" value="{{ $link->is_active ? 1 : 0 }}">

                @include('admin.pages._media-picker', ['name' => 'media_id', 'selected' => $link->media_id, 'defaultFolderId' => $linkFolderId])

                <div class="flex gap-2">
                    <button type="submit" class="bg-brand-blue text-white font-bold text-[13px] rounded-[8px] h-9 px-4">ជ្រើសរូបពី Media Library</button>
                    <button type="button"
                        @click="
                            const none = $el.closest('form').querySelector('.media-picker input[value=\'\']');
                            none.checked = true;
                            none.dispatchEvent(new Event('change', { bubbles: true }));
                        "
                        class="text-brand-red font-bold text-[13px] rounded-[8px] h-9 px-4 border border-line">
                        លុបរូប
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-admin.layout>
