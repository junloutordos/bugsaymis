<?php

namespace App\Models\HR;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServiceRecordEntry extends Model
{
    protected $table = 'hr_service_record_entries';

    protected $guarded = [];

    protected $casts = [
        'service_from' => 'date:Y-m-d',
        'service_to' => 'date:Y-m-d',
        'appointment_issued_at' => 'date:Y-m-d',
        'assumed_at' => 'date:Y-m-d',
        'csc_action_at' => 'date:Y-m-d',
        'separation_date' => 'date:Y-m-d',
        'salary_amount' => 'decimal:2',
        'credited_as_government_service' => 'boolean',
        'verified_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function lwopPeriods(): HasMany
    {
        return $this->hasMany(ServiceRecordLwopPeriod::class, 'service_record_entry_id')
            ->orderBy('date_from');
    }

    public function evidence(): HasMany
    {
        return $this->hasMany(ServiceRecordEvidence::class, 'service_record_entry_id')
            ->latest();
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeVerified($query)
    {
        return $query->where('status', 'verified');
    }

    public function scopeCreditable($query)
    {
        return $query->where('credited_as_government_service', true);
    }
}
