<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PDSFamilyBackground extends Model
{
    protected $table = 'pds_family_background';

    protected $fillable = [
        'pds_id',
        'spouse_surname',
        'spouse_first_name',
        'spouse_middle_name',
        'spouse_name_ext',
        'spouse_occupation',
        'spouse_employer',
        'spouse_business_address',
        'spouse_telephone_no',
        'father_surname',
        'father_first_name',
        'father_middle_name',
        'father_name_ext',
        'mother_maiden_surname',
        'mother_maiden_first_name',
        'mother_maiden_middle_name',
    ];

    public function pds()
    {
        return $this->belongsTo(PDS::class);
    }
}
