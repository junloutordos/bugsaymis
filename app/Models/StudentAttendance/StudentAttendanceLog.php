<?php

namespace App\Models\StudentAttendance;

use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

class StudentAttendanceLog extends Model
{
    protected $table = 'student_attendance_logs';

    // Immutable — records are NEVER updated after creation
    const UPDATED_AT = null;
    public $timestamps = false;

    protected $fillable = [
        'student_id',
        'raw_barcode',
        'scan_time',
        'type',
        'source',
        'gate_location',
        'recorded_by',
        'kiosk_device_id',
        'scan_uuid',
        'device_scan_time',
        'capture_method',
    ];

    protected $casts = [
        'scan_time'  => 'datetime',
        'device_scan_time' => 'datetime',
        'created_at' => 'datetime',
    ];

    // Boot guard — prevent any modifications after creation
    protected static function boot(): void
    {
        parent::boot();

        static::updating(function () {
            throw new RuntimeException('StudentAttendanceLog records are immutable and cannot be updated.');
        });

        static::deleting(function () {
            throw new RuntimeException('StudentAttendanceLog records are immutable and cannot be deleted.');
        });
    }

    // ── Relations ──────────────────────────────────────────────────────────────

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function kioskDevice(): BelongsTo
    {
        return $this->belongsTo(StudentAttendanceDevice::class, 'kiosk_device_id');
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    public function isIn(): bool
    {
        return $this->type === 'in';
    }

    public function isOut(): bool
    {
        return $this->type === 'out';
    }

    public function getTypeLabelAttribute(): string
    {
        return $this->type === 'in' ? 'Time In' : 'Time Out';
    }
}
