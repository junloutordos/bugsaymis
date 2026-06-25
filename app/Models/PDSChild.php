<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PDSChild extends Model
{
    protected $table = 'pds_children';

    protected $fillable = [
        'pds_id',
        'child_name',
        'child_date_of_birth',
    ];

    public function pds()
    {
        return $this->belongsTo(PDS::class);
    }
}
