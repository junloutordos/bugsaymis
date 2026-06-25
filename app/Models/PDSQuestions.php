<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PDSQuestions extends Model
{
    protected $table = 'pds_questions';

    protected $fillable = [
        'pds_id',
        'q34a_third_degree',
        'q34a_details',
        'q34b_fourth_degree',
        'q34b_details',
        'q35a_admin_offense',
        'q35a_details',
        'q35b_criminal_charge',
        'q35b_date_filed',
        'q35b_status',
        'q35b_details',
        'q36_convicted',
        'q36_details',
        'q37_separated_service',
        'q37_details',
        'q38a_candidate',
        'q38a_details',
        'q38b_resigned_for_campaign',
        'q38b_details',
        'q39_immigrant',
        'q39_country',
        'q40a_indigenous',
        'q40a_group',
        'q40b_pwd',
        'q40b_pwd_id',
        'q40c_solo_parent',
        'q40c_solo_parent_id',
    ];

    public function pds()
    {
        return $this->belongsTo(PDS::class);
    }
}
