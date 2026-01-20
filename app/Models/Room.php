<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Office;

class Room extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'code', 'building_id', 'capacity', 'remarks', 'office_id'
    ];

    public function building()
    {
        return $this->belongsTo(Building::class);
    }

    public function office()
    {
        return $this->belongsTo(Office::class, 'office_id');
    }
}
