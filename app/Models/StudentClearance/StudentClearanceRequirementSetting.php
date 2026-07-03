<?php

namespace App\Models\StudentClearance;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentClearanceRequirementSetting extends Model
{
    protected $fillable = [
        'requirement_code',
        'requirement_label',
        'requirement_type',
        'requirement_group',
        'assigned_user_id',
        'assigned_permission',
        'applies_grade_levels',
        'intern_only',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'assigned_user_id'     => 'integer',
        'applies_grade_levels' => 'array',
        'intern_only'          => 'boolean',
        'is_active'            => 'boolean',
        'sort_order'           => 'integer',
    ];

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }
}
