<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WorkRequestPreRepairInspection extends Model
{
    use HasFactory;

    protected $fillable = [
        'work_request_id',
        'inspector_id',
        'noted_by_id',
        'status',
        'asset_type',
        'building_facility',
        'property_no',
        'location',
        'description',
        'serial_no',
        'date_of_purchase',
        'purchase_cost',
        'warranty_status',
        'location_of_defect',
        'nature_of_defect',
        'cause_of_defect',
        'recommendations',
        'material_items',
        'labor_items',
        'total_material_cost',
        'total_labor_cost',
        'indirect_cost',
        'total_estimated_repair_cost',
        'inspected_at',
        'noted_at',
        'return_reason',
    ];

    protected $casts = [
        'date_of_purchase' => 'date:Y-m-d',
        'material_items' => 'array',
        'labor_items' => 'array',
        'purchase_cost' => 'decimal:2',
        'total_material_cost' => 'decimal:2',
        'total_labor_cost' => 'decimal:2',
        'indirect_cost' => 'decimal:2',
        'total_estimated_repair_cost' => 'decimal:2',
        'inspected_at' => 'datetime',
        'noted_at' => 'datetime',
    ];

    public function workRequest()
    {
        return $this->belongsTo(WorkRequest::class);
    }

    public function inspector()
    {
        return $this->belongsTo(User::class, 'inspector_id');
    }

    public function notedBy()
    {
        return $this->belongsTo(User::class, 'noted_by_id');
    }
}
