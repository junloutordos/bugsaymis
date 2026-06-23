<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IctEquipmentAssignmentHistory extends Model
{
    protected $table = 'ict_equipment_assignment_history';

    protected $fillable = [
        'equipment_id',
        'user_id',
        'assigned_at',
        'unassigned_at',
        'reason',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
        'unassigned_at' => 'datetime',
    ];

    public function equipment()
    {
        return $this->belongsTo(ICTEquipment::class, 'equipment_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
