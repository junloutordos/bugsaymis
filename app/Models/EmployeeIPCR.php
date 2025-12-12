<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeIPCR extends Model
{
    use HasFactory;

    protected $table = 'employee_ipcrs';

    protected $fillable = [
        'user_id',
        'rating_period',
        'title',
        'status',
        'remarks',
        'submitted_for_review_at',
        'target_approved_at',
        'submitted_for_rating_at',
        'submitted_rating_at',
        'submitted_for_pmtreview_at',  
    ];

    // Each IPCR belongs to a user (employee)
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Pivot relationship
    public function plans()
    {
        return $this->belongsToMany(WorkDistributionPlan::class, 'employee_ipcrs_plan', 'ipcr_id', 'plan_id')
                    ->withPivot([
                        'accomplishment',
                        'mov_link',

                        'self_quality',
                        'self_efficiency',
                        'self_timeliness',
                        'self_average',

                        'sup_quality',
                        'sup_efficiency',
                        'sup_timeliness',
                        'sup_average',
                    ])
                    ->withTimestamps();
    }
}
