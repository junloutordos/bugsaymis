<?php

namespace App\Models\HR;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeProfile extends Model
{
    protected $table = 'employee_profiles';

    protected $fillable = [
        'user_id',
        'employee_no',
        'salary_grade_id',
        'step',
        'employment_type',
        'appointment_status',
        'item_no',
        'division',
        'section',
        'immediate_supervisor_id',
        'division_chief_id',
        'date_original_appointment',
        'date_last_promotion',
        'date_of_birth',
        'civil_status',
        'tin',
        'sss_no',
        'gsis_no',
        'pagibig_no',
        'philhealth_no',
        'bank_name',
        'bank_account_no',
        'blood_type',
        'emergency_contact_name',
        'emergency_contact_phone',
        'is_active',
    ];

    protected $casts = [
        'date_original_appointment' => 'date',
        'date_last_promotion'       => 'date',
        'date_of_birth'             => 'date',
        'is_active'                 => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function salaryGrade(): BelongsTo
    {
        return $this->belongsTo(\App\Models\SalaryGrade::class);
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'immediate_supervisor_id');
    }

    public function divisionChief(): BelongsTo
    {
        return $this->belongsTo(User::class, 'division_chief_id');
    }
}
