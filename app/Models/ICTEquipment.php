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
        'location',
        'remarks',
        'qr_code_path',
    ];

    protected $casts = [
        'date_acquired' => 'date',
        'amount'        => 'decimal:2',
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


}
