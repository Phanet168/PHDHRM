<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NewsAttachment extends Model
{
    protected $fillable = ['news_id', 'media_id', 'label', 'sort_order'];

    public function news(): BelongsTo
    {
        return $this->belongsTo(News::class);
    }

    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }

    /** The admin's own label if set, otherwise the file's original name. */
    public function displayName(): string
    {
        return $this->label ?: ($this->media?->original_filename ?? 'ឯកសារ');
    }

    public function url(): ?string
    {
        return $this->media?->url;
    }

    public function formattedSize(): ?string
    {
        return $this->media?->formattedSize();
    }

    public function iconType(): string
    {
        return $this->media?->iconType() ?? 'file';
    }
}
