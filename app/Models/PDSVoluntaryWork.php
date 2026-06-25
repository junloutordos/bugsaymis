<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PDSVoluntaryWork extends Model
{
    protected $table = 'pds_voluntary_work';

    protected $fillable = [
        'pds_id',
        'organization',
        'from_date',
        'to_date',
        'hours',
        'nature_of_work',
    ];

    protected $casts = [
        'hours' => 'integer',
    ];

    public function pds()
    {
        return $this->belongsTo(PDS::class);
    }
}
