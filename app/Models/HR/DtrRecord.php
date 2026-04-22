<?php

namespace App\Models\HR;

use App\Models\User;
use App\Models\WFHAttendance;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class DtrRecord extends Model
{
    use SoftDeletes;

    protected $table = 'dtr_records';

    protected $fillable = [
        'user_id',
        'schedule_id',
        'work_date',
        'time_in_am',
        'time_out_am',
        'time_in_pm',
        'time_out_pm',
        'hours_worked',
        'late_minutes',
        'undertime_minutes',
        'overtime_minutes',
        'day_type',
        'attendance_status',
        'leave_application_id',
        'is_posted',
        'is_locked',
        'processed_by',
        'processed_at',
        'remarks',
        'penned_time_in_am',
        'penned_time_out_am',
        'penned_time_in_pm',
        'penned_time_out_pm',
        'penned_remarks',
        'penned_by',
        'penned_at',
        'penned_submitted_at',
        'penned_submitted_by',
        'wfh_attendance_id',
    ];

    protected $casts = [
        'work_date'      => 'date:Y-m-d',
        'hours_worked'   => 'decimal:2',
        'late_minutes'   => 'decimal:2',
        'undertime_minutes' => 'decimal:2',
        'overtime_minutes'  => 'decimal:2',
        'is_posted'      => 'boolean',
        'is_locked'      => 'boolean',
        'processed_at'        => 'datetime',
        'penned_at'           => 'datetime',
        'penned_submitted_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(EmployeeSchedule::class);
    }

    public function leaveApplication(): BelongsTo
    {
        return $this->belongsTo(LeaveApplication::class);
    }

    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function wfhAttendance(): BelongsTo
    {
        return $this->belongsTo(WFHAttendance::class, 'wfh_attendance_id');
    }
}
