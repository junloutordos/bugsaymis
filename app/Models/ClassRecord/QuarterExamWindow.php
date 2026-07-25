<?php

namespace App\Models\ClassRecord;

use App\Models\FacultyLoading\SchoolYear;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuarterExamWindow extends Model
{
    protected $table = 'quarter_exam_windows';

    protected $fillable = [
        'school_year_id',
        'quarter',
        'start_date',
        'end_date',
    ];

    protected $casts = [
        'quarter'    => 'integer',
        'start_date' => 'date:Y-m-d',
        'end_date'   => 'date:Y-m-d',
    ];

    public function schoolYear(): BelongsTo
    {
        return $this->belongsTo(SchoolYear::class);
    }
}
