<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PageBlock extends Model
{
    protected $fillable = ['page_id', 'block_type', 'data', 'is_active', 'sort_order'];

    protected $casts = [
        'data' => 'array',
        'is_active' => 'boolean',
    ];

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return data_get($this->data, $key, $default);
    }

    public static function typeLabel(string $type): string
    {
        return match ($type) {
            'heading' => 'ចំណងជើង (Heading)',
            'text' => 'អត្ថបទ (Text)',
            'image' => 'រូបភាព (Image)',
            'image_text' => 'រូបភាព + អត្ថបទ (Image + Text)',
            'gallery' => 'វិចិត្រសាល (Gallery)',
            'button' => 'ប៊ូតុង (Button)',
            'two_columns' => 'ពីរជួរឈរ (Two Columns)',
            'cards' => 'កាតព័ត៌មាន (Cards)',
            'video' => 'វីដេអូ (Video)',
            'quote' => 'សម្រង់ (Quote)',
            'statistics' => 'ស្ថិតិ (Statistics)',
            'contact_info' => 'ព័ត៌មានទំនាក់ទំនង (Contact Info)',
            'map' => 'ផែនទី (Map)',
            'html_css' => 'HTML + CSS',
            default => $type,
        };
    }

    public function duplicate(): self
    {
        $copy = $this->replicate();
        $copy->sort_order = $this->page->blocks()->count();
        $copy->save();

        return $copy;
    }

    /**
     * Resolves a video block's URL into an embeddable form: a YouTube/Vimeo
     * iframe embed, or a direct <video> source for plain file links.
     *
     * @return array{type: string, src: string}|null
     */
    public static function videoEmbed(?string $url): ?array
    {
        return \App\Support\VideoEmbed::resolve($url);
    }
}
