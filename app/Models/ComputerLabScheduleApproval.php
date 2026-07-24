<?php

namespace App\Models;

use App\Models\FacultyLoading\AcademicTerm;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class ComputerLabScheduleApproval extends Model
{
    protected $fillable = [
        'academic_term_id', 'status', 'schedule_snapshot', 'schedule_hash',
        'submitted_by', 'submitted_at', 'approved_by', 'approved_at',
        'returned_by', 'returned_at', 'return_remarks',
    ];

    protected $casts = [
        'schedule_snapshot' => 'array',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'returned_at' => 'datetime',
    ];

    public function academicTerm()
    {
        return $this->belongsTo(AcademicTerm::class);
    }

    public function submitter()
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function returner()
    {
        return $this->belongsTo(User::class, 'returned_by');
    }

    public function signatures(): MorphMany
    {
        return $this->morphMany(DigitalSignature::class, 'signable');
    }
}
