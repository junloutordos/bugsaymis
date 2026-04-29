<?php

namespace App\Models\FacultyLoading;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SchoolYear extends Model
{
    use HasFactory;
    protected $table = 'school_years';

    protected $fillable = [
        'name',
        'start_date',
        'end_date',
        'is_current',
        'status',
        'workflow_status',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date'   => 'date',
        'is_current' => 'boolean',
    ];

    public function terms(): HasMany
    {
        return $this->hasMany(AcademicTerm::class);
    }

    public function facultyLoads(): HasMany
    {
        return $this->hasMany(FacultyLoad::class);
    }

    public function currentTerm(): ?AcademicTerm
    {
        return $this->terms()->where('is_current', true)->first();
    }

    public function scopeCurrent($query)
    {
        return $query->where('is_current', true);
    }

    public function vacancies(): HasMany
    {
        return $this->hasMany(FacultyVacancy::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopePublished($query)
    {
        return $query->where('workflow_status', 'published');
    }

    public function scopeWorkflow($query, string $status)
    {
        return $query->where('workflow_status', $status);
    }

    /** Advance the workflow to the next state. */
    public function advance(): bool
    {
        $transitions = [
            'draft'    => 'proposed',
            'proposed' => 'approved',
            'approved' => 'published',
        ];

        if (! isset($transitions[$this->workflow_status])) {
            return false;
        }

        return $this->update(['workflow_status' => $transitions[$this->workflow_status]]);
    }
}
