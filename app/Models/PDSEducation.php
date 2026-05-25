<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PDSEducation extends Model
{
    protected $table = 'pds_education';

    protected $fillable = [
        'pds_id',
        'level',
        'school_name',
        'degree',
        'from',
        'to',
        'highest_level',
        'year_graduated',
        'honors',
    ];

    protected $casts = [
        'year_graduated' => 'integer',
    ];

    public function pds()
    {
        return $this->belongsTo(PDS::class);
    }
}
