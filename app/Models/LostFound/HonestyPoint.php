<?php

namespace App\Models\LostFound;

use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HonestyPoint extends Model
{
    protected $table = 'honesty_points';

    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'student_id',
        'points',
        'reason',
        'lost_found_item_id',
        'awarded_by',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(LostFoundItem::class, 'lost_found_item_id');
    }
}
