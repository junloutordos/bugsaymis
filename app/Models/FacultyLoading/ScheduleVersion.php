<?php

namespace App\Models\FacultyLoading;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduleVersion extends Model
{
    protected $table = 'schedule_versions';

    protected $fillable = [
        'academic_term_id',
        'label',
        'notes',
        'schedule_snapshot',
        'session_count',
        'created_by',
        'restored_at',
    ];

    protected $casts = [
        'schedule_snapshot' => 'array',
        'session_count'     => 'integer',
        'restored_at'       => 'datetime',
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
