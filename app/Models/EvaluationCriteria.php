<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EvaluationCriteria extends Model
{
    use HasFactory;

    protected $table = 'evaluation_criteria';

    protected $fillable = [
        'recruitment_type_id',
        'name',
        'weight_percentage',
        'scoring_guide',
        'is_active',
    ];

    protected $casts = [
        'weight_percentage' => 'decimal:2',
        'is_active'         => 'boolean',
    ];

    // ── Relationships ──────────────────────────────────────────────────────────

    public function recruitmentType()
    {
        return $this->belongsTo(RecruitmentType::class);
    }

    public function evaluationScores()
    {
        return $this->hasMany(EvaluationScore::class, 'criteria_id');
    }
}
