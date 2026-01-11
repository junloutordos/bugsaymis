<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FacilityRequest extends Model
{
    use HasFactory;

    protected $table = 'facility_requests';

    protected $fillable = [
        'requestor', 'unit', 'activity', 'purpose', 'nature',
        'date_start', 'date_end', 'time_start', 'time_end',
        'division_chief_id',
        'male', 'female', 'venue', 'chairs', 'tables',
        'equipment',
        'equipment_quantities',
        'mic', 'whiteboard', 'projector', 'elecfans', 'aircon', 'trashbins',
        'others', 'remarks', 'unitheadapproval', 'fadchiefapproval',
        'status', 'email', 'date_filed', 'participants', 'reference_no',
        'decline_reason', 'declined_at',
    ];

    protected $casts = [
        'male' => 'integer',
        'female' => 'integer',
        'venue' => 'array',
        'equipment' => 'array',
        'equipment_quantities' => 'array',
        'division_chief_id' => 'integer',
        'declined_at' => 'datetime',
        'chairs' => 'integer',
        'tables' => 'integer',
        'mic' => 'integer',
        'whiteboard' => 'integer',
        'projector' => 'integer',
        'elecfans' => 'integer',
        'aircon' => 'integer',
        'trashbins' => 'integer',
    ];
}
