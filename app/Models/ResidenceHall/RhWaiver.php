<?php

namespace App\Models\ResidenceHall;

use Illuminate\Database\Eloquent\Model;

class RhWaiver extends Model
{
    protected $table = 'rh_waivers';

    protected $fillable = [
        'rh_intern_id',
        'can_go_home_alone',
        'guardian_name',
        'guardian_contact',
        'signed_by_student_at',
        'signed_by_guardian_at',
    ];

    protected function casts(): array
    {
        return [
            'can_go_home_alone'      => 'boolean',
            'signed_by_student_at'   => 'datetime',
            'signed_by_guardian_at'  => 'datetime',
        ];
    }

    public function intern()
    {
        return $this->belongsTo(RhIntern::class, 'rh_intern_id');
    }
}
