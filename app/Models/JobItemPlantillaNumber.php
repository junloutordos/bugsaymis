<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JobItemPlantillaNumber extends Model
{
    use HasFactory;

    protected $fillable = [
        'job_item_id',
        'plantilla_item_no',
        'status',
        'placement_id',
    ];

    // ── Relationships ──────────────────────────────────────────────────────────

    public function jobItem()
    {
        return $this->belongsTo(JobItem::class);
    }

    public function placement()
    {
        return $this->belongsTo(Placement::class);
    }

    // ── Scopes ─────────────────────────────────────────────────────────────────

    public function scopeVacant($query)
    {
        return $query->where('status', 'vacant');
    }
}
