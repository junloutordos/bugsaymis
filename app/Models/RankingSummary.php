<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RankingSummary extends Model
{
    use HasFactory;

    protected $table = 'ranking_summaries';

    protected $fillable = [
        'application_id',
        'total_score',
        'rank',
        'is_recommended',
        'deliberation_notes',
    ];

    protected $casts = [
        'total_score'     => 'decimal:4',
        'is_recommended'  => 'boolean',
    ];

    // ── Relationships ──────────────────────────────────────────────────────────

    public function application()
    {
        return $this->belongsTo(Application::class);
    }
}
