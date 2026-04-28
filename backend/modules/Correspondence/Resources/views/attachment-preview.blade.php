<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $fileName ?? 'មើលឯកសារភ្ជាប់' }}</title>
    <style>
        html, body { width: 100%; height: 100%; margin: 0; }
        body {
            font-family: Arial, sans-serif;
            background: #f7f7f7;
            display: flex;
            flex-direction: column;
        }
        .toolbar { display: flex; align-items: center; gap: 12px; padding: 12px 16px; background: #fff; border-bottom: 1px solid #e5e7eb; flex-shrink: 0; }
        .toolbar .name { font-weight: 600; }
        .toolbar a { text-decoration: none; color: #2563eb; font-weight: 600; }
        .content {
            padding: 16px;
            flex: 1;
            min-height: 0;
        }
        .content-inner {
            width: 100%;
            height: 100%;
            min-height: 480px;
            background: #fff;
            border: 1px solid #e5e7eb;
            overflow: auto;
        }
        iframe {
            width: 100%;
            height: 100%;
            border: none;
            display: block;
            background: #fff;
        }
        img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            display: block;
            background: #fff;
        }
        .fallback { background: #fff; padding: 24px; border: 1px dashed #d1d5db; color: #6b7280; }
    </style>
</head>
<body>
    <div class="toolbar">
        <span class="name">{{ $fileName }}</span>
        <a href="{{ $downloadUrl ?? ($fileUrl . '?download=1') }}">{{ localize('download', 'ទាញយក') }}</a>
    </div>
    <div class="content">
        @if ($isPreviewable)
            <div class="content-inner">
                @if ($fileExt === 'pdf')
                    <object data="{{ $fileUrl }}#view=FitH&zoom=100" type="application/pdf" width="100%" height="100%">
                        <embed src="{{ $fileUrl }}#view=FitH&zoom=100" type="application/pdf" width="100%" height="100%" />
                        <div class="fallback">
                            {{ localize('pdf_preview_unavailable', 'មិនអាចបង្ហាញ PDF ដោយផ្ទាល់បាន។ សូមចុចប៊ូតុងទាញយក។') }}
                        </div>
                    </object>
                @else
                    <iframe src="{{ $fileUrl }}" title="{{ $fileName }}"></iframe>
                @endif
            </div>
        @else
            <div class="fallback">
                {{ localize('preview_not_supported', 'មិនគាំទ្រការមើលផ្ទាល់សម្រាប់ប្រភេទឯកសារនេះ។ សូមទាញយកដើម្បីមើល។') }}
            </div>
        @endif
    </div>
</body>
</html>

