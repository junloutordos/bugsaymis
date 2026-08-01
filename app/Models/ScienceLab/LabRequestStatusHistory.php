<?php

namespace App\Models\ScienceLab;

use Illuminate\Database\Eloquent\Model;

class LabRequestStatusHistory extends Model
{
    protected $fillable = [
        'bundle_id', 'form_type', 'form_id', 'from_status', 'to_status',
        'actor_user_id', 'actor_student_id', 'remarks',
    ];

    public function bundle() { return $this->belongsTo(LabRequestBundle::class, 'bundle_id'); }
    public function actor() { return $this->belongsTo(\App\Models\User::class, 'actor_user_id'); }
}
