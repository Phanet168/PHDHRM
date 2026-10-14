<x-admin.layout :title="$document->title">
    <div class="flex items-center justify-between mb-5 flex-wrap gap-3">
        <div class="flex items-center gap-3">
            <p class="font-bold text-brand-navy text-[18px]">{{ $document->title }}</p>
            <x-admin.status-badge :status="$document->status" />
        </div>
        <div class="flex gap-2">
            @can('publish', $document)
                @if ($document->status !== 'published')
                    <x-admin.confirmation-dialog :action="route('admin.legal-documents.publish', $document)" message="ផ្សាយឯកសារនេះ?" label="ផ្សាយ" class="bg-brand-green text-white" />
                @else
                    <x-admin.confirmation-dialog :action="route('admin.legal-documents.archive', $document)" message="ទុកឯកសារនេះជាឯកសារយោង?" label="ទុកជាឯកសារ" class="bg-gray-200 text-ink" />
                @endif
            @endcan
            @can('delete', $document)
                <x-admin.confirmation-dialog :action="route('admin.legal-documents.destroy', $document)" method="DELETE" message="លុបឯកសារនេះជាអចិន្ត្រៃយ៍?" label="លុប" class="bg-red-50 text-brand-red" />
            @endcan
        </div>
    </div>

    <form method="POST" action="{{ route('admin.legal-documents.update', $document) }}" enctype="multipart/form-data" class="bg-white border border-line rounded-[10px] shadow-[0px_3px_10px_0px_rgba(0,0,0,0.06)] p-6 flex flex-col gap-4 max-w-[700px]">
        @csrf @method('PUT')
        @include('admin.legal-documents._form')
        <div class="flex justify-end gap-3 pt-2">
            <a href="{{ route('admin.legal-documents.index') }}" class="text-muted text-[14px] font-bold px-4 py-2">ត្រឡប់</a>
            <button type="submit" class="bg-brand-blue text-white font-bold text-[14px] rounded-[8px] h-11 px-6">រក្សាទុក</button>
        </div>
    </form>
</x-admin.layout>
