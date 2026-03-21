<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RewardNomination extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'reward_type_id',
        'nominee_id',
        'nominated_by',
        'justification',
        'supporting_documents',
        'status',
        'period',
        'team_name',
        'remarks',
    ];

    protected $casts = [
        'supporting_documents' => 'array',
    ];

    public function rewardType(): BelongsTo
    {
        return $this->belongsTo(RewardType::class);
    }

    public function nominee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'nominee_id');
    }

    public function nominator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'nominated_by');
    }

    public function evaluations(): HasMany
    {
        return $this->hasMany(RewardEvaluation::class, 'nomination_id');
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(RewardApproval::class, 'nomination_id');
    }

    public function reward(): HasOne
    {
        return $this->hasOne(Reward::class, 'nomination_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(RecognitionLog::class, 'nomination_id')->orderBy('acted_at');
    }
}
