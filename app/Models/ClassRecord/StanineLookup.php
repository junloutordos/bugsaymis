<?php

namespace App\Models\ClassRecord;

use Illuminate\Database\Eloquent\Model;

class StanineLookup extends Model
{
    protected $table = 'stanine_lookup';

    // Read-only reference table — no mass assignment needed
    protected $guarded = ['id'];

    protected $casts = [
        'percentage'       => 'decimal:2',
        'grade_equivalent' => 'decimal:3',
    ];

    /**
     * Look up the grade equivalent for a given percentage.
     * Finds the closest percentage row (rounds down to nearest integer).
     */
    public static function lookup(float $percentage): ?self
    {
        $pct = max(39, min(100, (int) floor($percentage)));
        return static::where('percentage', $pct)->first();
    }
}
