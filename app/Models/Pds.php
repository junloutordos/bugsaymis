<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Pds extends Model
{
    protected $fillable = [
        'user_id', // or employee_id if applicable
        'submitted_at',
        'annual_reminder_sent_at',
    ];

    protected $casts = [
        'submitted_at'             => 'datetime',
        'annual_reminder_sent_at'  => 'datetime',
    ];

    /** True when the PDS has never been submitted or was last submitted over a year ago. */
    public function isOverdue(): bool
    {
        return is_null($this->submitted_at) || $this->submitted_at->lt(now()->subYear());
    }

    /** Owner, SuperAdmin, or anyone with pds.view_all (HR) can view/export read-only. */
    public function canBeViewedBy(User $user): bool
    {
        return $user->id === $this->user_id || $user->isSuperAdmin() || $user->hasPermission('pds.view_all');
    }

    /** Only the owner or SuperAdmin can edit/submit — HR's pds.view_all does NOT grant edit rights. */
    public function canBeEditedBy(User $user): bool
    {
        return $user->id === $this->user_id || $user->isSuperAdmin();
    }

    public function personalInfo() { return $this->hasOne(PDSPersonalInfo::class); }
    public function familyBackground() { return $this->hasOne(PDSFamilyBackground::class); }
    public function children() { return $this->hasMany(PDSChild::class); }
    public function education() { return $this->hasMany(PDSEducation::class); }
    public function eligibility() { return $this->hasMany(PDSEligibility::class); }
    public function workExperience() { return $this->hasMany(PDSWorkExperience::class); }
    public function voluntaryWork() { return $this->hasMany(PDSVoluntaryWork::class); }
    public function trainings() { return $this->hasMany(PDSTraining::class); }
    public function skillsHobbies() { return $this->hasMany(PDSSkillsHobbies::class); }
    public function nonAcademicRecognition() { return $this->hasMany(PDSNonAcademicRecognition::class); }
    public function membershipOrganizations() {return $this->hasMany(PDSMembershipOrganization::class);}
    public function questions() { return $this->hasOne(PDSQuestions::class); }
    public function references() { return $this->hasMany(PDSReference::class); }
    public function otherInfo() { return $this->hasOne(PDSOtherInfo::class); }
    public function workExperienceSheets() { return $this->hasMany(PDSWorkExperienceSheet::class)->orderBy('sort_order'); }
}
