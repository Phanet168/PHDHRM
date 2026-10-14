@props(['document'])

<a href="{{ route('legal-documents.download', $document) }}" class="home-document-item">
    <span class="home-document-icon">
        <x-media-file-icon :type="$document->iconType()" :size="36" />
    </span>
    <span class="home-document-body">
        <span class="home-document-title" title="{{ $document->title }}">{{ $document->title }}</span>
        <span class="home-document-meta">
            {{ $document->issue_date?->format('d-m-Y') }}
            @if ($document->formattedFileSize())
                · {{ $document->fileExtension() }} · {{ $document->formattedFileSize() }}
            @endif
        </span>
    </span>
</a>
