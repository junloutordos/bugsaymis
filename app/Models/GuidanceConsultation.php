<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GuidanceConsultation extends Model
{
    use HasFactory;

    protected $table = 'guidance_consultations';

    protected $fillable = [
        'requestor_id',
        'concern',
        'description',
        'intervention',
        'status',
        'date_time_preferred',
        'date_time_assigned',
        'teacher',
        'consultation_type',
        'referred_by',
        'referral_category',
        'behavior_spotted',
        'brief_description',
    ];

    protected $casts = [
        'date_time_preferred' => 'datetime',
        'date_time_assigned' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
