<?php

namespace App\Models;

use App\Traits\HasApprovalSnapshots;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VehicleRequest extends Model
{
    use HasFactory, HasApprovalSnapshots;

    protected $fillable = [
        'requestor_id',
        'purpose',
        'destination',
        'date_needed',
        'date_needed_multiple',
        'time_of_departure',
        'eta',
        'vehicle_type',
        'passengers',
        'division_chief_id',
        'status',
        'decline_reason',
        'declined_at',
        'driver_id',
    ];

    protected $casts = [
        'date_needed' => 'date',
        'date_needed_multiple' => 'array',
        'time_of_departure' => 'string',
        'eta' => 'string',
        'declined_at' => 'datetime',
        'passengers' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'requestor_id');
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requestor_id');
    }

    public function divisionChief()
    {
        return $this->belongsTo(User::class, 'division_chief_id');
    }

    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }
}
