<?php

namespace App\Models\FacultyLoading;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Maps to the existing `committees` table.
 *
 * The core table was created in migration 2026_03_06_000002 with
 * name, head_id, and description columns. Migration 000012 adds
 * Faculty Loading fields: code, committee_type, load unit rates,
 * and is_active.
 */
class Committee extends Model
{
    protected $table = 'committees';

    protected $fillable = [
        'name',
        'code',
        'committee_type',
        'description',
        'head_id',
        'max_members',
        'chairperson_title',
        'chairperson_load_units',
        'member_load_units',
        'is_active',
    ];

    protected $casts = [
        'max_members'            => 'integer',
        'chairperson_load_units' => 'decimal:2',
        'member_load_units'      => 'decimal:2',
        'is_active'              => 'boolean',
    ];

    // ── Relationships ──────────────────────────────────────────────────────────

    public function head(): BelongsTo
    {
        return $this->belongsTo(User::class, 'head_id');
    }

    /** Members via the existing committee_user pivot. */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'committee_user')
                    ->withPivot('task');
    }

    /** Faculty Loading committee assignments (per-term records). */
    public function facultyAssignments(): HasMany
    {
        return $this->hasMany(FacultyCommitteeAssignment::class, 'committee_id');
    }

    // ── Scopes ─────────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('committee_type', $type);
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    /** Load units for a given role in this committee. */
    public function loadUnitsFor(string $role): float
    {
        return in_array($role, ['chairperson', 'co_chair'])
            ? (float) $this->chairperson_load_units
            : (float) $this->member_load_units;
    }
}
