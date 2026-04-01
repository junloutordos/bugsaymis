<?php

namespace App\Models\HR;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeaveCredit extends Model
{
    protected $table = 'leave_credits';

    protected $fillable = [
        'user_id',
        'leave_type_id',
        'year',
        'earned',
        'carried_over',
        'used',
        'forfeited',
        'monetized',
    ];

    protected $casts = [
        'earned'      => 'decimal:4',
        'carried_over' => 'decimal:4',
        'used'        => 'decimal:4',
        'forfeited'   => 'decimal:4',
        'monetized'   => 'decimal:4',
        'balance'     => 'decimal:4',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(LeaveCreditTransaction::class, 'user_id', 'user_id')
                    ->where('leave_type_id', $this->leave_type_id)
                    ->where('year', $this->year);
    }
}
