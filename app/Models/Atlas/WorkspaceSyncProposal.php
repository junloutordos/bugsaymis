<?php

namespace App\Models\Atlas;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkspaceSyncProposal extends Model
{
    protected $fillable = [
        'subject_type',
        'subject_id',
        'subject_name',
        'current_email',
        'proposed_email',
        'org_unit_path',
        'confidence',
        'status',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
    ];

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
