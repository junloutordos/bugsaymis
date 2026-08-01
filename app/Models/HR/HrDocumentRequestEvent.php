<?php

namespace App\Models\HR;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrDocumentRequestEvent extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'hr_document_request_events';

    protected $fillable = [
        'request_id', 'actor_id', 'action', 'from_status', 'to_status',
        'remarks', 'metadata', 'created_at',
    ];

    protected $casts = ['metadata' => 'array', 'created_at' => 'datetime'];

    public function request(): BelongsTo
    {
        return $this->belongsTo(HrDocumentRequest::class, 'request_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \RuntimeException('HR document request events are immutable.'));
        static::deleting(fn () => throw new \RuntimeException('HR document request events cannot be deleted.'));
    }
}
