<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PDSOtherInfo extends Model
{
    protected $table = 'pds_other_info';

    protected $fillable = [
        'pds_id',
        'government_id',
        'id_no',
        'date_place_issuance',
        'path_passport_photo',
    ];

    public function pds()
    {
        return $this->belongsTo(PDS::class);
    }
}
