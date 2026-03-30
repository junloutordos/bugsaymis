<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RecruitmentType extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'has_ranking',
        'has_exam',
        'has_interview',
        'requires_csc_eligibility',
        'requires_prc_license',
        'is_active',
    ];

    protected $casts = [
        'has_ranking'             => 'boolean',
        'has_exam'                => 'boolean',
        'has_interview'           => 'boolean',
        'requires_csc_eligibility'=> 'boolean',
        'requires_prc_license'    => 'boolean',
        'is_active'               => 'boolean',
    ];

    // ── Relationships ──────────────────────────────────────────────────────────

    public function jobItems()
    {
        return $this->hasMany(JobItem::class);
    }

    public function evaluationCriteria()
    {
        return $this->hasMany(EvaluationCriteria::class);
    }

    public function onboardingRequirements()
    {
        return $this->hasMany(OnboardingRequirement::class);
    }

    public function applications()
    {
        return $this->hasMany(Application::class);
    }
}
