<?php

namespace App\Models\ScienceLab;

use Illuminate\Database\Eloquent\Model;
use \App\Traits\HasApprovalSnapshots;

class LabRepairTicket extends Model
{
    use HasApprovalSnapshots;

    protected $table = 'lab_repair_tickets';

    protected $fillable = [
        'control_no', 'lab_equipment_id', 'reported_by_id', 'problem', 'priority', 'immediate_action', 'external_provider', 'service_report_path', 'signage_placed', 'assigned_to_id', 'reviewed_by_id', 'date_reported', 'date_completed', 'status', 'remarks',
    ];

    protected $casts = [
        'signage_placed' => 'boolean',
        'date_reported' => 'date:Y-m-d',
        'date_completed' => 'date:Y-m-d',
    ];

    public function equipment() { return $this->belongsTo(LabEquipment::class, 'lab_equipment_id'); }

    public function reporter() { return $this->belongsTo(\App\Models\User::class, 'reported_by_id'); }

    public function assignee() { return $this->belongsTo(\App\Models\User::class, 'assigned_to_id'); }

    public function serviceLogs() { return $this->hasMany(LabServiceLog::class, 'repair_ticket_id'); }
}
