<?php

namespace App\Models\ScienceLab;

use Illuminate\Database\Eloquent\Model;

class LabEquipment extends Model
{
    protected $table = 'lab_equipment';

    protected $fillable = [
        'property_no', 'description', 'specifications', 'model', 'serial_number', 'room_id', 'end_user_id', 'unit', 'category', 'date_acquired', 'unit_cost', 'status', 'calibration_required', 'calibration_frequency', 'pm_required', 'pm_frequency', 'remarks', 'created_by',
    ];

    protected $casts = [
        'date_acquired' => 'date:Y-m-d',
        'unit_cost' => 'decimal:2',
        'calibration_required' => 'boolean',
        'pm_required' => 'boolean',
    ];

    public function room() { return $this->belongsTo(\App\Models\Room::class); }

    public function endUser() { return $this->belongsTo(\App\Models\User::class, 'end_user_id'); }

    public function creator() { return $this->belongsTo(\App\Models\User::class, 'created_by'); }

    public function serviceLogs() { return $this->hasMany(LabServiceLog::class); }

    public function repairTickets() { return $this->hasMany(LabRepairTicket::class); }

    public function calibrationEvents() { return $this->hasMany(LabCalibrationEvent::class); }
}
