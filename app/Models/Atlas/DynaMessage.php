<?php

namespace App\Models\Atlas;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DynaMessage extends Model
{
    public $timestamps = false;

    protected $fillable = ['dyna_conversation_id', 'role', 'content', 'tool_calls', 'created_at'];

    protected $casts = [
        'tool_calls' => 'array',
        'created_at' => 'datetime',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(DynaConversation::class, 'dyna_conversation_id');
    }
}
