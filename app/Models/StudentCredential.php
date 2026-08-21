<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Password-login credentials for a student, used by AtlasGo's email+password
 * registration path. Kept separate from the legacy read-only `students`
 * table and from the main Atlas `users` table.
 */
class StudentCredential extends Model
{
    protected $table = 'student_credentials';

    protected $fillable = [
        'student_id',
        'email',
        'password',
        'status',
        'email_verified_at',
    ];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'password'          => 'hashed',
        'email_verified_at' => 'datetime',
    ];
}
