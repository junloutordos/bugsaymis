<?php

namespace App\Models\AMS;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityCoProponent extends Model
{
    protected $table = 'ams_activity_co_proponents';

    protected $fillable = [
        'activity_id',
        'employee_id',
    ];

    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class, 'activity_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id');
    }
}
