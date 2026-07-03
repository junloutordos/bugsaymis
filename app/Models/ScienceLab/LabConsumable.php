<?php

namespace App\Models\ScienceLab;

use Illuminate\Database\Eloquent\Model;

class LabConsumable extends Model
{
    protected $table = 'lab_consumables';

    protected $fillable = [
        'name', 'type', 'unit_of_measure', 'room_id', 'unit', 'is_chemical', 'sds_path', 'reorder_level', 'tracks_expiry', 'status', 'remarks', 'created_by',
    ];

    protected $casts = [
        'is_chemical' => 'boolean',
        'tracks_expiry' => 'boolean',
        'reorder_level' => 'decimal:3',
    ];

    public function room() { return $this->belongsTo(\App\Models\Room::class); }

    public function lots() { return $this->hasMany(LabConsumableLot::class); }

    public function movements() { return $this->hasMany(LabStockMovement::class); }

    public function stockCard() { return $this->hasOne(LabStockMovement::class)->latestOfMany(); }
}
