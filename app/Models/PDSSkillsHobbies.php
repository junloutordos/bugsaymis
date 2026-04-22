<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PDSSkillsHobbies extends Model
{
    protected $table = 'pds_skills_hobbies';

    protected $fillable = [
        'pds_id',
        'skills_hobbies',
    ];

    public function pds()
    {
        return $this->belongsTo(PDS::class);
    }
}
