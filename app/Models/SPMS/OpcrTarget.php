<?php

namespace App\Models\SPMS;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OpcrTarget extends Model
{
    use HasFactory;

    protected $table = 'spms_opcr_targets';

    protected $fillable = [
        'opcr_id', 'spms_performance_indicator_id',
        'q1_actual', 'q2_actual', 'q3_actual', 'q4_actual',
        'rating_q', 'rating_e', 'rating_t', 'rating_avg', 'remarks',
    ];

    protected $casts = [
        'q1_actual' => 'decimal:2', 'q2_actual' => 'decimal:2',
        'q3_actual' => 'decimal:2', 'q4_actual' => 'decimal:2',
        'rating_q' => 'decimal:2', 'rating_e' => 'decimal:2',
        'rating_t' => 'decimal:2', 'rating_avg' => 'decimal:2',
    ];

    public function opcr(): BelongsTo
    {
        return $this->belongsTo(Opcr::class, 'opcr_id');
    }

    public function performanceIndicator(): BelongsTo
    {
        return $this->belongsTo(PerformanceIndicator::class, 'spms_performance_indicator_id');
    }
}
