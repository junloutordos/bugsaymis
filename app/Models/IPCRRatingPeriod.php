<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IPCRRatingPeriod extends Model
{
    protected $table = 'ipcr_rating_periods';

    protected $fillable = ['label', 'year', 'is_active'];

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('year')->orderBy('id');
    }
}
