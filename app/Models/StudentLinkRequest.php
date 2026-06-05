<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentLinkRequest extends Model
{
    protected $fillable = [
        'parent_contact_id', 'student_id', 'relationship',
        'token', 'status', 'expires_at', 'confirmed_at',
    ];

    protected $casts = [
        'expires_at'   => 'datetime',
        'confirmed_at' => 'datetime',
    ];

    public function parentContact(): BelongsTo
    {
        return $this->belongsTo(\App\Models\StudentAttendance\ParentContact::class, 'parent_contact_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /** Returns masked student ID, e.g. "24-****-*-****" */
    public function maskedStudentId(): string
    {
        $raw = $this->student?->pisaysystemID ?? '';
        $parts = explode('-', $raw);
        foreach ($parts as $i => $part) {
            if ($i > 0) $parts[$i] = str_repeat('*', strlen($part));
        }
        return implode('-', $parts) ?: '****';
    }
}
