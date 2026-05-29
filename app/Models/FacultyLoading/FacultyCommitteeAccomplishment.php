<?php

namespace App\Models\FacultyLoading;

use App\Models\WorkDistributionPlan;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FacultyCommitteeAccomplishment extends Model
{
    protected $table = 'faculty_committee_accomplishments';

    protected $fillable = [
        'faculty_committee_assignment_id',
        'work_distribution_plan_id',
        'accomplishment',
        'mov_link',
        'sup_quality',
        'sup_efficiency',
        'sup_timeliness',
        'sup_average',
    ];

    protected $casts = [
        'sup_quality'    => 'decimal:2',
        'sup_efficiency' => 'decimal:2',
        'sup_timeliness' => 'decimal:2',
        'sup_average'    => 'decimal:2',
    ];

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(FacultyCommitteeAssignment::class, 'faculty_committee_assignment_id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(WorkDistributionPlan::class, 'work_distribution_plan_id');
    }
}
