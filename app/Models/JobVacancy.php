<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class JobVacancy extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'job_item_id',
        'posting_date',
        'closing_date',
        'publication_type',
        'status',
    ];

    protected $casts = [
        'posting_date' => 'date:Y-m-d',
        'closing_date' => 'date:Y-m-d',
    ];

    // ── Relationships ──────────────────────────────────────────────────────────

    public function jobItem()
    {
        return $this->belongsTo(JobItem::class);
    }

    public function applications()
    {
        return $this->hasMany(Application::class);
    }

    // ── Scopes ─────────────────────────────────────────────────────────────────

    public function scopeOpen($query)
    {
        return $query->where('status', 'open')
                     ->where('closing_date', '>=', now()->toDateString());
    }
}
