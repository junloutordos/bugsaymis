<?php

namespace App\Models\FacultyLoading;

use App\Models\Room;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Classroom extends Model
{
    protected $table = 'classrooms';

    protected $fillable = [
        'name',
        'code',
        'classroom_type',
        'capacity',
        'building',
        'floor',
        'room_id',
        'is_available',
        'remarks',
        'nfc_uuid',
    ];

    protected $casts = [
        'capacity'     => 'integer',
        'floor'        => 'integer',
        'is_available' => 'boolean',
    ];

    /** Optional link to the existing rooms/facilities table */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function classSchedules(): HasMany
    {
        return $this->hasMany(ClassSchedule::class);
    }

    public function tapLogs(): HasMany
    {
        return $this->hasMany(TeacherTapLog::class);
    }

    public function scopeAvailable($query)
    {
        return $query->where('is_available', true);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('classroom_type', $type);
    }

    /** Check if this room can accommodate a given class size */
    public function canAccommodate(int $students): bool
    {
        return $this->capacity >= $students;
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->name . ' (' . $this->code . ')';
    }
}
