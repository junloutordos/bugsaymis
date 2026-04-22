<?php

namespace App\Models;

use App\Traits\HasApprovalSnapshots;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WorkRequest extends Model
{
    use HasFactory, HasApprovalSnapshots;

    protected $fillable = [
        'issue',
        'category',
        'description',
        'priority',
        'division_chief_id', // Ensure this field is included
        'decline_reason',
        'declined_at',
        'location_division_id',
        'location_office_id',
        'assigned_user_id',
        'acted_by_id',
        'expected_completion_date',
        'action_taken',
        'date_completed',
        'requester_id',
        'status',
    ];

    public function division()
    {
        return $this->belongsTo(\App\Models\Building::class, 'location_division_id');
    }

    public function divisionChief()
    {
        return $this->belongsTo(User::class, 'division_chief_id');
    }

    public function office()
    {
        return $this->belongsTo(\App\Models\Room::class, 'location_office_id');
    }

    public function assignedUser()
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function actedBy()
    {
        return $this->belongsTo(User::class, 'acted_by_id');
    }
}
