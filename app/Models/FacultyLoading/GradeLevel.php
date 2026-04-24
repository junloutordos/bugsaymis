<?php

namespace App\Models\FacultyLoading;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GradeLevel extends Model
{
    protected $table      = 'grade_levels';
    protected $primaryKey = 'grade';
    protected $keyType    = 'int';
    public    $incrementing = false;

    protected $fillable = [
        'grade',
        'label',
        'academic_unit_id',
        'sort_order',
    ];

    protected $casts = [
        'grade'      => 'integer',
        'sort_order' => 'integer',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function academicUnit(): BelongsTo
    {
        return $this->belongsTo(AcademicUnit::class);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function isJuniorHigh(): bool
    {
        return $this->grade >= 7 && $this->grade <= 10;
    }

    public function isSeniorHigh(): bool
    {
        return $this->grade >= 11 && $this->grade <= 12;
    }
}
