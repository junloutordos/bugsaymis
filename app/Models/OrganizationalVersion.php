<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

class OrganizationalVersion extends Model
{
    use SoftDeletes;

    protected $table = 'organizational_versions';

    protected $fillable = [
        'version_number',
        'name',
        'status',
        'effective_date',
        'end_date',
        'snapshot',
        'approved_by',
        'approved_at',
        'change_summary',
        'basis_document',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'snapshot'       => 'array',
        'effective_date' => 'date',
        'end_date'       => 'date',
        'approved_at'    => 'datetime',
    ];

    const STATUS_DRAFT    = 'draft';
    const STATUS_APPROVED = 'approved';
    const STATUS_ACTIVE   = 'active';
    const STATUS_ARCHIVED = 'archived';

    // ── Model Boot ─────────────────────────────────────────────────────────────

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $model) {
            $model->created_by ??= Auth::id();
            $model->updated_by   = Auth::id();
        });

        static::updating(function (self $model) {
            $model->updated_by = Auth::id();
        });
    }

    // ── Relationships ──────────────────────────────────────────────────────────

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ── Query Scopes ───────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeDraft($query)
    {
        return $query->where('status', self::STATUS_DRAFT);
    }

    // ── Business Logic ─────────────────────────────────────────────────────────

    /**
     * Capture a full snapshot of the current org tree and save it.
     * Called when activating a version so the structure is preserved historically.
     */
    public function captureSnapshot(): void
    {
        $this->snapshot = OrganizationalUnit::getFullTree(onlyActive: false);
        $this->saveQuietly();
    }

    /**
     * Activate this version: archive the currently active one, set this as active.
     */
    public function activate(): void
    {
        // Archive the current active version
        static::where('status', self::STATUS_ACTIVE)
            ->whereNull('deleted_at')
            ->update([
                'status'   => self::STATUS_ARCHIVED,
                'end_date' => now()->toDateString(),
            ]);

        // Capture current tree state as this version's snapshot
        $this->captureSnapshot();

        $this->status      = self::STATUS_ACTIVE;
        $this->approved_by ??= Auth::id();
        $this->approved_at ??= now();
        $this->save();
    }

    /**
     * Return the currently active organizational version, or null.
     */
    public static function current(): ?self
    {
        return static::where('status', self::STATUS_ACTIVE)->first();
    }
}
