<?php

namespace App\Models\Administration;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CoaVisit extends Model
{
    protected $table = 'coa_visits';

    protected $fillable = [
        'purpose',
        'office_visited',
        'date_from',
        'date_to',
        'remarks',
        'status',
        'issued_at',
        'created_by',
    ];

    protected $casts = [
        'date_from' => 'date:Y-m-d',
        'date_to'   => 'date:Y-m-d',
        'issued_at' => 'datetime',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function certificates(): HasMany
    {
        return $this->hasMany(CoaCertificate::class, 'coa_visit_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function isEditable(): bool
    {
        return $this->status === 'draft';
    }
}
