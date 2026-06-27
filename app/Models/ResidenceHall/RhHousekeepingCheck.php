<?php

namespace App\Models\ResidenceHall;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class RhHousekeepingCheck extends Model
{
    protected $table = 'rh_housekeeping_checks';

    protected $fillable = [
        'rh_room_id',
        'check_date',
        'inspected_by',
        'scores',
        'total_score',
        'average_score',
        'passed',
    ];

    protected function casts(): array
    {
        return [
            'check_date'    => 'date:Y-m-d',
            'scores'        => 'array',
            'total_score'   => 'decimal:2',
            'average_score' => 'decimal:2',
            'passed'        => 'boolean',
        ];
    }

    public function room()
    {
        return $this->belongsTo(RhRoom::class, 'rh_room_id');
    }

    public function inspector()
    {
        return $this->belongsTo(User::class, 'inspected_by');
    }

    public function conformes()
    {
        return $this->hasMany(RhHousekeepingConforme::class, 'rh_housekeeping_check_id');
    }
}
