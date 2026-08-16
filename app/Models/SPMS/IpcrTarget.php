<?php

namespace App\Models\SPMS;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class IpcrTarget extends Model
{
    use HasFactory;

    protected $table = 'spms_ipcr_targets';

    protected $fillable = [
        'ipcr_id', 'function_type', 'source_type', 'source_id',
        'success_indicator', 'target', 'rubric_text', 'weight_pct',
        'actual_q', 'actual_e', 'actual_t',
        'rating_q', 'rating_e', 'rating_t', 'rating_avg', 'remarks',
    ];

    protected $casts = [
        'weight_pct' => 'decimal:2',
        'actual_q' => 'decimal:2', 'actual_e' => 'decimal:2', 'actual_t' => 'decimal:2',
        'rating_q' => 'decimal:2', 'rating_e' => 'decimal:2', 'rating_t' => 'decimal:2',
        'rating_avg' => 'decimal:2',
    ];

    public function ipcr(): BelongsTo
    {
        return $this->belongsTo(Ipcr::class, 'ipcr_id');
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function movChecklistItems(): HasMany
    {
        return $this->hasMany(MovChecklistItem::class, 'spms_ipcr_target_id');
    }
}
