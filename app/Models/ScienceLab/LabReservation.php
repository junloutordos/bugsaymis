<?php

namespace App\Models\ScienceLab;

use Illuminate\Database\Eloquent\Model;
use \App\Traits\HasApprovalSnapshots;

class LabReservation extends Model
{
    use HasApprovalSnapshots;

    protected $table = 'lab_reservations';

    protected $fillable = [
        'control_no', 'school_year_id', 'grade_level_section', 'number_of_students', 'subject', 'teacher_in_charge', 'date_start', 'date_end', 'time_start', 'time_end', 'room_id', 'requested_by_id', 'requester_name', 'requester_type', 'student_group', 'endorsed_by_id', 'endorsed_at', 'approved_by_id', 'approved_at', 'status', 'decline_reason', 'remarks',
    ];

    protected $casts = [
        'date_start' => 'date:Y-m-d',
        'date_end' => 'date:Y-m-d',
        'student_group' => 'array',
        'endorsed_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    public function room() { return $this->belongsTo(\App\Models\Room::class); }

    public function requester() { return $this->belongsTo(\App\Models\User::class, 'requested_by_id'); }

    public function endorser() { return $this->belongsTo(\App\Models\User::class, 'endorsed_by_id'); }

    public function approver() { return $this->belongsTo(\App\Models\User::class, 'approved_by_id'); }

    public function schoolYear() { return $this->belongsTo(\App\Models\FacultyLoading\SchoolYear::class, 'school_year_id'); }
}
