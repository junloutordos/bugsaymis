<?php

namespace App\Models;

use App\Models\FacultyLoading\SchoolYear;
use App\Models\FacultyLoading\Section;
use App\Models\FacultyLoading\Subject;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CidSchedule extends Model
{
    protected $fillable = [
        'school_year_id',
        'section_id',
        'subject_id',
        'title',
        'type',
        'scheduled_date',
        'start_time',
        'end_time',
        'description',
        'created_by',
    ];

    protected $casts = [
        'scheduled_date' => 'date',
        'school_year_id' => 'integer',
        'section_id'     => 'integer',
        'subject_id'     => 'integer',
    ];

    public function schoolYear(): BelongsTo
    {
        return $this->belongsTo(SchoolYear::class, 'school_year_id');
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class, 'section_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'subject_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
