<?php

namespace App\Models;

use App\Models\StudentAttendance\ParentContact;
use App\Models\StudentAttendance\StudentAttendanceLog;
use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Laravel\Sanctum\HasApiTokens;

class Student extends Model implements AuthenticatableContract
{
    // AtlasGo issues Sanctum tokens directly against this model (polymorphic
    // tokenable — no write to the `students` table itself, so the read-only
    // convention below is preserved). This is what lets AtlasGo authenticate
    // students without ever creating a row in the main Atlas `users` table.
    //
    // Authenticatable is required beyond auth:sanctum itself (which resolves
    // the tokenable model directly, contract or not) — Laravel's core
    // ThrottleRequests middleware unconditionally calls
    // $user->getAuthIdentifier() on any authenticated request, which
    // fatals without this. Student has no password/remember-token columns,
    // but the trait's defaults (empty string / null) are fine — nothing in
    // this app authenticates a Student by password.
    use Authenticatable, HasApiTokens;

    protected $table = 'students';

    // Legacy table has no updated_at
    const UPDATED_AT = null;
    public $timestamps = false;

    // Read-only — prevent accidental writes through this model
    protected $guarded = ['*'];

    // ── Relations ──────────────────────────────────────────────────────────────

    public function attendanceLogs(): HasMany
    {
        return $this->hasMany(StudentAttendanceLog::class, 'student_id');
    }

    public function parentContacts(): BelongsToMany
    {
        return $this->belongsToMany(
            ParentContact::class,
            'student_parent_contact',
            'student_id',
            'parent_contact_id'
        )->withPivot('relationship');
    }

    public function credential(): HasOne
    {
        return $this->hasOne(StudentCredential::class, 'student_id');
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    /** Full name formatted as "Lastname, Firstname Middlename" */
    public function getFullNameAttribute(): string
    {
        $middle = $this->middlename ? " {$this->middlename}" : '';
        return trim("{$this->lastname}, {$this->firstname}{$middle}");
    }

    /** The barcode value used for gate scanning */
    public function getBarcodeAttribute(): ?string
    {
        return $this->pisaysystemID ?: null;
    }
}
