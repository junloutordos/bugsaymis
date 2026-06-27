<?php

namespace App\Models\ResidenceHall;

use Illuminate\Database\Eloquent\Model;

class RhRoom extends Model
{
    protected $table = 'rh_rooms';

    protected $fillable = [
        'residence_hall',
        'room_number',
        'capacity',
        'floor',
        'description',
        'status',
    ];

    public function interns()
    {
        return $this->hasMany(RhIntern::class, 'rh_room_id');
    }

    public function activeInterns()
    {
        return $this->hasMany(RhIntern::class, 'rh_room_id')->where('status', 'active');
    }

    public function housekeepingChecks()
    {
        return $this->hasMany(RhHousekeepingCheck::class, 'rh_room_id');
    }

    public function getOccupancyAttribute(): int
    {
        return $this->activeInterns()->count();
    }

    public function getIsFullAttribute(): bool
    {
        return $this->occupancy >= $this->capacity;
    }
}
