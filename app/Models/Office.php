<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Office extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'division_id',
        'unit_head',
    ];

    public function division()
    {
        return $this->belongsTo(\App\Models\Division::class);
    }

    public function unitHeadUser()
    {
        return $this->belongsTo(\App\Models\User::class, 'unit_head');
    }
}
