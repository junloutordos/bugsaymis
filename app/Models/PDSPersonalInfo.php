<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PDSPersonalInfo extends Model
{
    protected $table = 'pds_personal_info';

    protected $fillable = [
        'pds_id',
        'surname',
        'first_name',
        'middle_name',
        'name_ext',
        'date_of_birth',
        'place_of_birth',
        'sex_at_birth',
        'civil_status',
        'height',
        'weight',
        'blood_type',
        'umid_id_no',
        'pagibig_id_no',
        'philhealth_no',
        'philsys_no',
        'tin_no',
        'agency_employee_no',
        'citizenship_filipino',
        'citizenship_dual',
        'citizenship_dual_type',
        'citizenship_dual_country',
        'residential_house',
        'residential_street',
        'residential_subdivision',
        'residential_barangay',
        'residential_city',
        'residential_province',
        'residential_zip_code',
        'permanent_house',
        'permanent_street',
        'permanent_subdivision',
        'permanent_barangay',
        'permanent_city',
        'permanent_province',
        'permanent_zip_code',
        'telephone_no',
        'mobile_no',
        'email_address',
    ];

    public function pds()
    {
        return $this->belongsTo(PDS::class);
    }
}
