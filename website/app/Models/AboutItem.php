<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

/** Repeatable About records: core info cards, timeline milestones and related documents. */
class AboutItem extends Model
{
    use SoftDeletes;

    public const GROUPS = [
        'cards' => 'ចក្ខុវិស័យ / បេសកកម្ម / តួនាទី / គោលតម្លៃ',
        'timeline' => 'ប្រវត្តិ និងការអភិវឌ្ឍ',
        'documents' => 'ឯកសារពាក់ព័ន្ធ',
    ];

    protected $fillable = [
        'group', 'title', 'body', 'date_label', 'icon_key', 'accent', 'link_url',
        'media_id', 'legal_document_id', 'is_active', 'sort_order', 'is_demo',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_demo' => 'boolean',
    ];

    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'media_id');
    }

    public function legalDocument(): BelongsTo
    {
        return $this->belongsTo(LegalDocument::class);
    }

    public function scopeInGroup($query, string $group)
    {
        return $query->where('group', $group);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Download details for a document row, from its Media Library file or a published
     * legal document. Null when the source was removed or is no longer public, so the
     * public page drops the row instead of linking to nothing.
     *
     * @return array{url: string, icon: string, type: string, size: ?string, external: bool}|null
     */
    public function download(): ?array
    {
        if ($this->media && ! $this->media->fileMissing()) {
            return ['url' => $this->media->url, 'icon' => $this->media->iconType(), 'type' => $this->media->typeLabel(), 'size' => $this->media->formattedSize(), 'external' => false];
        }

        $document = $this->legalDocument;
        if ($document && $document->status === 'published' && $document->hasDownload()) {
            $size = $document->file_path && Storage::disk('public')->exists($document->file_path)
                ? (new Media(['size' => Storage::disk('public')->size($document->file_path)]))->formattedSize()
                : null;

            return ['url' => route('legal-documents.download', $document), 'icon' => $document->iconType(), 'type' => $document->fileExtension() ?: 'FILE', 'size' => $size, 'external' => ! $document->file_path];
        }

        return null;
    }
}
