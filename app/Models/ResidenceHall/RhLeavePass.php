<?php

namespace App\Models\ResidenceHall;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class RhLeavePass extends Model
{
    protected $table = 'rh_leave_passes';

    protected $fillable = [
        'rh_intern_id',
        'purpose',
        'destination',
        'with_companion',
        'companion_name',
        'companion_contact',
        'student_signature_at',
        'status',
        'approved_by',
        'approved_at',
        'remarks',
        'expected_return_at',
        'actual_departure_at',
        'guard_out_id',
        'actual_return_at',
        'guard_in_id',
    ];

    protected function casts(): array
    {
        return [
            'with_companion'       => 'boolean',
            'student_signature_at' => 'datetime',
            'approved_at'          => 'datetime',
            'expected_return_at'   => 'datetime',
            'actual_departure_at'  => 'datetime',
            'actual_return_at'     => 'datetime',
        ];
    }

    public function intern()
    {
        return $this->belongsTo(RhIntern::class, 'rh_intern_id');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function guardOut()
    {
        return $this->belongsTo(User::class, 'guard_out_id');
    }

    public function guardIn()
    {
        return $this->belongsTo(User::class, 'guard_in_id');
    }
}
