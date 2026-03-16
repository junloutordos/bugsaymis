<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PDSTraining extends Model
{
    protected $table = 'pds_training';

    protected $fillable = [
        'pds_id',
        'training_title',
        'date_from',
        'date_to',
        'hours',
        'training_type',
        'conducted_by',
    ];

    protected $casts = [
        'date_from' => 'date',
        'date_to' => 'date',
        'hours' => 'decimal:2',
    ];

    public function pds()
    {
        return $this->belongsTo(PDS::class);
    }
}
