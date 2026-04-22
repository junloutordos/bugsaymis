<?php

namespace App\Models\HR;

use App\Models\OrganizationalUnit;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

class EmployeeUnitAssignment extends Model
{
    use SoftDeletes;

    protected $table = 'employee_unit_assignments';

    protected $fillable = [
        'user_id',
        'organizational_unit_id',
        'is_primary',
        'assignment_type',
        'appointment_type',
        'position_title',
        'item_number',
        'effective_date',
        'end_date',
        'order_number',
        'remarks',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_primary'     => 'boolean',
        'effective_date' => 'date',
        'end_date'       => 'date',
    ];

    // ── Assignment Type Constants ──────────────────────────────────────────────

    const ASSIGNMENT_ORGANIC    = 'organic';
    const ASSIGNMENT_SECONDED   = 'seconded';
    const ASSIGNMENT_DESIGNATED = 'designated';
    const ASSIGNMENT_CONCURRENT = 'concurrent';
    const ASSIGNMENT_DETAILED   = 'detailed';

    const APPOINTMENT_PERMANENT  = 'permanent';
    const APPOINTMENT_TEMPORARY  = 'temporary';
    const APPOINTMENT_CASUAL     = 'casual';
    const APPOINTMENT_COS        = 'cos';
    const APPOINTMENT_JOB_ORDER  = 'job_order';
    const APPOINTMENT_SECONDMENT = 'secondment';

    // ── Model Boot ─────────────────────────────────────────────────────────────

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $model) {
            $model->created_by ??= Auth::id();
            $model->updated_by   = Auth::id();

            // Enforce a single primary assignment per employee
            if ($model->is_primary) {
                static::where('user_id', $model->user_id)
                    ->where('is_primary', true)
                    ->whereNull('end_date')
                    ->whereNull('deleted_at')
                    ->update(['is_primary' => false]);
            }
        });

        static::updating(function (self $model) {
            $model->updated_by = Auth::id();
        });
    }

    // ── Relationships ──────────────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(OrganizationalUnit::class, 'organizational_unit_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ── Query Scopes ───────────────────────────────────────────────────────────

    /** Currently active assignments (no end date). */
    public function scopeActive($query)
    {
        return $query->whereNull('end_date');
    }

    /** Only primary assignments. */
    public function scopePrimary($query)
    {
        return $query->where('is_primary', true);
    }

    /** Assignments effective on a given date. */
    public function scopeEffectiveOn($query, string $date)
    {
        return $query
            ->where('effective_date', '<=', $date)
            ->where(fn ($q) => $q->whereNull('end_date')->orWhere('end_date', '>=', $date));
    }

    /** Assignments by type. */
    public function scopeOfAssignmentType($query, string $type)
    {
        return $query->where('assignment_type', $type);
    }

    // ── Accessors ──────────────────────────────────────────────────────────────

    public function getIsActiveAttribute(): bool
    {
        return $this->end_date === null;
    }
}
