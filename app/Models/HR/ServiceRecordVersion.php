<?php

namespace App\Models\HR;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceRecordVersion extends Model
{
    protected $table = 'hr_service_record_versions';
    protected $guarded = [];

    protected $casts = [
        'snapshot' => 'array',
        'certified_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
