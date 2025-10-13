<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WorkDistributionPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'performance_indicator_id',
        'success_indicator',
        'start_date',
        'end_date',
    ];

    // Each plan belongs to one performance indicator
    public function performanceIndicator()
    {
        return $this->belongsTo(PerformanceIndicator::class);
    }

    // Each plan has many assigned personnel (users)
    public function personnel()
    {
        return $this->belongsToMany(User::class, 'plan_user')
            ->withTimestamps();
    }
    // Each plan has many assigned personnel (users)
    public function users()
    {
        return $this->belongsToMany(User::class, 'plan_user') // 👈 make sure pivot matches migration
            ->withTimestamps();
    }

    // Each plan has many IPCR records
    public function ipcrs()
    {
        return $this->hasMany(IPCR::class, 'work_distribution_plan_id');
    }
}
