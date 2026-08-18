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
        'evaluation_open',
        'evaluation_status_changed_at',
        'evaluation_status_changed_by',
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
        'evaluation_open' => 'boolean',
        'evaluation_status_changed_at' => 'datetime',
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

    public function attendanceDays(): HasMany
    {
        return $this->hasMany(ActivityAttendanceDay::class, 'activity_id');
    }

    public function isTrainingWorkshopSeminar(): bool
    {
        return $this->activity_type === self::TYPE_TRAINING_WORKSHOP_SEMINAR;
    }

    public function statusChangedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'evaluation_status_changed_by');
    }

    public function isMultiDay(): bool
    {
        return $this->start_date && $this->end_date && $this->end_date->gt($this->start_date);
    }

    /** Inclusive list of Y-m-d date strings from start_date to end_date. Empty if not multi-day. */
    public function attendanceDayList(): array
    {
        if (! $this->isMultiDay()) {
            return [];
        }

        $days = [];
        $cursor = $this->start_date->copy();
        while ($cursor->lte($this->end_date) && count($days) < 60) {
            $days[] = $cursor->toDateString();
            $cursor->addDay();
        }

        return $days;
    }
}
