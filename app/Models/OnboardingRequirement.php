<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OnboardingRequirement extends Model
{
    use HasFactory;

    protected $fillable = [
        'recruitment_type_id',
        'requirement_name',
        'description',
        'is_required',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'is_active'   => 'boolean',
    ];

    // ── Relationships ──────────────────────────────────────────────────────────

    public function recruitmentType()
    {
        return $this->belongsTo(RecruitmentType::class);
    }
}
