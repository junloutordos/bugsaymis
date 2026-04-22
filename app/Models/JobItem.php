<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class JobItem extends Model
{
    use HasFactory, SoftDeletes;

    protected $casts = [
        'competencies' => 'array',
    ];

    protected $fillable = [
        'recruitment_type_id',
        'position_title',
        'plantilla_item_no',
        'salary_grade',
        'salary_step',
        'monthly_salary',
        'daily_rate',
        'qualification_standards',
        'duties_responsibilities',
        'education_level',
        'subject_area',
        'education',
        'experience',
        'training',
        'eligibility',
        'competencies',
        'duration_type',
        'budget_source',
        'office_id',
        'status',
        'created_by',
    ];

    // ── Relationships ──────────────────────────────────────────────────────────

    public function recruitmentType()
    {
        return $this->belongsTo(RecruitmentType::class);
    }

    public function office()
    {
        return $this->belongsTo(Office::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function jobVacancies()
    {
        return $this->hasMany(JobVacancy::class);
    }

    public function requirements()
    {
        return $this->belongsToMany(
            ApplicationRequirement::class,
            'job_item_requirements',
            'job_item_id',
            'requirement_id'
        )->withPivot('is_mandatory')->withTimestamps();
    }

    public function activeVacancy()
    {
        return $this->hasOne(JobVacancy::class)->where('status', 'open');
    }

    // ── Scopes ─────────────────────────────────────────────────────────────────

    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }

    public function scopeByType($query, int $typeId)
    {
        return $query->where('recruitment_type_id', $typeId);
    }
}
