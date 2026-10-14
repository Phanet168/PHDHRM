<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class LegalDocument extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'category_id',
        'title',
        'document_number',
        'issuing_authority',
        'issue_date',
        'year',
        'description',
        'file_path',
        'external_url',
        'external_format',
        'status',
        'published_at',
        'created_by',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'published_at' => 'datetime',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(LegalDocumentCategory::class, 'category_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            'draft' => 'សេចក្តីព្រាង',
            'published' => 'បានផ្សាយ',
            'archived' => 'បានទុកក្នុងឯកសារ',
        };
    }

    /** An uploaded file, or a link to a file hosted elsewhere (e.g. a shared Google Drive PDF). */
    public function hasDownload(): bool
    {
        return filled($this->file_path) || filled($this->external_url);
    }

    public function fileExtension(): string
    {
        $extension = pathinfo($this->file_path ?? '', PATHINFO_EXTENSION);

        return strtoupper($extension !== '' ? $extension : (string) $this->external_format);
    }

    public function iconType(): string
    {
        return match (strtolower($this->fileExtension())) {
            'pdf' => 'pdf',
            'doc', 'docx' => 'word',
            'xls', 'xlsx' => 'excel',
            default => 'file',
        };
    }

    public function formattedFileSize(): ?string
    {
        if (! $this->file_path || ! Storage::disk('public')->exists($this->file_path)) {
            return null;
        }

        $size = Storage::disk('public')->size($this->file_path);
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($size >= 1024 && $i < count($units) - 1) {
            $size /= 1024;
            $i++;
        }

        return round($size, 1) . ' ' . $units[$i];
    }
}
