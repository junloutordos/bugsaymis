<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Pds extends Model
{
    protected $fillable = [
        'user_id', // or employee_id if applicable
    ];

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
}
