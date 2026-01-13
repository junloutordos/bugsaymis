<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CollectionCategory extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'created_by', 'student_borrowing_days', 'employee_borrowing_days'];
}
