<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Application extends Model
{
    use HasFactory, SoftDeletes;

    // Workflow stages in order
    const STAGES = [
        'submitted',
        'screening',
        'exam',
        'interview',
        'ranking',
        'selection',
        'placement',
        'rejected',
        'withdrawn',
    ];

    protected $fillable = [
        'applicant_id',
        'job_vacancy_id',
        'recruitment_type_id',
        'application_date',
        'current_stage',
        'is_internal',
        'priority_score',
        'remarks',
    ];

    protected $casts = [
        'application_date' => 'date',
        'is_internal'      => 'boolean',
        'priority_score'   => 'decimal:4',
    ];

    // ── Relationships ──────────────────────────────────────────────────────────

    public function applicant()
    {
        return $this->belongsTo(Applicant::class);
    }

    public function jobVacancy()
    {
        return $this->belongsTo(JobVacancy::class);
    }

    public function recruitmentType()
    {
        return $this->belongsTo(RecruitmentType::class);
    }

    public function evaluationScores()
    {
        return $this->hasMany(EvaluationScore::class);
    }

    public function interviews()
    {
        return $this->hasMany(Interview::class);
    }

    public function rankingSummary()
    {
        return $this->hasOne(RankingSummary::class);
    }

    public function selection()
    {
        return $this->hasOne(Selection::class);
    }

    public function placement()
    {
        return $this->hasOne(Placement::class);
    }

    // ── Scopes ─────────────────────────────────────────────────────────────────

    public function scopeAtStage($query, string $stage)
    {
        return $query->where('current_stage', $stage);
    }

    public function scopeActive($query)
    {
        return $query->whereNotIn('current_stage', ['rejected', 'withdrawn']);
    }
}
