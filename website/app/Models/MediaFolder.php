<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class MediaFolder extends Model
{
    use SoftDeletes;

    protected $fillable = ['parent_id', 'name', 'slug', 'created_by', 'owner_type', 'owner_id'];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * The single content record (News, Content, Page, ...) this folder is
     * dedicated to, when it was created via
     * MediaManagerService::resolveFolderForRecord() rather than as a plain
     * module-wide or manually-created folder.
     */
    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('name');
    }

    public function allChildren(): HasMany
    {
        return $this->children()->with('allChildren');
    }

    public function media(): HasMany
    {
        return $this->hasMany(Media::class, 'folder_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * This folder's ancestor chain, root first, for breadcrumb rendering.
     */
    public function breadcrumbTrail(): array
    {
        $trail = [];
        $node = $this;

        while ($node) {
            array_unshift($trail, $node);
            $node = $node->parent;
        }

        return $trail;
    }

    /**
     * IDs of this folder and every folder nested beneath it, used to scope
     * "include subfolders" searches and to guard against circular moves.
     */
    public function descendantIds(): array
    {
        $ids = [$this->id];

        foreach (self::where('parent_id', $this->id)->get() as $child) {
            $ids = array_merge($ids, $child->descendantIds());
        }

        return $ids;
    }

    /**
     * Flat, indented [id => "— Name"] list of every folder, for a folder
     * picker <select>. $excludeIds keeps a folder (and its own descendants,
     * when moving a folder) out of its own picker options.
     */
    public static function optionsTree(array $excludeIds = []): array
    {
        $options = [];

        $walk = function ($parentId, $depth) use (&$walk, &$options, $excludeIds) {
            $children = self::where('parent_id', $parentId)->orderBy('name')->get();

            foreach ($children as $folder) {
                if (in_array($folder->id, $excludeIds, true)) {
                    continue;
                }

                $options[$folder->id] = str_repeat('— ', $depth) . $folder->name;
                $walk($folder->id, $depth + 1);
            }
        };

        $walk(null, 0);

        return $options;
    }
}
