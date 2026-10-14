<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PageReviewLog extends Model
{
    protected $fillable = ['page_id', 'reviewer_id', 'action', 'note'];

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public static function actionLabel(string $action): string
    {
        return match ($action) {
            'submitted' => 'បានដាក់ស្នើសម្រាប់ការត្រួតពិនិត្យ',
            'approved' => 'បានអនុម័ត',
            'rejected' => 'បានច្រានចោល',
            'published' => 'បានផ្សាយ',
            'unpublished' => 'បានដកចេញពីការផ្សាយ',
            'archived' => 'បានទុកក្នុងឯកសារ',
            default => $action,
        };
    }
}
