<?php

namespace App\Models\ScienceLab;

use Illuminate\Database\Eloquent\Model;

class LabConsumable extends Model
{
    protected $table = 'lab_consumables';

    protected $fillable = [
        'name', 'description', 'type', 'unit_of_measure', 'room_id', 'storage', 'end_user_id', 'unit', 'cabinet', 'storage_code', 'code_description', 'brand', 'cas_number', 'container_size', 'hazards', 'is_chemical', 'sds_path', 'reorder_level', 'tracks_expiry', 'status', 'remarks', 'created_by',
    ];

    protected $casts = [
        'is_chemical' => 'boolean',
        'tracks_expiry' => 'boolean',
        'reorder_level' => 'decimal:3',
    ];

    public function room() { return $this->belongsTo(\App\Models\Room::class); }

    public function endUser() { return $this->belongsTo(\App\Models\User::class, 'end_user_id'); }

    public function lots() { return $this->hasMany(LabConsumableLot::class); }

    public function movements() { return $this->hasMany(LabStockMovement::class); }

    public function stockCard() { return $this->hasOne(LabStockMovement::class)->latestOfMany(); }
}
