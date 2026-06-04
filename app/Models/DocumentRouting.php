<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class DocumentRouting extends Model
{
    use HasFactory;

    protected $fillable = [
        'document_id',
        'sequence',
        'sender_id',
        'receiver_id',
        'status',
        'received_at',
        'action_taken_at',
        'reviewed_at',
        'action_taken',
        'remarks',
        'instructions',
        'due_at',
        'forwarded_at',
        'is_terminal',
        'returned_at',
        'return_reason',
    ];

    protected $casts = [
        'received_at'     => 'datetime',
        'action_taken_at' => 'datetime',
        'reviewed_at'     => 'datetime',
        'due_at'          => 'datetime',
        'forwarded_at'    => 'datetime',
        'returned_at'     => 'datetime',
        'is_terminal'     => 'boolean',
    ];

    public function document()
    {
        return $this->belongsTo(Document::class);
    }

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function receiver()
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }

    public function getIsOverdueAttribute(): bool
    {
        if (!$this->due_at) return false;
        if (!in_array($this->status, ['Pending', 'Received'])) return false;
        return Carbon::now()->isAfter($this->due_at);
    }
}
