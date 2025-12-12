<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IPCR extends Model
{
    use HasFactory;

     protected $table = 'ipcrs';

    protected $fillable = 
    [
        'user_id',
        'work_distribution_plan_id',
        'target',
        'target_status',
        'accomplishment',
        'self_quality',
        'self_efficiency',
        'self_timeliness',
        'self_rating',
        'supervisor_quality',
        'supervisor_efficiency',
        'supervisor_timeliness',
        'supervisor_rating',
        'target_submitted_at',
        'target_reviewed_at',
        'accomplishment_submitted_at',
        'accomplishment_reviewed_at',
        'mov_link',
    ];


    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function workDistributionPlan()
    {
        return $this->belongsTo(WorkDistributionPlan::class);
    }
}
