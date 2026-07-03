<?php

namespace App\Models\ScienceLab;

use Illuminate\Database\Eloquent\Model;
use \App\Traits\HasApprovalSnapshots;

class LabCalibrationSchedule extends Model
{
    use HasApprovalSnapshots;

    protected $table = 'lab_calibration_schedules';

    protected $fillable = [
        'school_year_id', 'lab_equipment_id', 'frequency', 'prepared_by_id', 'approved_by_id', 'approved_at', 'status', 'remarks',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
    ];

    public function equipment() { return $this->belongsTo(LabEquipment::class, 'lab_equipment_id'); }

    public function events() { return $this->hasMany(LabCalibrationEvent::class); }

    public function preparedBy() { return $this->belongsTo(\App\Models\User::class, 'prepared_by_id'); }

    public function approver() { return $this->belongsTo(\App\Models\User::class, 'approved_by_id'); }

    public function schoolYear() { return $this->belongsTo(\App\Models\FacultyLoading\SchoolYear::class, 'school_year_id'); }
}
