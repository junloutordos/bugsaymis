<?php

namespace App\Models\ResidenceHall;

use Illuminate\Database\Eloquent\Model;

class RhHousekeepingConforme extends Model
{
    protected $table = 'rh_housekeeping_conforme';

    protected $fillable = [
        'rh_housekeeping_check_id',
        'rh_intern_id',
        'conforme_at',
    ];

    protected function casts(): array
    {
        return [
            'conforme_at' => 'datetime',
        ];
    }

    public function check()
    {
        return $this->belongsTo(RhHousekeepingCheck::class, 'rh_housekeeping_check_id');
    }

    public function intern()
    {
        return $this->belongsTo(RhIntern::class, 'rh_intern_id');
    }
}
