<?php

namespace App\Models\Quiz;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuizSession extends Model
{
    public const STATUS_LOBBY = 'lobby';
    public const STATUS_QUESTION_ACTIVE = 'question_active';
    public const STATUS_REVEAL = 'reveal';
    public const STATUS_LEADERBOARD = 'leaderboard';
    public const STATUS_ENDED = 'ended';

    protected $fillable = [
        'quiz_id',
        'host_user_id',
        'game_pin',
        'status',
        'current_question_index',
        'require_roster_join',
        'question_started_at',
        'started_at',
        'ended_at',
    ];

    protected $casts = [
        'require_roster_join' => 'boolean',
        'question_started_at' => 'datetime',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }

    public function host(): BelongsTo
    {
        return $this->belongsTo(User::class, 'host_user_id');
    }

    public function players(): HasMany
    {
        return $this->hasMany(QuizPlayer::class, 'session_id')->orderByDesc('total_score');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(QuizPlayerAnswer::class, 'session_id');
    }
}
