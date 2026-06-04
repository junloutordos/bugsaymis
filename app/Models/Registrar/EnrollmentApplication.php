<?php

namespace App\Models\Registrar;

use App\Models\FacultyLoading\SchoolYear;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EnrollmentApplication extends Model
{
    protected $fillable = [
        'reference_no', 'school_year_id', 'grade_level',
        'lastname', 'firstname', 'middlename',
        'birthday', 'sex', 'birth_place', 'lrn',
        'address', 'contact_no', 'email',
        'father_name', 'father_occupation', 'father_contact', 'father_email',
        'mother_name', 'mother_occupation', 'mother_contact', 'mother_email',
        'guardian_name', 'guardian_relationship', 'guardian_contact', 'guardian_email',
        'previous_school', 'previous_school_address',
        'grade_level_completed', 'school_year_completed',
        'status', 'pisays_id_assigned', 'remarks',
        'reviewed_by', 'reviewed_at', 'student_id',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
        'grade_level' => 'integer',
    ];

    public function schoolYear(): BelongsTo
    {
        return $this->belongsTo(SchoolYear::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function getFullNameAttribute(): string
    {
        return trim("{$this->lastname}, {$this->firstname}" .
            ($this->middlename ? " {$this->middlename}" : ''));
    }

    public static function generateReferenceNo(): string
    {
        $year     = now()->year;
        $prefix   = "ENR-{$year}-";
        $lastNo   = static::where('reference_no', 'like', "{$prefix}%")
            ->orderByDesc('id')
            ->value('reference_no');

        $seq = $lastNo
            ? (int) substr($lastNo, strlen($prefix)) + 1
            : 1;

        return $prefix . str_pad($seq, 4, '0', STR_PAD_LEFT);
    }
}
