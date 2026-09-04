<?php

namespace App\Models\OPCR;

use Illuminate\Database\Eloquent\Model;

class OpcrIndicatorActual extends Model
{
    protected $fillable = ['opcr_indicator_id', 'quarter', 'value'];

    public function indicator()
    {
        return $this->belongsTo(OpcrIndicator::class, 'opcr_indicator_id');
    }
}
