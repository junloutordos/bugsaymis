<?php

namespace App\Models\ClassRecord;

use App\Models\User;
use App\Models\Subject;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClassRecord extends Model
{
    protected $table = 'class_records';

    protected $fillable = [
        'subject_id',
        'section_id',
        'teacher_id',
        'grading_option_id',
        'school_year',
        'subject_name',
        'year_level_section',
        'status',
        'submitted_at',
        'checked_at',
        'checked_by_id',
    ];

    protected $casts = [
        'subject_id'   => 'integer',
        'section_id'   => 'integer',
        'submitted_at' => 'datetime',
        'checked_at'   => 'datetime',
    ];

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function gradingOption(): BelongsTo
    {
        return $this->belongsTo(GradingOption::class);
    }

    public function checkedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_by_id');
    }

    public function quarters(): HasMany
    {
        return $this->hasMany(ClassRecordQuarter::class)->orderBy('quarter');
    }
}
