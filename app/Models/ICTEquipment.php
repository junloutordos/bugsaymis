<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ICTEquipment extends Model
{
    use HasFactory;

    protected $table = 'ict_equipments';

    protected $fillable = [
        'category',
        'owner_id',
        'property_no',
        'serial_no',
        'description',
        'date_acquired',
        'amount',
        'status',
        'room_id',
        'remarks',
        'qr_code_path',
        'warranty_expires_at',
        'warranty_provider',
        'decommissioned_at',
    ];

    protected $casts = [
        'date_acquired'       => 'date',
        'amount'              => 'decimal:2',
        'warranty_expires_at' => 'date',
        'decommissioned_at'   => 'date',
    ];

    /**
     * Belongs to an owner (User)
     */
    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * Many-to-Many: Equipment ↔ PMS
     */
    public function pmsSchedules()
    {
        return $this->belongsToMany(
            PMS::class,
            'ict_pms_equipment', // pivot table
            'equipment_id',      // FK to Equipment
            'ict_pms_id'         // FK to PMS
        )->withTimestamps();
    }
    public function histories()
    {
        return $this->hasMany(\App\Models\ICTPMSHistory::class, 'equipment_id');
    }
    public function pmsHistory()
    {
        return $this->hasMany(ICTPMSHistory::class, 'equipment_id');
    }
   
    public function room()
    {
        return $this->belongsTo(Room::class,'room_id');
    }

    public function agentDevice()
    {
        // A reinstall/re-enroll creates a new device row rather than reusing
        // the old one, so always resolve to the most recently created one.
        return $this->hasOne(IctEquipmentDevice::class, 'equipment_id')->latestOfMany();
    }

    public function alerts()
    {
        return $this->hasMany(IctEquipmentAlert::class, 'equipment_id');
    }

    public function assignmentHistory()
    {
        return $this->hasMany(IctEquipmentAssignmentHistory::class, 'equipment_id');
    }

}
