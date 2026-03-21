<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Applicant extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'first_name',
        'middle_name',
        'last_name',
        'suffix',
        'birthdate',
        'address',
        'civil_status',
        'email',
        'contact_number',
        'eligibility',
        'prc_license_no',
        'school',
        'course',
        'year_graduated',
        'source',
        'status',
    ];

    protected $casts = [
        'birthdate' => 'date',
    ];

    // ── Accessors ──────────────────────────────────────────────────────────────

    public function getFullNameAttribute(): string
    {
        $parts = array_filter([
            $this->first_name,
            $this->middle_name ? $this->middle_name[0] . '.' : null,
            $this->last_name,
            $this->suffix,
        ]);
        return implode(' ', $parts);
    }

    // ── Relationships ──────────────────────────────────────────────────────────

    public function documents()
    {
        return $this->hasMany(ApplicantDocument::class);
    }

    public function applications()
    {
        return $this->hasMany(Application::class);
    }

    // ── Scopes ─────────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
