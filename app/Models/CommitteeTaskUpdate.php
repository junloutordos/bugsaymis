<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommitteeTaskUpdate extends Model
{
    protected $fillable = ['committee_task_id', 'user_id', 'body', 'is_accomplishment', 'mov_link'];

    protected $casts = ['is_accomplishment' => 'boolean'];

    public function task(): BelongsTo
    {
        return $this->belongsTo(CommitteeTask::class, 'committee_task_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
