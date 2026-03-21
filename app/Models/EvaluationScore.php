<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EvaluationScore extends Model
{
    use HasFactory;

    protected $fillable = [
        'application_id',
        'criteria_id',
        'score',
        'computed_score',
        'evaluator_id',
        'remarks',
    ];

    protected $casts = [
        'score'          => 'decimal:4',
        'computed_score' => 'decimal:4',
    ];

    // ── Relationships ──────────────────────────────────────────────────────────

    public function application()
    {
        return $this->belongsTo(Application::class);
    }

    public function criteria()
    {
        return $this->belongsTo(EvaluationCriteria::class, 'criteria_id');
    }

    public function evaluator()
    {
        return $this->belongsTo(User::class, 'evaluator_id');
    }
}
