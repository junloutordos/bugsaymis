<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LibraryAttendance extends Model
{
    protected $table = 'library_attendances';
    protected $fillable = [
        'student_id', 'student_name', 'badge_id', 'scanned_at', 'type', 'section',
    ];
    public $timestamps = true;
    protected $dates = ['scanned_at'];
}
