<?php

namespace App\Models\LostFound;

use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LostFoundMovement extends Model
{
    protected $table = 'lost_found_movements';

    public const UPDATED_AT = null;

    protected $fillable = [
        'lost_found_item_id',
        'action',
        'location',
        'notes',
        'actor_user_id',
        'actor_student_id',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(LostFoundItem::class, 'lost_found_item_id');
    }

    public function actorUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function actorStudent(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'actor_student_id');
    }
}
