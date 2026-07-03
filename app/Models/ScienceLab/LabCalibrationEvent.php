<?php

namespace App\Models\ScienceLab;

use Illuminate\Database\Eloquent\Model;

class LabCalibrationEvent extends Model
{
    protected $table = 'lab_calibration_events';

    protected $fillable = [
        'lab_calibration_schedule_id', 'lab_equipment_id', 'month', 'target_date', 'actual_date', 'calibrated_by', 'is_external', 'certificate_path', 'sticker_attached', 'due_date', 'result', 'status', 'remarks',
    ];

    protected $casts = [
        'month' => 'integer',
        'target_date' => 'date:Y-m-d',
        'actual_date' => 'date:Y-m-d',
        'due_date' => 'date:Y-m-d',
        'is_external' => 'boolean',
        'sticker_attached' => 'boolean',
    ];

    public function schedule() { return $this->belongsTo(LabCalibrationSchedule::class, 'lab_calibration_schedule_id'); }

    public function equipment() { return $this->belongsTo(LabEquipment::class, 'lab_equipment_id'); }
}
