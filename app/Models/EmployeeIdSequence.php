<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeIdSequence extends Model
{
    protected $fillable = [
        'hired_year',
        'last_sequence',
    ];
}
