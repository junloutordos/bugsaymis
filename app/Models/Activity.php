<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Activity extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'date',
        'venue',
        'participants',
        'materials_no_cost',
        'materials_with_cost',
        'working_committee',
    ];

    protected $casts = [
        'date' => 'date:Y-m-d',
    ];
}
