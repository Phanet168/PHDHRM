<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Page;
use App\Models\PageReviewLog;
use Illuminate\Support\Str;
use RuntimeException;

class PageService
{
    public function __construct(private PageMenuSyncService $menuSync)
    {
    }

    public function uniqueSlug(string $base, ?int $ignoreId = null): string
    {
        $slug = Str::slug($base) ?: 'page';
        $candidate = $slug;
        $i = 2;

        while (
            Page::withTrashed()->where('slug', $candidate)
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $candidate = "{$slug}-{$i}";
            $i++;
        }

        return $candidate;
    }

    public function duplicate(Page $page): Page
    {
        $copy = $page->replicate([
            'status', 'published_at', 'approved_at', 'reviewer_id', 'slug', 'created_at', 'updated_at',
        ]);
        $copy->title_km = $page->title_km . ' (Copy)';
        $copy->slug = $this->uniqueSlug($page->slug . '-copy');
        $copy->status = 'draft';
        $copy->published_at = null;
        $copy->approved_at = null;
        $copy->reviewer_id = null;
        $copy->created_by = auth()->id();
        $copy->author_id = auth()->id();
        $copy->save();

        foreach ($page->blocks as $block) {
            $blockCopy = $block->replicate();
            $blockCopy->page_id = $copy->id;
            $blockCopy->save();
        }

        AuditLog::record('created', 'Page', $copy->id, "ចម្លងទំព័រ៖ {$page->title_km} → {$copy->title_km}");

        return $copy;
    }

    public function submitForReview(Page $page): void
    {
        $page->update(['status' => 'pending_review']);

        PageReviewLog::create(['page_id' => $page->id, 'reviewer_id' => auth()->id(), 'action' => 'submitted']);
        AuditLog::record('submitted_for_review', 'Page', $page->id, "ដាក់ទំព័រសម្រាប់ត្រួតពិនិត្យ៖ {$page->title_km}");
    }

    public function approve(Page $page, ?string $note = null): void
    {
        $page->update([
            'status' => 'approved',
            'reviewer_id' => auth()->id(),
            'approved_at' => now(),
        ]);

        PageReviewLog::create(['page_id' => $page->id, 'reviewer_id' => auth()->id(), 'action' => 'approved', 'note' => $note]);
        AuditLog::record('approved', 'Page', $page->id, "អនុម័តទំព័រ៖ {$page->title_km}");
    }

    public function reject(Page $page, string $note): void
    {
        $page->update(['status' => 'draft']);

        PageReviewLog::create(['page_id' => $page->id, 'reviewer_id' => auth()->id(), 'action' => 'rejected', 'note' => $note]);
        AuditLog::record('updated', 'Page', $page->id, "ច្រានចោលទំព័រ៖ {$page->title_km}");
    }

    public function publish(Page $page): void
    {
        $page->update([
            'status' => 'published',
            'published_at' => $page->published_at ?? now(),
        ]);

        $this->menuSync->reactivateMenuItems($page);

        PageReviewLog::create(['page_id' => $page->id, 'reviewer_id' => auth()->id(), 'action' => 'published']);
        AuditLog::record('published', 'Page', $page->id, "បានផ្សាយទំព័រ៖ {$page->title_km}");
    }

    public function unpublish(Page $page): void
    {
        $page->update(['status' => 'draft']);

        $this->menuSync->deactivateMenuItems($page);

        PageReviewLog::create(['page_id' => $page->id, 'reviewer_id' => auth()->id(), 'action' => 'unpublished']);
        AuditLog::record('unpublished', 'Page', $page->id, "ដកទំព័រពីការផ្សាយ៖ {$page->title_km}");
    }

    public function archive(Page $page): void
    {
        $page->update(['status' => 'archived']);

        $this->menuSync->deactivateMenuItems($page);

        PageReviewLog::create(['page_id' => $page->id, 'reviewer_id' => auth()->id(), 'action' => 'archived']);
        AuditLog::record('unpublished', 'Page', $page->id, "ទុកទំព័រក្នុងឯកសារ៖ {$page->title_km}");
    }

    public function trash(Page $page): void
    {
        $this->menuSync->deactivateMenuItems($page);
        $page->delete();

        AuditLog::record('deleted', 'Page', $page->id, "ទុកទំព័រក្នុងធុងសម្រាម៖ {$page->title_km}");
    }

    public function restore(Page $page): void
    {
        $page->restore();

        AuditLog::record('updated', 'Page', $page->id, "សង្គ្រោះទំព័រ៖ {$page->title_km}");
    }

    /**
     * Permanently removes a page. Refuses if any menu item still points at
     * it (the caller should remove/relink those first) so a menu is never
     * left with a dangling reference.
     */
    public function forceDelete(Page $page): void
    {
        if ($page->menuItems()->exists()) {
            throw new RuntimeException('ទំព័រនេះកំពុងភ្ជាប់ជាមួយម៉ឺនុយ។ សូមដកចេញពីម៉ឺនុយជាមុនសិន។');
        }

        $title = $page->title_km;
        $id = $page->id;
        $page->blocks()->delete();
        $page->reviewLogs()->delete();
        $page->forceDelete();

        AuditLog::record('deleted', 'Page', $id, "លុបទំព័រជាអចិន្ត្រៃយ៍៖ {$title}");
    }
}
