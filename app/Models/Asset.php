<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Asset extends Model
{
    use HasFactory;

    protected $fillable = [
        'property_no',
        'asset_name',
        'description',
        'category',
        'serial_number',
        'purchase_date',
        'cost',
        'condition',
        'building_id',
        'room_id',
        'assigned_user_id',
        'warranty_until',
        'status',
        'remarks',
        'photo',
    ];

    public function building()
    {
        return $this->belongsTo(Building::class, 'building_id');
    }

    public function room()
    {
        return $this->belongsTo(Room::class, 'room_id');
    }

    public function assignedUser()
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }
}
