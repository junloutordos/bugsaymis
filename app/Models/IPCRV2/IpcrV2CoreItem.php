<?php

namespace App\Models\IPCRV2;

use App\Models\EmployeeFunction;
use Illuminate\Database\Eloquent\Model;

class IpcrV2CoreItem extends Model
{
    protected $table = 'ipcr_v2_core_items';

    protected $fillable = [
        'ipcr_v2_id', 'employee_function_id', 'label', 'output_outcome', 'weight_percent', 'success_indicator',
        'target', 'actual_accomplishment', 'mov_link',
        'student_feedback_rating', 'supervisor_feedback_rating',
        'im_development_rating', 'quality_rating', 'efficiency_rating',
        'timeliness_rating', 'row_average', 'remarks',
        'self_quality_rating', 'self_efficiency_rating', 'self_student_feedback_rating',
        'self_supervisor_feedback_rating', 'self_im_development_rating',
        'self_timeliness_rating', 'self_row_average',
    ];

    protected $casts = [
        'weight_percent' => 'decimal:2',
        'row_average' => 'decimal:2',
        'self_row_average' => 'decimal:2',
    ];

    public function ipcr()
    {
        return $this->belongsTo(IpcrV2Record::class, 'ipcr_v2_id');
    }

    public function employeeFunction()
    {
        return $this->belongsTo(EmployeeFunction::class);
    }
}
