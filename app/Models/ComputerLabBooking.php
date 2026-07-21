<?php

namespace App\Models;

use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\ClassSchedule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComputerLabBooking extends Model
{
    protected $fillable = [
        'room_id',
        'class_schedule_id',
        'academic_term_id',
        'booking_date',
        'day_of_week',
        'start_time',
        'end_time',
        'booking_type',
        'title',
        'purpose',
        'requested_by',
        'status',
        'decision_by',
        'decided_at',
        'conflict_note',
    ];

    protected $casts = [
        'booking_date' => 'date:Y-m-d',
        'decided_at' => 'datetime',
    ];

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function classSchedule(): BelongsTo
    {
        return $this->belongsTo(ClassSchedule::class);
    }

    public function academicTerm(): BelongsTo
    {
        return $this->belongsTo(AcademicTerm::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function decisionBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decision_by');
    }

    public function scopeOccupying($query)
    {
        return $query->whereIn('status', ['confirmed', 'approved']);
    }

    public function isPriorityClass(): bool
    {
        return $this->booking_type === 'priority_class';
    }
}
