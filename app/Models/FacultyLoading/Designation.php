<?php

namespace App\Models\FacultyLoading;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Designation extends Model
{
    protected $table = 'designations';

    protected $fillable = [
        'designation_category_id',
        'section_id',
        'code',
        'name',
        'description',
        'load_units',
        'assignment_type',
        'requires_unit',
        'max_holders',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'section_id'      => 'integer',
        'load_units'      => 'decimal:2',
        'assignment_type' => 'string',
        'requires_unit'   => 'boolean',
        'max_holders'     => 'integer',
        'sort_order'      => 'integer',
        'is_active'       => 'boolean',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function category(): BelongsTo
    {
        return $this->belongsTo(DesignationCategory::class, 'designation_category_id');
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function loadAssignments(): HasMany
    {
        return $this->hasMany(LoadAssignment::class);
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeRequiresUnit($query)
    {
        return $query->where('requires_unit', true);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /** Check if the designation has reached its holder cap for a given term. */
    public function isAtCapacity(int $academicTermId, ?int $academicUnitId = null): bool
    {
        if (is_null($this->max_holders)) {
            return false;
        }

        $query = $this->loadAssignments()
            ->where('academic_term_id', $academicTermId);

        if ($this->requires_unit && $academicUnitId) {
            $query->whereHas('faculty', fn($q) => $q->where('academic_unit_id', $academicUnitId));
        }

        return $query->count() >= $this->max_holders;
    }
}
