{{-- Row actions for an About record: toggle / edit / soft delete, or restore / force delete in the trash. --}}
<div class="flex justify-end flex-wrap gap-2">
    @if ($trashed)
        <form method="POST" action="{{ $restoreUrl }}">@csrf<button type="submit" class="bg-brand-blue-tint text-brand-blue font-bold text-[13px] rounded-[8px] h-9 px-4">ស្ដារ</button></form>
        <x-admin.confirmation-dialog :action="$forceUrl" method="DELETE" message="លុបជាអចិន្ត្រៃយ៍? មិនអាចស្ដារវិញបានទេ (ឯកសារក្នុង Media Library នៅដដែល)។" label="លុបជាអចិន្ត្រៃយ៍" class="bg-red-50 text-brand-red" />
    @else
        <form method="POST" action="{{ $toggleUrl }}">@csrf<button type="submit" class="{{ $active ? 'bg-gray-100 text-gray-600' : 'bg-brand-green-tint text-brand-green' }} font-bold text-[13px] rounded-[8px] h-9 px-4">{{ $active ? 'បិទប្រើ' : 'បើកប្រើ' }}</button></form>
        <a href="{{ $editUrl }}" class="bg-brand-blue text-white font-bold text-[13px] rounded-[8px] h-9 px-4 inline-flex items-center">កែសម្រួល</a>
        <x-admin.confirmation-dialog :action="$destroyUrl" method="DELETE" message="លុប «{{ addslashes($name) }}»? អាចស្ដារវិញពីធុងសំរាម។" label="លុប" class="bg-red-50 text-brand-red" />
    @endif
</div>
