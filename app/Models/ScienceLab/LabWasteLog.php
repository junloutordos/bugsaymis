<?php

namespace App\Models\ScienceLab;

use Illuminate\Database\Eloquent\Model;

class LabWasteLog extends Model
{
    protected $table = 'lab_waste_logs';

    protected $fillable = [
        'room_id', 'unit', 'waste_date', 'waste_type', 'description', 'quantity', 'disposal_method', 'disposed_by_id', 'remarks',
    ];

    protected $casts = [
        'waste_date' => 'date:Y-m-d',
    ];

    public function room() { return $this->belongsTo(\App\Models\Room::class); }

    public function disposedBy() { return $this->belongsTo(\App\Models\User::class, 'disposed_by_id'); }
}
