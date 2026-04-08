<?php

namespace App\Models;

use App\Models\HR\EmployeeUnitAssignment;
use App\Models\HR\UnitHead;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class OrganizationalUnit extends Model
{
    use SoftDeletes;

    protected $table = 'organizational_units';

    protected $fillable = [
        'code',
        'name',
        'short_name',
        'description',
        'type',
        'parent_id',
        'path',
        'depth',
        'order_index',
        'division_id',
        'office_id',
        'is_active',
        'established_date',
        'abolished_date',
        'legal_basis',
        'mandate',
        'remarks',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'is_active'        => 'boolean',
        'established_date' => 'date',
        'abolished_date'   => 'date',
        'depth'            => 'integer',
        'order_index'      => 'integer',
    ];

    // ── Type Constants ─────────────────────────────────────────────────────────

    const TYPE_INSTITUTION = 'institution';
    const TYPE_DIVISION    = 'division';
    const TYPE_DEPARTMENT  = 'department';
    const TYPE_SECTION     = 'section';
    const TYPE_UNIT        = 'unit';
    const TYPE_OFFICE      = 'office';
    const TYPE_COMMITTEE   = 'committee';

    const TYPES = [
        self::TYPE_INSTITUTION,
        self::TYPE_DIVISION,
        self::TYPE_DEPARTMENT,
        self::TYPE_SECTION,
        self::TYPE_UNIT,
        self::TYPE_OFFICE,
        self::TYPE_COMMITTEE,
    ];

    // ── Model Boot — Audit & Integrity ─────────────────────────────────────────

    protected static function boot(): void
    {
        parent::boot();

        // Stamp audit fields automatically
        static::creating(function (self $unit) {
            $unit->created_by ??= Auth::id();
            $unit->updated_by   = Auth::id();
        });

        static::updating(function (self $unit) {
            $unit->updated_by = Auth::id();
        });

        static::deleting(function (self $unit) {
            $unit->deleted_by = Auth::id();
            $unit->saveQuietly();
        });

        // Recompute path + depth whenever parent_id changes
        static::saving(function (self $unit) {
            if ($unit->isDirty('parent_id')) {
                // Guard: prevent circular reference before touching the DB
                if ($unit->parent_id !== null) {
                    $unit->guardCircularReference($unit->parent_id);
                }
                $unit->recomputePath();
            }
        });

        // After save, cascade path/depth update to all descendants
        static::saved(function (self $unit) {
            if ($unit->wasChanged('parent_id') || $unit->wasChanged('path')) {
                $unit->cascadePathUpdate();
            }
        });
    }

    // ── Relationships ──────────────────────────────────────────────────────────

    /** Direct parent unit. */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** Direct children units, ordered by order_index. */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')
            ->orderBy('order_index')
            ->orderBy('name');
    }

    /** Active children only. */
    public function activeChildren(): HasMany
    {
        return $this->children()->where('is_active', true);
    }

    /** Backward-compat: linked legacy division. */
    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }

    /** Backward-compat: linked legacy office. */
    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class);
    }

    /** All employee assignments (including historical). */
    public function assignments(): HasMany
    {
        return $this->hasMany(EmployeeUnitAssignment::class);
    }

    /** Currently active employee assignments. */
    public function activeAssignments(): HasMany
    {
        return $this->assignments()
            ->whereNull('end_date')
            ->whereNull('deleted_at');
    }

    /** All head designations (including historical). */
    public function headDesignations(): HasMany
    {
        return $this->hasMany(UnitHead::class);
    }

    /** Current active head designation. */
    public function currentHead(): HasMany
    {
        return $this->headDesignations()
            ->where('is_current', true)
            ->whereNull('end_date');
    }

    /** Creator user. */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ── Query Scopes ───────────────────────────────────────────────────────────

    /** Only root (top-level) units. */
    public function scopeRoot($query)
    {
        return $query->whereNull('parent_id');
    }

    /** Only active units. */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /** Filter by type. */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * All units that are within the subtree of a given ancestor unit.
     * Uses the materialized path for O(1) index lookup.
     */
    public function scopeDescendantsOf($query, self $ancestor)
    {
        return $query->where('path', 'like', $ancestor->path . $ancestor->id . '/%');
    }

    // ── Hierarchy Engine — Path & Depth ────────────────────────────────────────

    /**
     * Recompute this unit's materialized path and depth from its parent.
     * Called automatically in the `saving` hook when parent_id changes.
     */
    public function recomputePath(): void
    {
        if ($this->parent_id === null) {
            $this->path  = '/';
            $this->depth = 0;
        } else {
            $parent = static::findOrFail($this->parent_id);
            // Path: parent's path + parent's id + '/'
            // e.g., root(id=1) → path='/' → child(id=4) → path='/1/'
            $this->path  = $parent->path . $parent->id . '/';
            $this->depth = $parent->depth + 1;
        }
    }

    /**
     * After a parent_id change, cascade the updated path/depth to ALL
     * descendants using a single bulk-update query (avoids N+1 recursion).
     */
    public function cascadePathUpdate(): void
    {
        // Fetch all descendants via old materialized path or by recursive query
        $descendants = static::withTrashed()
            ->where('path', 'like', $this->path . $this->id . '/%')
            ->orWhere('path', 'like', '/' . $this->id . '/%')
            ->get();

        foreach ($descendants as $desc) {
            $desc->recomputePath();
            $desc->saveQuietly();
        }
    }

    // ── Hierarchy Engine — Circular Reference Guard ────────────────────────────

    /**
     * Prevent circular references in the adjacency list.
     * Walks UP the ancestor chain of the proposed parent to ensure
     * this unit does not already appear in it.
     *
     * @throws \RuntimeException if a circular reference is detected
     */
    public function guardCircularReference(int $proposedParentId): void
    {
        if ($this->exists && $proposedParentId === $this->id) {
            throw new \RuntimeException(
                "Circular reference: a unit cannot be its own parent."
            );
        }

        // Walk up via recursive CTE to get all ancestor IDs of the proposed parent
        $ancestors = $this->getAncestorIds($proposedParentId);

        if ($this->exists && in_array($this->id, $ancestors, strict: true)) {
            throw new \RuntimeException(
                "Circular reference detected: unit #{$this->id} is already an ancestor of #{$proposedParentId}."
            );
        }
    }

    /**
     * Return all ancestor IDs for a given unit ID via recursive CTE.
     */
    public function getAncestorIds(int $unitId): array
    {
        $rows = DB::select(<<<SQL
            WITH RECURSIVE ancestors AS (
                SELECT id, parent_id
                FROM organizational_units
                WHERE id = ? AND deleted_at IS NULL

                UNION ALL

                SELECT ou.id, ou.parent_id
                FROM organizational_units ou
                INNER JOIN ancestors a ON ou.id = a.parent_id
                WHERE ou.deleted_at IS NULL
            )
            SELECT id FROM ancestors WHERE id != ?
        SQL, [$unitId, $unitId]);

        return array_column($rows, 'id');
    }

    // ── Hierarchy Engine — Tree Traversal ──────────────────────────────────────

    /**
     * Retrieve all ancestors (from root to immediate parent) using the
     * materialized path. Most efficient — single indexed query.
     *
     * @return Collection<self>
     */
    public function getAncestors(): Collection
    {
        if ($this->path === '/' || $this->path === null) {
            return collect();
        }

        // Extract IDs from the materialized path string: '/1/4/7/' → [1, 4, 7]
        $ids = array_filter(explode('/', $this->path));

        if (empty($ids)) {
            return collect();
        }

        // Preserve order (root → immediate parent)
        return static::whereIn('id', $ids)
            ->orderByRaw('FIELD(id, ' . implode(',', $ids) . ')')
            ->get();
    }

    /**
     * Retrieve all descendants using the materialized path index.
     * Includes ALL levels below this unit.
     *
     * @return Collection<self>
     */
    public function getDescendants(): Collection
    {
        return static::where('path', 'like', $this->path . $this->id . '/%')
            ->orderBy('depth')
            ->orderBy('order_index')
            ->get();
    }

    /**
     * Build a nested tree array of this unit and all descendants.
     * Uses a single query and builds the tree in PHP — no N+1.
     *
     * @param  bool  $onlyActive  Exclude inactive units from the tree
     * @return array
     */
    public function getTree(bool $onlyActive = true): array
    {
        $query = static::where(function ($q) {
            $q->where('id', $this->id)
              ->orWhere('path', 'like', $this->path . $this->id . '/%');
        })->orderBy('depth')->orderBy('order_index');

        if ($onlyActive) {
            $query->where('is_active', true);
        }

        $nodes = $query->with(['currentHead.user'])->get()->keyBy('id');

        if (! $nodes->has($this->id)) {
            return [];
        }

        // Build the root node, then recursively attach children below it
        $root             = $nodes->get($this->id);
        $item             = $root->toArray();
        $item['children'] = $this->buildNestedTree($nodes, $this->id);

        return [$item];
    }

    /**
     * Recursive helper: find direct children of $parentId and nest their subtrees.
     * Only matches nodes whose parent_id equals $parentId — no special-case needed.
     */
    private function buildNestedTree(Collection $nodes, int $parentId): array
    {
        $result = [];

        foreach ($nodes as $node) {
            if ((int) $node->parent_id === $parentId) {
                $item             = $node->toArray();
                $item['children'] = $this->buildNestedTree($nodes, $node->id);
                $result[]         = $item;
            }
        }

        return $result;
    }

    /**
     * Retrieve the full organizational tree from all root nodes.
     * Uses a single query for the entire dataset — no N+1 queries.
     *
     * @param  bool  $onlyActive
     * @return array
     */
    public static function getFullTree(bool $onlyActive = true): array
    {
        $query = static::orderBy('depth')->orderBy('order_index');

        if ($onlyActive) {
            $query->where('is_active', true);
        }

        $all = $query->with(['currentHead' => fn ($q) => $q->with('user:id,name')])->get();

        return static::buildFlatTree($all, null);
    }

    /**
     * Static helper to build a nested tree from a flat Collection.
     */
    private static function buildFlatTree(Collection $all, ?int $parentId): array
    {
        return $all
            ->filter(fn ($n) => $n->parent_id === $parentId)
            ->values()
            ->map(function ($node) use ($all) {
                $item             = $node->toArray();
                $item['children'] = static::buildFlatTree($all, $node->id);
                return $item;
            })
            ->all();
    }

    /**
     * Get the full hierarchy as a flat list with recursive CTE.
     * Useful for queries requiring server-side ordering/filtering.
     *
     * @param  int|null  $rootId  NULL = entire tree; int = subtree rooted at $rootId
     * @return \Illuminate\Support\Collection
     */
    public static function getHierarchyCTE(?int $rootId = null): Collection
    {
        $anchor = $rootId
            ? "SELECT id, parent_id, name, short_name, type, depth, path, order_index, is_active, 0 AS level
               FROM organizational_units
               WHERE id = {$rootId} AND deleted_at IS NULL"
            : "SELECT id, parent_id, name, short_name, type, depth, path, order_index, is_active, 0 AS level
               FROM organizational_units
               WHERE parent_id IS NULL AND deleted_at IS NULL";

        $rows = DB::select(<<<SQL
            WITH RECURSIVE org_tree AS (
                {$anchor}

                UNION ALL

                SELECT ou.id, ou.parent_id, ou.name, ou.short_name, ou.type,
                       ou.depth, ou.path, ou.order_index, ou.is_active,
                       ot.level + 1 AS level
                FROM organizational_units ou
                INNER JOIN org_tree ot ON ou.parent_id = ot.id
                WHERE ou.deleted_at IS NULL
            )
            SELECT * FROM org_tree
            ORDER BY level, order_index, name
        SQL);

        return collect($rows)->map(fn ($r) => (array) $r);
    }

    // ── Hierarchy Engine — Relationship Checks ─────────────────────────────────

    /** Returns true if this unit is a direct parent of $child. */
    public function isParentOf(self $child): bool
    {
        return $child->parent_id === $this->id;
    }

    /** Returns true if this unit is an ancestor of $descendant at any depth. */
    public function isAncestorOf(self $descendant): bool
    {
        return str_contains($descendant->path ?? '', '/' . $this->id . '/');
    }

    /** Returns true if this unit is a descendant of $ancestor at any depth. */
    public function isDescendantOf(self $ancestor): bool
    {
        return str_contains($this->path ?? '', '/' . $ancestor->id . '/');
    }

    // ── Convenience Accessors ──────────────────────────────────────────────────

    /** Formatted breadcrumb: Root › Division › Department */
    public function getBreadcrumbAttribute(): string
    {
        $ancestors = $this->getAncestors()->pluck('name');
        return $ancestors->push($this->name)->implode(' › ');
    }

    /** Count of currently active employees in this unit (not subtree). */
    public function getEmployeeCountAttribute(): int
    {
        return $this->activeAssignments()->count();
    }

    /** Count of all employees in this unit's subtree. */
    public function getSubtreeEmployeeCountAttribute(): int
    {
        $descendantIds = $this->getDescendants()->pluck('id')->push($this->id)->toArray();

        return EmployeeUnitAssignment::whereIn('organizational_unit_id', $descendantIds)
            ->whereNull('end_date')
            ->whereNull('deleted_at')
            ->count();
    }
}
