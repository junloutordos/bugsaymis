<?php

namespace App\Models\PM2;

use App\Models\WorkDistributionPlan;
use Illuminate\Database\Eloquent\Model;

class EmployeeIpcrPlanV2 extends Model
{
    protected $table = 'employee_ipcrs_plan_v2';

    protected $fillable = [
        'ipcr_id', 'function_type', 'weight_percent', 'plan_id', 'opcr_template_item_id',
        'individual_target', 'accomplishment', 'mov_link',
        'self_quality', 'self_efficiency', 'self_timeliness', 'self_average',
        'sup_quality', 'sup_efficiency', 'sup_timeliness', 'sup_average', 'remarks',
    ];

    protected $casts = [
        'weight_percent' => 'decimal:2',
        'self_average'   => 'decimal:2',
        'sup_average'    => 'decimal:2',
    ];

    public function ipcr()
    {
        return $this->belongsTo(EmployeeIpcrV2::class, 'ipcr_id');
    }

    public function plan()
    {
        return $this->belongsTo(WorkDistributionPlan::class, 'plan_id');
    }

    public function templateItem()
    {
        return $this->belongsTo(OpcrTemplateItem::class, 'opcr_template_item_id');
    }
}
