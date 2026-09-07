<?php

namespace App\Models\IPCRV2;

use App\Models\EmployeeFunction;
use Illuminate\Database\Eloquent\Model;

class IpcrV2SupportItem extends Model
{
    protected $table = 'ipcr_v2_support_items';

    protected $fillable = [
        'ipcr_v2_id', 'employee_function_id', 'label', 'success_indicator', 'target',
        'actual_accomplishment', 'mov_link',
        'quality_rating', 'efficiency_rating', 'timeliness_rating', 'row_average', 'remarks',
    ];

    protected $casts = [
        'row_average' => 'decimal:2',
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
