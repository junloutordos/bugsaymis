<?php

namespace App\Models\HR;

use Illuminate\Database\Eloquent\Model;

class Holiday extends Model
{
    protected $table = 'holidays';

    protected $fillable = [
        'name',
        'holiday_date',
        'type',
        'is_recurring',
        'description',
        'is_active',
    ];

    protected $casts = [
        'holiday_date' => 'date',
        'is_recurring' => 'boolean',
        'is_active'    => 'boolean',
    ];

    /**
     * Check if a given date is a non-working holiday.
     */
    public static function isNonWorking(\DateTimeInterface|string $date): bool
    {
        return static::query()
            ->where('is_active', true)
            ->whereIn('type', ['regular', 'special_non_working'])
            ->where('holiday_date', $date)
            ->exists();
    }
}
