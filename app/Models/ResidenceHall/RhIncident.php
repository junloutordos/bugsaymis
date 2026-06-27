<?php

namespace App\Models\ResidenceHall;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class RhIncident extends Model
{
    protected $table = 'rh_incidents';

    protected $fillable = [
        'rh_intern_id',
        'incident_type',
        'description',
        'initial_intervention',
        'referred_to',
        'follow_up_notes',
        'status',
        'logged_by',
    ];

    public function intern()
    {
        return $this->belongsTo(RhIntern::class, 'rh_intern_id');
    }

    public function loggedBy()
    {
        return $this->belongsTo(User::class, 'logged_by');
    }
}
