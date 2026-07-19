<?php

namespace App\Models\FacultyLoading;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoadAssignmentVersion extends Model
{
    protected $table = 'load_assignment_versions';

    protected $fillable = [
        'academic_term_id',
        'label',
        'notes',
        'assignment_snapshot',
        'assignment_count',
        'created_by',
        'restored_at',
    ];

    protected $casts = [
        'assignment_snapshot' => 'array',
        'assignment_count'    => 'integer',
        'restored_at'         => 'datetime',
    ];

    // ── Relationships ────────────────────────────────────────────────────────

    public function academicTerm(): BelongsTo
    {
        return $this->belongsTo(AcademicTerm::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ── Scopes ───────────────────────────────────────────────────────────────

    public function scopeForTerm($query, int $termId)
    {
        return $query->where('academic_term_id', $termId);
    }
}
