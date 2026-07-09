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
        'created_by',
        'approval_status',
        'approved_by',
        'approved_at',
        'declined_reason',
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
     * The user who originally created this schedule (Prepared By).
     */
    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * The OCD-role user who approved this schedule (Noted By).
     */
    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * One PMS has many schedule dates
     */
    public function dates()
    {
        return $this->hasMany(PMSDate::class, 'ict_pms_id');
    }
}
