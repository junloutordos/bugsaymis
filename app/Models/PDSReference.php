<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PDSReference extends Model
{
    protected $table = 'pds_references';

    protected $fillable = [
        'pds_id',
        'name',
        'office_address',
        'contact_no_email',
    ];

    public function pds()
    {
        return $this->belongsTo(PDS::class);
    }
}
