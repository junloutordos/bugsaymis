<?php

namespace App\Models\AMS;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Activity extends Model
{
    protected $table = 'ams_activities';

    protected $fillable = [
        'user_id',
        'title',
        'activity_type',
        'start_date',
        'start_time',
        'end_date',
        'end_time',
        'total_hours',
        'venue',
        'resource_person',
        'what_to_bring',
        'banner',
        'special_order',
        'activity_report',
        'official_documentation',
        'qr_token',
    ];

    protected static function booted(): void
    {
        static::creating(function (Activity $model) {
            if (empty($model->qr_token)) {
                $model->qr_token = (string) Str::uuid();
            }
        });
    }

    protected $casts = [
        'start_date' => 'date:Y-m-d',
        'end_date'   => 'date:Y-m-d',
    ];

    public const TYPE_IN_HOUSE = 'in_house';
    public const TYPE_TRAINING_WORKSHOP_SEMINAR = 'training_workshop_seminar';

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function coProponents(): HasMany
    {
        return $this->hasMany(ActivityCoProponent::class, 'activity_id');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(ActivityParticipant::class, 'activity_id');
    }

    public function studentAttendance(): HasMany
    {
        return $this->hasMany(ActivityStudentAttendance::class, 'activity_id');
    }

    public function mealPlans(): HasMany
    {
        return $this->hasMany(ActivityMealPlan::class, 'activity_id')->orderBy('date');
    }

    public function speakers(): HasMany
    {
        return $this->hasMany(ActivitySpeaker::class, 'activity_id')->orderBy('sort_order');
    }

    public function twsEvaluations(): HasMany
    {
        return $this->hasMany(ActivityTwsEvaluation::class, 'activity_id');
    }

    public function isTrainingWorkshopSeminar(): bool
    {
        return $this->activity_type === self::TYPE_TRAINING_WORKSHOP_SEMINAR;
    }
}
