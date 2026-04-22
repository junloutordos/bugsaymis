<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GuidanceSessionReport extends Model
{
    use HasFactory;

    protected $table = 'guidance_session_reports';

    protected $fillable = [
        'consultation_id',
        'cs_no',
        'date_filed',
        'school_year',
        'client_name',
        'client_age',
        'client_sex',
        'grade_section',
        'batch',
        'referral_strategies',
        'concern',
        'difficulties_presented',
        'counselor_notes',
        'accomplished_by',
    ];

    protected $casts = [
        'date_filed'         => 'date',
        'referral_strategies' => 'array',
        'client_age'         => 'integer',
    ];

    public function accomplishedByUser()
    {
        return $this->belongsTo(User::class, 'accomplished_by');
    }
}
