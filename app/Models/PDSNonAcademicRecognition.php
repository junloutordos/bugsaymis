<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PDSNonAcademicRecognition extends Model
{
    protected $table = 'pds_nonacedemic_recognition';

    // include fields the front-end sends: title, awarding_body, date_received
    protected $fillable = [
        'pds_id',
        'recognition',
        'title',
        'awarding_body',
        'date_received',
    ];

    public function pds()
    {
        return $this->belongsTo(PDS::class);
    }
}
