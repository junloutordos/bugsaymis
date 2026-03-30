<?php

namespace App\Models\FacultyLoading;

use App\Models\Committee;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FacultyCommitteeAssignment extends Model
{
    protected $table = 'faculty_committee_assignments';

    protected $fillable = [
        'user_id',
        'school_year_id',
        'academic_term_id',
        'committee_id',
        'committee_name',
        'role',
        'load_units',
        'status',
        'remarks',
        'load_assignment_id',
    ];

    protected $casts = [
        'load_units' => 'decimal:2',
    ];

    public function faculty(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function schoolYear(): BelongsTo
    {
        return $this->belongsTo(SchoolYear::class);
    }

    public function academicTerm(): BelongsTo
    {
        return $this->belongsTo(AcademicTerm::class);
    }

    public function committee(): BelongsTo
    {
        return $this->belongsTo(Committee::class);
    }

    public function loadAssignment(): BelongsTo
    {
        return $this->belongsTo(LoadAssignment::class);
    }

    public function isChairperson(): bool
    {
        return in_array($this->role, ['chairperson', 'co_chair']);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeForFaculty($query, int $userId, int $termId)
    {
        return $query->where('user_id', $userId)
                     ->where('academic_term_id', $termId)
                     ->where('status', 'active');
    }
}
