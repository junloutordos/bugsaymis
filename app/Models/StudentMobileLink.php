<?php

namespace App\Models;

use App\Models\FacultyLoading\SchoolYear;
use App\Models\Registrar\StudentEnrollment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentMobileLink extends Model
{
    protected $table = 'student_mobile_links';

    protected $fillable = ['student_id', 'user_id'];

    protected $casts = [
        'student_id' => 'integer',
        'user_id'    => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function student(): BelongsTo
    {
        // No FK constraint (MyISAM), resolved by app
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function currentEnrollment(): ?StudentEnrollment
    {
        $schoolYear = SchoolYear::where('is_current', true)->first();
        if (! $schoolYear) return null;

        return StudentEnrollment::where('student_id', $this->student_id)
            ->where('school_year_id', $schoolYear->id)
            ->where('status', 'enrolled')
            ->first();
    }
}
