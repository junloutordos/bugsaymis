<?php

namespace App\Models\ScienceLab;

use Illuminate\Database\Eloquent\Model;

class LabPmSchedule extends Model
{
    protected $table = 'lab_pm_schedules';

    protected $fillable = [
        'school_year_id', 'lab_equipment_id', 'frequency', 'prepared_by_id', 'noted_by_id', 'remarks',
    ];

    protected $casts = [
    ];

    public function equipment() { return $this->belongsTo(LabEquipment::class, 'lab_equipment_id'); }

    public function records() { return $this->hasMany(LabPmRecord::class); }

    public function preparedBy() { return $this->belongsTo(\App\Models\User::class, 'prepared_by_id'); }

    public function notedBy() { return $this->belongsTo(\App\Models\User::class, 'noted_by_id'); }

    public function schoolYear() { return $this->belongsTo(\App\Models\FacultyLoading\SchoolYear::class, 'school_year_id'); }
}
