<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Media;
use App\Models\Page;
use App\Models\PageBlock;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PageBlockService
{
    public const BLOCK_TYPES = [
        'heading', 'text', 'image', 'image_text', 'gallery', 'button',
        'two_columns', 'cards', 'video', 'quote', 'statistics',
        'contact_info', 'map', 'html_css',
    ];

    public function __construct(private MediaManagerService $mediaManager)
    {
    }

    public function create(Page $page, string $blockType, array $inputData, Request $request): PageBlock
    {
        $inputData = $this->resolveMediaFields($blockType, $inputData, $request, $page);
        $data = $this->sanitizeData($blockType, $inputData);

        $block = $page->blocks()->create([
            'block_type' => $blockType,
            'data' => $data,
            'is_active' => true,
            'sort_order' => $page->blocks()->count(),
        ]);

        AuditLog::record('created', 'PageBlock', $block->id, "បន្ថែម Block ({$blockType})៖ {$page->title_km}");

        return $block;
    }

    public function update(PageBlock $block, array $inputData, Request $request): void
    {
        $inputData = $this->resolveMediaFields($block->block_type, $inputData, $request, $block->page, $block);
        $data = $this->sanitizeData($block->block_type, $inputData);

        $block->update(['data' => $data]);

        AuditLog::record('updated', 'PageBlock', $block->id, "កែប្រែ Block៖ {$block->page->title_km}");
    }

    public function duplicate(PageBlock $block): PageBlock
    {
        $copy = $block->duplicate();

        AuditLog::record('created', 'PageBlock', $copy->id, "ចម្លង Block៖ {$block->page->title_km}");

        return $copy;
    }

    public function toggle(PageBlock $block): void
    {
        $block->update(['is_active' => ! $block->is_active]);
    }

    public function delete(PageBlock $block): void
    {
        $pageTitle = $block->page->title_km;
        $block->delete();

        AuditLog::record('deleted', 'PageBlock', null, "លុប Block៖ {$pageTitle}");
    }

    public function reorder(Page $page, array $items): void
    {
        foreach ($items as $row) {
            $block = PageBlock::find($row['id']);
            if ($block && $block->page_id === $page->id) {
                $block->update(['sort_order' => $row['sort_order']]);
            }
        }
    }

    /**
     * Resolves the two ways an image-bearing block can receive an image: a
     * fresh upload, or an existing Media Library record picked by ID. Falls
     * back to whatever path the block already had (on update) so leaving
     * both fields empty doesn't wipe an existing image.
     */
    private function resolveMediaFields(string $blockType, array $data, Request $request, Page $page, ?PageBlock $existing = null): array
    {
        if (! in_array($blockType, ['image', 'image_text'], true)) {
            return $data;
        }

        if ($request->hasFile('image_file')) {
            $folderId = $this->mediaManager->resolveFolderForRecord('pages', $page, "#{$page->id} — {$page->title_km}");
            $data['image'] = MediaUploader::store($request->file('image_file'), 'pages/blocks', MediaUploader::IMAGE_ONLY_EXTENSIONS, MediaUploader::IMAGE_ONLY_MIMES, $folderId);

            return $data;
        }

        if (! empty($data['media_id'])) {
            $media = Media::find($data['media_id']);
            if ($media) {
                $data['image'] = $media->disk_path;

                return $data;
            }
        }

        $data['image'] = $data['image'] ?? $existing?->get('image');

        return $data;
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
            'image_text' => [
                'image' => $data['image'] ?? null,
                'media_id' => $data['media_id'] ?? null,
                'alt_text' => strip_tags($data['alt_text'] ?? ''),
                'heading' => strip_tags($data['heading'] ?? ''),
                'html' => HtmlSanitizer::clean($data['html'] ?? ''),
                'image_position' => ($data['image_position'] ?? 'left') === 'right' ? 'right' : 'left',
            ],
            'gallery' => [
                'media_ids' => array_values(array_filter(array_map('intval', $data['media_ids'] ?? []))),
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
            'cards' => [
                'cards' => $this->sanitizeRepeatable($data['cards'] ?? [], ['title', 'text'], ['link_url']),
            ],
            'video' => [
                'url' => $this->safeUrl($data['url'] ?? ''),
                'caption' => strip_tags($data['caption'] ?? ''),
            ],
            'quote' => [
                'text' => strip_tags($data['text'] ?? ''),
                'author' => strip_tags($data['author'] ?? ''),
            ],
            'statistics' => [
                'items' => $this->sanitizeRepeatable($data['items'] ?? [], ['label', 'value'], ['suffix']),
            ],
            'contact_info' => [
                'address' => strip_tags($data['address'] ?? ''),
                'phone' => strip_tags($data['phone'] ?? ''),
                'email' => strip_tags($data['email'] ?? ''),
                'hours' => strip_tags($data['hours'] ?? ''),
            ],
            'map' => [
                'lat' => is_numeric($data['lat'] ?? null) ? (float) $data['lat'] : null,
                'lng' => is_numeric($data['lng'] ?? null) ? (float) $data['lng'] : null,
                'zoom' => is_numeric($data['zoom'] ?? null) ? (int) $data['zoom'] : 14,
                'marker_title' => strip_tags($data['marker_title'] ?? ''),
            ],
            'html_css' => [
                'html' => HtmlSanitizer::clean($data['html'] ?? ''),
                'css' => CssScoper::stripDangerousConstructs($data['css'] ?? ''),
            ],
            default => throw ValidationException::withMessages(['block_type' => 'ប្រភេទ Block មិនត្រឹមត្រូវ។']),
        };
    }

    /**
     * Cleans a fixed-slot repeatable field (cards / statistics): each row is
     * kept only if at least one of its $requiredKeys has content, plain
     * fields are strip_tags'd, and $urlKeys go through the URL scheme guard.
     */
    private function sanitizeRepeatable(array $rows, array $requiredKeys, array $urlKeys = []): array
    {
        $clean = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $hasContent = false;
            $sanitized = [];

            foreach ($row as $key => $value) {
                if (in_array($key, $urlKeys, true)) {
                    $sanitized[$key] = ($safe = $this->safeUrl((string) $value)) === '#' ? null : $safe;
                } else {
                    $sanitized[$key] = strip_tags((string) $value);
                }
                if (in_array($key, $requiredKeys, true) && trim((string) $value) !== '') {
                    $hasContent = true;
                }
            }

            if ($hasContent) {
                $clean[] = $sanitized;
            }
        }

        return $clean;
    }

    private function safeUrl(string $url): string
    {
        if ($url === '' || $url === '#') {
            return '#';
        }

        if (str_starts_with($url, '/')) {
            return $url;
        }

        $normalized = strtolower(trim(preg_replace('/[\x00-\x1F\x7F]/', '', $url) ?? ''));

        if (str_starts_with($normalized, 'javascript:') || str_starts_with($normalized, 'vbscript:') || str_starts_with($normalized, 'data:')) {
            return '#';
        }

        return filter_var($url, FILTER_VALIDATE_URL) ? $url : '#';
    }
}
