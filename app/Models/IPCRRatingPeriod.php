<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IPCRRatingPeriod extends Model
{
    protected $table = 'ipcr_rating_periods';

    protected $fillable = ['label', 'year', 'semester', 'start_date', 'end_date', 'status', 'is_active', 'is_current'];

    protected $casts = [
        'start_date' => 'date:Y-m-d',
        'end_date'   => 'date:Y-m-d',
        'is_active'  => 'boolean',
        'is_current' => 'boolean',
    ];

    public const STATUS_OPEN = 'open';
    public const STATUS_CLOSED = 'closed';

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('year')->orderBy('id');
    }

    public function scopeOpen($query)
    {
        return $query->where('status', self::STATUS_OPEN)->orderByDesc('year')->orderByDesc('semester');
    }

    public function scopeCurrent($query)
    {
        return $query->where('is_current', true);
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    public function isClosed(): bool
    {
        return $this->status === self::STATUS_CLOSED;
    }

    // CSC MC No. 6, s. 2012: accomplished IPCRs are due to HR within 15 days after the rating period ends
    public function hrDeadline(): ?string
    {
        return $this->end_date?->copy()->addDays(15)->format('Y-m-d');
    }

    public function ipcrs()
    {
        return $this->hasMany(EmployeeIPCR::class, 'rating_period_id');
    }
}
