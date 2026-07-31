<?php

namespace App\Models\ALP;

use App\Models\FacultyLoading\SchoolYear;
use App\Models\Registrar\StudentEnrollment;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class AlpMembership extends Model
{
    protected $table = 'alp_memberships';

    protected $fillable = ['alp_program_cycle_id', 'school_year_id', 'student_id', 'student_enrollment_id', 'status', 'consent_status', 'consent_file_id', 'completion_certificate_file_id', 'completed_at', 'joined_at', 'accountability', 'remarks', 'created_by'];

    protected $casts = ['joined_at' => 'date:Y-m-d', 'completed_at' => 'datetime'];

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(AlpProgramCycle::class, 'alp_program_cycle_id');
    }

    public function schoolYear(): BelongsTo
    {
        return $this->belongsTo(SchoolYear::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(StudentEnrollment::class, 'student_enrollment_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function officer(): HasOne
    {
        return $this->hasOne(AlpOfficer::class);
    }

    public function attendance(): HasMany
    {
        return $this->hasMany(AlpAttendance::class);
    }
}
