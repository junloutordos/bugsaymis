<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PDSWorkExperience extends Model
{
    protected $table = 'pds_work_experience';

    protected $fillable = [
        'pds_id',
        'from_date',
        'to_date',
        'position',
        'agency',
        'appointment_status',
        'government_service',
    ];

    protected $casts = [
        'from_date' => 'date',
        'to_date' => 'date',
    ];

    public function pds()
    {
        return $this->belongsTo(PDS::class);
    }
}
