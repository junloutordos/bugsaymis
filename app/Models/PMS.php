<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PMS extends Model
{
    use HasFactory;

    protected $table = 'ict_pms';

    protected $fillable = [
        'title',
        'school_year',
        'office_area',
        'frequency',
        'status',
        'performed_by',
        'remarks',
    ];

    // Remove pms_date here because multiple dates will live in ict_pms_dates

    protected $casts = [
        // no need for pms_date cast anymore
    ];

    /**
     * Many-to-Many: PMS ↔ ICT Equipments
     */
    public function equipments()
    {
        return $this->belongsToMany(
            ICTEquipment::class,
            'ict_pms_equipment',
            'ict_pms_id',
            'equipment_id'
        )->withTimestamps();
    }

    /**
     * Performed by a user (technician, IT staff, etc.)
     */
    public function performedBy()
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    /**
     * One PMS has many schedule dates
     */
    public function dates()
    {
        return $this->hasMany(PMSDate::class, 'ict_pms_id');
    }
}
