<?php

namespace App\Models\SALN;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalnChild extends Model
{
    protected $table = 'saln_children';

    protected $fillable = [
        'saln_record_id',
        'name',
        'age',
    ];

    protected $casts = [
        'age' => 'integer',
    ];

    public function salnRecord(): BelongsTo
    {
        return $this->belongsTo(SalnRecord::class, 'saln_record_id');
    }
}
