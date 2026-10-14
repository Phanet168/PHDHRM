<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Content;
use App\Models\ContentBlock;
use App\Services\CssScoper;
use App\Services\HtmlSanitizer;
use App\Services\MediaManagerService;
use App\Services\MediaUploader;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ContentBlockController extends Controller
{
    private const BLOCK_TYPES = [
        'heading', 'text', 'image', 'gallery', 'button',
        'two_columns', 'video', 'quote', 'html', 'html_css',
    ];

    public function store(Request $request, Content $content): RedirectResponse
    {
        $this->authorize('update', $content);

        $blockType = $request->input('block_type');
        $request->validate(['block_type' => ['required', Rule::in(self::BLOCK_TYPES)]]);

        if ($blockType === 'html_css') {
            $this->authorize('useCustomHtml', Content::class);
        }

        $inputData = $request->input('data', []);
        if ($blockType === 'image') {
            $inputData['image'] = $this->resolveImageBlockPath($request, $inputData, $content);
        }

        $data = $this->sanitizeData($blockType, $inputData);

        $block = $content->blocks()->create([
            'block_type' => $blockType,
            'data' => $data,
            'is_active' => true,
            'sort_order' => $content->blocks()->count(),
        ]);

        AuditLog::record('created', 'ContentBlock', $block->id, "បន្ថែម Block ({$blockType})៖ {$content->title_km}");

        return back()->with('status', 'បានបន្ថែម Block ដោយជោគជ័យ។');
    }

    public function update(Request $request, Content $content, ContentBlock $block): RedirectResponse
    {
        $this->authorize('update', $content);
        abort_unless($block->content_id === $content->id, 404);

        if ($block->block_type === 'html_css') {
            $this->authorize('useCustomHtml', Content::class);
        }

        $inputData = $request->input('data', []);
        if ($block->block_type === 'image') {
            $inputData['image'] = $this->resolveImageBlockPath($request, $inputData, $content) ?: $block->get('image');
        }

        $data = $this->sanitizeData($block->block_type, $inputData);

        $block->update(['data' => $data]);

        AuditLog::record('updated', 'ContentBlock', $block->id, "កែប្រែ Block៖ {$content->title_km}");

        return back()->with('status', 'បានធ្វើបច្ចុប្បន្នភាព Block ដោយជោគជ័យ។');
    }

    public function destroy(Content $content, ContentBlock $block): RedirectResponse
    {
        $this->authorize('update', $content);
        abort_unless($block->content_id === $content->id, 404);

        $block->delete();

        return back()->with('status', 'បានលុប Block ដោយជោគជ័យ។');
    }

    public function duplicate(Content $content, ContentBlock $block): RedirectResponse
    {
        $this->authorize('update', $content);
        abort_unless($block->content_id === $content->id, 404);

        if ($block->block_type === 'html_css') {
            $this->authorize('useCustomHtml', Content::class);
        }

        $block->duplicate();

        return back()->with('status', 'បានចម្លង Block ដោយជោគជ័យ។');
    }

    public function toggle(Content $content, ContentBlock $block): RedirectResponse
    {
        $this->authorize('update', $content);
        abort_unless($block->content_id === $content->id, 404);

        $block->update(['is_active' => ! $block->is_active]);

        return back()->with('status', 'បានផ្លាស់ប្តូរស្ថានភាព Block ដោយជោគជ័យ។');
    }

    public function reorder(Request $request, Content $content): JsonResponse
    {
        $this->authorize('update', $content);

        $request->validate([
            'items' => ['required', 'array'],
            'items.*.id' => ['required', 'integer', 'exists:content_blocks,id'],
            'items.*.sort_order' => ['required', 'integer'],
        ]);

        foreach ($request->input('items') as $row) {
            $block = ContentBlock::find($row['id']);
            if ($block && $block->content_id === $content->id) {
                $block->update(['sort_order' => $row['sort_order']]);
            }
        }

        return response()->json(['status' => 'ok']);
    }

    private function sanitizeData(string $blockType, array $data): array
    {
        return match ($blockType) {
            'heading' => [
                'text' => strip_tags($data['text'] ?? ''),
                'level' => in_array($data['level'] ?? 'h2', ['h1', 'h2', 'h3', 'h4'], true) ? $data['level'] : 'h2',
            ],
            'text' => [
                'html' => HtmlSanitizer::clean($data['html'] ?? ''),
            ],
            'image' => [
                'image' => $data['image'] ?? null,
                'media_id' => $data['media_id'] ?? null,
                'caption' => strip_tags($data['caption'] ?? ''),
                'alt_text' => strip_tags($data['alt_text'] ?? ''),
                'link_url' => ($safeLink = $this->safeUrl($data['link_url'] ?? '#')) === '#' ? null : $safeLink,
            ],
            'gallery' => [
                'image_ids' => array_values(array_filter(array_map('intval', $data['image_ids'] ?? []))),
            ],
            'button' => [
                'text' => strip_tags($data['text'] ?? 'ចុចទីនេះ'),
                'url' => $this->safeUrl($data['url'] ?? '#'),
                'style' => in_array($data['style'] ?? 'primary', ['primary', 'secondary', 'outline'], true) ? $data['style'] : 'primary',
                'target' => ($data['target'] ?? '_self') === '_blank' ? '_blank' : '_self',
            ],
            'two_columns' => [
                'left_html' => HtmlSanitizer::clean($data['left_html'] ?? ''),
                'right_html' => HtmlSanitizer::clean($data['right_html'] ?? ''),
            ],
            'video' => [
                'url' => $this->safeUrl($data['url'] ?? ''),
                'caption' => strip_tags($data['caption'] ?? ''),
            ],
            'quote' => [
                'text' => strip_tags($data['text'] ?? ''),
                'author' => strip_tags($data['author'] ?? ''),
            ],
            'html' => [
                'html' => HtmlSanitizer::clean($data['html'] ?? ''),
            ],
            'html_css' => [
                'html' => HtmlSanitizer::clean($data['html'] ?? ''),
                'css' => CssScoper::stripDangerousConstructs($data['css'] ?? ''),
            ],
            default => throw ValidationException::withMessages(['block_type' => 'ប្រភេទ Block មិនត្រឹមត្រូវ។']),
        };
    }

    private function resolveImageBlockPath(Request $request, array $data, Content $content): ?string
    {
        if ($request->hasFile('image_file')) {
            $folderId = app(MediaManagerService::class)->resolveFolderForRecord('content', $content, "#{$content->id} — {$content->title_km}");

            return MediaUploader::store($request->file('image_file'), 'content/blocks', MediaUploader::IMAGE_ONLY_EXTENSIONS, MediaUploader::IMAGE_ONLY_MIMES, $folderId);
        }

        if (! empty($data['media_id'])) {
            $media = \App\Models\Media::find($data['media_id']);
            if ($media) {
                return $media->disk_path;
            }
        }

        return $data['image'] ?? null;
    }

    private function safeUrl(string $url): string
    {
        if ($url === '' || $url === '#') {
            return '#';
        }

        if (str_starts_with($url, '/')) {
            return $url;
        }

        // Browsers strip ASCII control characters (tabs, newlines, etc.) before
        // evaluating a URI's scheme, so "java\tscript:" still executes. Strip
        // them here too before checking for a dangerous scheme.
        $normalized = strtolower(trim(preg_replace('/[\x00-\x1F\x7F]/', '', $url) ?? ''));

        if (str_starts_with($normalized, 'javascript:') || str_starts_with($normalized, 'vbscript:') || str_starts_with($normalized, 'data:')) {
            return '#';
        }

        return filter_var($url, FILTER_VALIDATE_URL) ? $url : '#';
    }
}
