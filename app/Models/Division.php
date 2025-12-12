<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Division extends Model
{
    use HasFactory;

    protected $fillable = [
        'division_name',
        'division_chief_id',
        'year',
        'status',
    ];

    /**
     * Relationship: Division belongs to a Chief (User)
     */
    public function divisionchief()
    {
        return $this->belongsTo(User::class, 'division_chief_id', 'id');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
    public function employees()
    {
        return $this->hasMany(User::class, 'division_id');
    }
    public function ipcrWeights()
    {
        return $this->hasMany(IPCRWeightDistribution::class);
    }
}
