<?php

namespace App\Models\SALN;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalnRelative extends Model
{
    protected $table = 'saln_relatives';

    protected $fillable = [
        'saln_record_id',
        'name',
        'relationship',
        'position',
        'agency_office',
        'agency_address',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function salnRecord(): BelongsTo
    {
        return $this->belongsTo(SalnRecord::class, 'saln_record_id');
    }
}
