<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DostSubStrategy extends Model
{
    protected $fillable = ['dost_strategy_id', 'description'];

    public function strategy()
    {
        return $this->belongsTo(DostStrategy::class, 'dost_strategy_id');
    }

    public function opcrIndicators()
    {
        return $this->hasMany(\App\Models\OPCR\OpcrIndicator::class, 'dost_sub_strategy_id');
    }
}
