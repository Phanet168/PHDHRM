<x-admin.layout title="ផ្ទាំងបដាទំព័រ">
    <p class="text-muted text-[13px] mb-5 max-w-[720px]">
        គ្រប់គ្រងរូបភាពបដាខាងលើនៃទំព័រសាធារណៈនីមួយៗ។ ទំព័រដែលមិនទាន់កំណត់ នឹងប្រើរូបភាពលំនាំដើម។
    </p>

    <x-admin.data-table :headers="['ទំព័រ', 'រូបបដាបច្ចុប្បន្ន', 'ប្រភព', '']">
        @foreach ($pages as $pageKey => $page)
            @php
                $banner = $banners->get($pageKey);
                $isCustom = $banner?->media && $banner->media->isImage();
            @endphp
            <tr>
                <td class="px-4 py-3 font-bold text-ink text-[14px]">
                    {{ $page['label'] }}
                    @if ($page['route'])
                        <a href="{{ route($page['route']) }}" target="_blank" rel="noopener" class="block text-brand-blue text-[12px] font-normal mt-0.5">មើលទំព័រ ↗</a>
                    @endif
                </td>
                <td class="px-4 py-3">
                    <img src="{{ \App\Models\PageBanner::urlFor($pageKey) }}" alt="" class="h-[60px] w-[160px] object-cover rounded-[6px] border border-line">
                </td>
                <td class="px-4 py-3">
                    <span class="{{ $isCustom ? 'bg-brand-green-tint text-brand-green' : 'bg-gray-100 text-gray-500' }} inline-flex rounded-full px-3 py-1 text-[12px] font-bold">
                        {{ $isCustom ? 'កំណត់ដោយអ្នកគ្រប់គ្រង' : 'រូបលំនាំដើម' }}
                    </span>
                </td>
                <td class="px-4 py-3 text-right">
                    <a href="{{ route('admin.page-banners.edit', $pageKey) }}" class="text-brand-blue text-[13px] font-bold">កែប្រែ</a>
                </td>
            </tr>
        @endforeach
    </x-admin.data-table>
</x-admin.layout>
