<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ContentImage extends Model
{
    protected $fillable = ['content_id', 'media_id', 'image', 'caption', 'alt_text', 'is_featured', 'sort_order'];

    protected $casts = [
        'is_featured' => 'boolean',
    ];

    public function content(): BelongsTo
    {
        return $this->belongsTo(Content::class);
    }

    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }

    public function url(): string
    {
        return Storage::disk('public')->url($this->image);
    }
}
