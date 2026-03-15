<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LibraryAttendance extends Model
{
    protected $table = 'library_attendances';
    protected $guarded = [];
    public $timestamps = true;
    protected $dates = ['scanned_at'];
}
