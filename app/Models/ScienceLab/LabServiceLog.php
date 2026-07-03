<?php

namespace App\Models\ScienceLab;

use Illuminate\Database\Eloquent\Model;

class LabServiceLog extends Model
{
    protected $table = 'lab_service_logs';

    protected $fillable = [
        'lab_equipment_id', 'repair_ticket_id', 'log_date', 'particulars', 'type', 'remarks_reference', 'cost_of_materials', 'serviced_by', 'inputted_by_id',
    ];

    protected $casts = [
        'log_date' => 'date:Y-m-d',
        'cost_of_materials' => 'decimal:2',
    ];

    public function equipment() { return $this->belongsTo(LabEquipment::class, 'lab_equipment_id'); }

    public function ticket() { return $this->belongsTo(LabRepairTicket::class, 'repair_ticket_id'); }

    public function inputtedBy() { return $this->belongsTo(\App\Models\User::class, 'inputted_by_id'); }
}
