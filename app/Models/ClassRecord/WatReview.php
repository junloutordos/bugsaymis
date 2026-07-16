<?php

namespace App\Models\ClassRecord;

use App\Models\FacultyLoading\SchoolYear;
use App\Models\FacultyLoading\Section;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WatReview extends Model
{
    protected $table = 'wat_reviews';

    protected $fillable = [
        'section_id',
        'school_year_id',
        'week_start',
        'status',
        'remarks',
        'reviewed_by_id',
        'reviewed_at',
    ];

    protected $casts = [
        'section_id'  => 'integer',
        'week_start'  => 'date:Y-m-d',
        'reviewed_at' => 'datetime',
    ];

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class, 'section_id');
    }

    public function schoolYear(): BelongsTo
    {
        return $this->belongsTo(SchoolYear::class, 'school_year_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_id');
    }
}
