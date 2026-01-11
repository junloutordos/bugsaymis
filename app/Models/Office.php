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
    ];

    public function division()
    {
        return $this->belongsTo(\App\Models\Division::class);
    }
}
