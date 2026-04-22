<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeIPCRPlan extends Model
{
    protected $table = 'employee_ipcrs_plan';

    protected $fillable = [
        'ipcr_id', 'plan_id',
        'accomplishment', 'mov_link',
        'self_quality', 'self_efficiency', 'self_timeliness', 'self_average',
        'sup_quality', 'sup_efficiency', 'sup_timeliness', 'sup_average',
        'remarks',
    ];

    public function ipcr()
    {
        return $this->belongsTo(EmployeeIPCR::class, 'ipcr_id');
    }

    public function plan()
    {
        return $this->belongsTo(WorkDistributionPlan::class, 'plan_id');
    }

    public function accomplishments()
    {
        return $this->hasMany(Accomplishment::class, 'ipcr_plan_id');
    }
}
