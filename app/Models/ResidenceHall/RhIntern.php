<?php

namespace App\Models\ResidenceHall;

use Illuminate\Database\Eloquent\Model;

class RhIntern extends Model
{
    protected $table = 'rh_interns';

    protected $fillable = [
        'student_id',
        'school_year_id',
        'rh_room_id',
        'bed_number',
        'check_in_date',
        'check_out_date',
        'lodging_fee_monthly',
        'contract_start',
        'contract_end',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'check_in_date'     => 'date:Y-m-d',
            'check_out_date'    => 'date:Y-m-d',
            'contract_start'    => 'date:Y-m-d',
            'contract_end'      => 'date:Y-m-d',
            'lodging_fee_monthly' => 'decimal:2',
        ];
    }

    public function room()
    {
        return $this->belongsTo(RhRoom::class, 'rh_room_id');
    }

    public function waiver()
    {
        return $this->hasOne(RhWaiver::class, 'rh_intern_id');
    }

    public function appliances()
    {
        return $this->hasMany(RhAppliance::class, 'rh_intern_id');
    }

    public function leavePasses()
    {
        return $this->hasMany(RhLeavePass::class, 'rh_intern_id');
    }

    public function incidents()
    {
        return $this->hasMany(RhIncident::class, 'rh_intern_id');
    }

    public function feeLedger()
    {
        return $this->hasMany(RhFeeLedger::class, 'rh_intern_id');
    }

    public function getResidenceHallAttribute(): ?string
    {
        return $this->room?->residence_hall;
    }
}
