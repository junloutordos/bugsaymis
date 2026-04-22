<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class IctPmsHistory extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'ict_pms_history';

    protected $fillable = [
        'ict_pms_id',
        'equipment_id',
        'pms_date',
        'description',
        'type',
        'cost_of_repair',
        'remarks',
        'created_by',
    ];

    protected $casts = [
        'pms_date' => 'date',
        'cost_of_repair' => 'decimal:2',
    ];

    // Relationships (adjust names to your existing models)
    public function pms()
    {
        return $this->belongsTo(\App\Models\PMS::class, 'ict_pms_id');
    }

    public function equipment()
    {
        return $this->belongsTo(\App\Models\ICTEquipment::class, 'equipment_id');
    }

    public function creator()
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }
    
}
