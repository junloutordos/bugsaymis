<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PDSEligibility extends Model
{
    protected $table = 'pds_eligibility';

    protected $fillable = [
        'pds_id',
        'eligibility',
        'rating',
        'exam_date',
        'place_taken',
        'license_number',
        'license_validity',
    ];

    protected $casts = [
        'exam_date' => 'date',
        'license_validity' => 'date',
    ];

    public function pds()
    {
        return $this->belongsTo(PDS::class);
    }
}
