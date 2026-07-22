<?php

namespace App\Models\FacultyLoading;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClassScheduleScopeLock extends Model
{
    protected $fillable = [
        'academic_term_id',
        'scope_type',
        'scope_key',
        'grade_level',
        'section_id',
        'locked_by',
        'locked_at',
    ];

    protected $casts = [
        'academic_term_id' => 'integer',
        'grade_level' => 'integer',
        'section_id' => 'integer',
        'locked_by' => 'integer',
        'locked_at' => 'datetime',
    ];

    public function academicTerm(): BelongsTo
    {
        return $this->belongsTo(AcademicTerm::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function lockedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'locked_by');
    }
}
