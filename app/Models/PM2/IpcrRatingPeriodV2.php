<?php

namespace App\Models\PM2;

use Illuminate\Database\Eloquent\Model;

class IpcrRatingPeriodV2 extends Model
{
    protected $table = 'ipcr_rating_periods_v2';

    protected $fillable = ['label', 'year', 'semester', 'start_date', 'end_date', 'status', 'is_current'];

    protected $casts = [
        'start_date' => 'date:Y-m-d',
        'end_date'   => 'date:Y-m-d',
        'is_current' => 'boolean',
    ];

    public const STATUS_OPEN = 'open';
    public const STATUS_CLOSED = 'closed';

    public function scopeCurrent($query)
    {
        return $query->where('is_current', true);
    }

    public function scopeOpen($query)
    {
        return $query->where('status', self::STATUS_OPEN)->orderByDesc('year')->orderByDesc('semester');
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    public function isClosed(): bool
    {
        return $this->status === self::STATUS_CLOSED;
    }

    public function templates()
    {
        return $this->hasMany(OpcrTemplate::class, 'ipcr_rating_period_v2_id');
    }

    public function ipcrs()
    {
        return $this->hasMany(EmployeeIpcrV2::class, 'rating_period_id');
    }
}
