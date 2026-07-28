<?php

namespace App\Models\HomeroomAttendance;

use App\Models\Student;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonthlyReportLine extends Model
{
    protected $table = 'homeroom_monthly_report_lines';

    protected $fillable = [
        'homeroom_monthly_report_id',
        'student_id',
        'days_present',
        'excused_absences',
        'unexcused_absences',
        'cutting_count',
        'tardy_count',
        'inc_uniform_count',
        'deduction_points',
        'remarks',
        'is_perfect_attendance',
    ];

    protected $casts = [
        'student_id'            => 'integer',
        'days_present'          => 'decimal:2',
        'excused_absences'      => 'integer',
        'unexcused_absences'    => 'integer',
        'cutting_count'         => 'integer',
        'tardy_count'           => 'integer',
        'inc_uniform_count'     => 'integer',
        'deduction_points'      => 'decimal:2',
        'is_perfect_attendance' => 'boolean',
    ];

    public function report(): BelongsTo
    {
        return $this->belongsTo(MonthlyReport::class, 'homeroom_monthly_report_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id');
    }
}
