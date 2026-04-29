<?php

namespace App\Models\FacultyLoading;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AcademicUnit extends Model
{
    protected $table = 'academic_units';

    protected $fillable = [
        'code',
        'name',
        'unit_type',
        'head_user_id',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active'  => 'boolean',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function head(): BelongsTo
    {
        return $this->belongsTo(User::class, 'head_user_id');
    }

    public function gradeLevels(): HasMany
    {
        return $this->hasMany(GradeLevel::class, 'academic_unit_id', 'id');
    }

    public function subjects(): HasMany
    {
        return $this->hasMany(Subject::class);
    }

    public function vacancies(): HasMany
    {
        return $this->hasMany(FacultyVacancy::class);
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('unit_type', $type);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function isJuniorHigh(): bool
    {
        return $this->unit_type === 'junior_high';
    }

    public function isSeniorHigh(): bool
    {
        return $this->unit_type === 'senior_high';
    }

    public function isDepartment(): bool
    {
        return $this->unit_type === 'department';
    }
}
