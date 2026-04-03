<?php

namespace App\Models\HR;

use App\Models\OrganizationalUnit;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

class UnitHead extends Model
{
    use SoftDeletes;

    protected $table = 'unit_heads';

    protected $fillable = [
        'organizational_unit_id',
        'user_id',
        'designation_title',
        'is_acting',
        'is_current',
        'effective_date',
        'end_date',
        'designation_order',
        'designation_order_date',
        'remarks',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_acting'              => 'boolean',
        'is_current'             => 'boolean',
        'effective_date'         => 'date',
        'end_date'               => 'date',
        'designation_order_date' => 'date',
    ];

    // ── Model Boot ─────────────────────────────────────────────────────────────

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $model) {
            $model->created_by ??= Auth::id();
            $model->updated_by   = Auth::id();

            // When a new current head is designated, close out the previous one
            if ($model->is_current) {
                static::where('organizational_unit_id', $model->organizational_unit_id)
                    ->where('is_current', true)
                    ->whereNull('end_date')
                    ->whereNull('deleted_at')
                    ->update([
                        'is_current' => false,
                        'end_date'   => $model->effective_date ?? now()->toDateString(),
                    ]);
            }
        });

        static::updating(function (self $model) {
            $model->updated_by = Auth::id();
        });
    }

    // ── Relationships ──────────────────────────────────────────────────────────

    public function unit(): BelongsTo
    {
        return $this->belongsTo(OrganizationalUnit::class, 'organizational_unit_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ── Query Scopes ───────────────────────────────────────────────────────────

    /** Only currently active designations. */
    public function scopeCurrent($query)
    {
        return $query->where('is_current', true)->whereNull('end_date');
    }

    /** Acting / OIC designations. */
    public function scopeActing($query)
    {
        return $query->where('is_acting', true);
    }

    /** Designations effective on a given date. */
    public function scopeEffectiveOn($query, string $date)
    {
        return $query
            ->where('effective_date', '<=', $date)
            ->where(fn ($q) => $q->whereNull('end_date')->orWhere('end_date', '>=', $date));
    }
}
