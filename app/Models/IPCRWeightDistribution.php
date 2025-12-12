<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IPCRWeightDistribution extends Model
{
    protected $fillable = ['division_id','strategic','core','support'];

    public function division()
    {
        return $this->belongsTo(Division::class);
    }
}
