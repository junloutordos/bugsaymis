<?php

namespace App\Models\ScienceLab;

use Illuminate\Database\Eloquent\Model;

class ScienceLabProfile extends Model
{
    protected $table = 'science_lab_profiles';

    protected $fillable = [
        'room_id', 'unit', 'auh_user_id', 'in_charge_user_id', 'safety_procedure_path', 'first_aid_checklist', 'status', 'remarks',
    ];

    protected $casts = [
        'first_aid_checklist' => 'array',
    ];

    public function room() { return $this->belongsTo(\App\Models\Room::class); }

    public function auh() { return $this->belongsTo(\App\Models\User::class, 'auh_user_id'); }

    public function inCharge() { return $this->belongsTo(\App\Models\User::class, 'in_charge_user_id'); }
}
