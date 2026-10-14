<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PageBanner extends Model
{
    /**
     * Every public page whose top banner is admin-managed. A page not
     * listed here keeps its own hardcoded/record-derived banner (article,
     * facility, and CMS-page detail views already use their own image).
     * 'default' is the stock image each page used before this was
     * manageable, so nothing changes until an admin picks a new one.
     */
    public const PAGES = [
        'about' => ['label' => 'អំពីមន្ទីរសុខាភិបាល', 'default' => 'images/stock/stock-3.jpg', 'route' => 'about'],
        'contact' => ['label' => 'ទំនាក់ទំនង', 'default' => 'images/stock/stock-1.jpg', 'route' => 'contact'],
        'facilities' => ['label' => 'មូលដ្ឋានសុខាភិបាល', 'default' => 'images/stock/stock-1.jpg', 'route' => 'facilities.index'],
        'news' => ['label' => 'ព័ត៌មាន និងសកម្មភាព', 'default' => 'images/stock/stock-2.jpg', 'route' => 'news.index'],
        'legal-documents' => ['label' => 'លិខិតបទដ្ឋានគតិយុត្ត', 'default' => 'images/stock/stock-4.jpg', 'route' => 'legal-documents.index'],
        'health-content' => ['label' => 'មាតិកាអប់រំសុខភាព', 'default' => 'images/stock/stock-3.jpg', 'route' => 'health-content.index'],
        'content-categories' => ['label' => 'ទំព័រប្រភេទអត្ថបទ (ទាំងអស់)', 'default' => 'images/stock/stock-2.jpg', 'route' => null],
    ];

    protected $fillable = ['page_key', 'media_id'];

    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'media_id');
    }

    public static function isKnown(string $pageKey): bool
    {
        return array_key_exists($pageKey, self::PAGES);
    }

    public static function label(string $pageKey): string
    {
        return self::PAGES[$pageKey]['label'] ?? $pageKey;
    }

    public static function defaultUrl(string $pageKey): string
    {
        return asset(self::PAGES[$pageKey]['default'] ?? 'images/stock/stock-1.jpg');
    }

    /**
     * The banner image for a page: the admin's chosen Media Library image,
     * else the page's original stock image. A trashed or deleted Media row
     * makes the belongsTo return null, so this can never yield a broken URL.
     */
    public static function urlFor(string $pageKey): string
    {
        $media = static::where('page_key', $pageKey)->first()?->media;

        return $media && $media->isImage() ? $media->url : self::defaultUrl($pageKey);
    }
}
