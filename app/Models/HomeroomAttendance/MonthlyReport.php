<?php

namespace App\Models\HomeroomAttendance;

use App\Models\FacultyLoading\Section;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MonthlyReport extends Model
{
    protected $table = 'homeroom_monthly_reports';

    protected $fillable = [
        'section_id',
        'school_year_id',
        'month',
        'year',
        'status',
        'school_days_count',
        'submitted_by',
        'submitted_at',
        'approved_by',
        'approved_at',
        'coordinator_remarks',
    ];

    protected $casts = [
        'section_id'        => 'integer',
        'month'             => 'integer',
        'year'              => 'integer',
        'school_days_count' => 'integer',
        'submitted_at'      => 'datetime',
        'approved_at'       => 'datetime',
    ];

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class, 'section_id');
    }

    public function schoolYear(): BelongsTo
    {
        return $this->belongsTo(SchoolYear::class, 'school_year_id');
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(MonthlyReportLine::class, 'homeroom_monthly_report_id');
    }
}
