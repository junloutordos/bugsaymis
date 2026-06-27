<?php

namespace App\Models\ResidenceHall;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class RhApplication extends Model
{
    protected $table = 'rh_applications';

    protected $fillable = [
        'student_id',
        'school_year_id',
        'grade_level',
        'scholarship_category',
        'home_province',
        'estimated_distance_km',
        'preferred_hall',
        'foster_parent_name',
        'foster_parent_contact',
        'foster_parent_address',
        'status',
        'evaluation_notes',
        'evaluated_by',
        'evaluated_at',
        'approved_by',
        'approved_at',
        'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'evaluated_at' => 'datetime',
            'approved_at'  => 'datetime',
        ];
    }

    public function evaluatedBy()
    {
        return $this->belongsTo(User::class, 'evaluated_by');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
