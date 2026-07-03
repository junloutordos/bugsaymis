<?php

namespace App\Models\AMS;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityMealPlan extends Model
{
    protected $table = 'ams_activity_meal_plans';

    protected $fillable = [
        'activity_id',
        'date',
        'am_snacks',
        'lunch',
        'pm_snacks',
        'dinner',
    ];

    protected $casts = [
        'date'      => 'date:Y-m-d',
        'am_snacks' => 'boolean',
        'lunch'     => 'boolean',
        'pm_snacks' => 'boolean',
        'dinner'    => 'boolean',
    ];

    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class, 'activity_id');
    }
}
