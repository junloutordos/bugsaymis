<?php

namespace App\Models;

use App\Traits\HasApprovalSnapshots;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class FacilityRequest extends Model
{
    use HasFactory, HasApprovalSnapshots;

    protected $table = 'facility_requests';

    protected $fillable = [
        'requestor', 'requestor_id', 'unit', 'activity', 'purpose', 'nature',
        'date_start', 'date_end', 'time_start', 'time_end',
        
        'male', 'female', 'venue', 'chairs', 'tables',
        'equipment',
        'equipment_quantities',
        'mic', 'whiteboard', 'projector', 'elecfans', 'aircon', 'trashbins',
        'others', 'remarks',
        'status', 'email', 'date_filed', 'participants', 'reference_no',
        'decline_reason', 'declined_at',
    ];

    protected $casts = [
        'male' => 'integer',
        'female' => 'integer',
        'venue' => 'array',
        'equipment' => 'array',
        'equipment_quantities' => 'array',
        'requestor_id' => 'integer',
        
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

    /**
     * Relationship to the requesting user (linked by email).
     * FacilityRequest stores requester name and email rather than user_id,
     * so resolve the User by matching the `email` column.
     */
    public function requester()
    {
        return $this->belongsTo(User::class, 'requestor_id');
    }
}
