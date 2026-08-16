<?php

namespace App\Models\SPMS;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MovChecklistItem extends Model
{
    use HasFactory;

    protected $table = 'spms_ipcr_mov_checklist';

    protected $fillable = [
        'spms_ipcr_target_id', 'document_type', 'status', 's3_key', 'submitted_at', 'submitted_by',
    ];

    protected $casts = ['submitted_at' => 'datetime'];

    public function target(): BelongsTo
    {
        return $this->belongsTo(IpcrTarget::class, 'spms_ipcr_target_id');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }
}
