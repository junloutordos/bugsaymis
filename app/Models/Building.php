<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Building extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'no_of_rooms',
        'remarks',
        'building_use',
        'number_of_floors',
        'year_constructed',
        'year_completed',
        'amount',
    ];

    protected $casts = [
        'building_use' => 'array',
        'amount' => 'float',
    ];
}
