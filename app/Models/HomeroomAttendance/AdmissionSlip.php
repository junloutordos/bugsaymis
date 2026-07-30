<?php

namespace App\Models\HomeroomAttendance;

use App\Models\FacultyLoading\Section;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdmissionSlip extends Model
{
    protected $table = 'class_admission_slips';

    protected $fillable = [
        'student_id',
        'section_id',
        'infraction_date',
        'infraction_type',
        'excused_status',
        'reason',
        'supporting_document_path',
        'issued_by',
        'issued_at',
    ];

    protected $casts = [
        'student_id'      => 'integer',
        'section_id'      => 'integer',
        'infraction_date' => 'date:Y-m-d',
        'issued_at'       => 'datetime',
    ];

    protected $appends = ['infraction_type_label'];

    /**
     * Human-readable label per infraction_type — 'cutting' is the legacy
     * value (pre-dates the cut_class/cutting_suspected split, kept in the
     * enum only so historical rows still display correctly); 'cut_class' is
     * a direct teacher assertion (CIM 3.6/3.6.2), 'cutting_suspected' is the
     * system-detected mismatch safety net (subject Absent + homeroom
     * Present/Tardy).
     */
    public const INFRACTION_TYPE_LABELS = [
        'absence'           => 'Absence',
        'tardy'             => 'Tardy',
        'cutting'           => 'Cut Class',
        'cut_class'         => 'Cut Class',
        'cutting_suspected' => 'Cut Class (suspected)',
    ];

    public function getInfractionTypeLabelAttribute(): string
    {
        return self::INFRACTION_TYPE_LABELS[$this->infraction_type] ?? ucfirst($this->infraction_type);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class, 'section_id');
    }

    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class, 'admission_slip_id');
    }
}
