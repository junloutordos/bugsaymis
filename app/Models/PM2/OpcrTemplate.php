<?php

namespace App\Models\PM2;

use Illuminate\Database\Eloquent\Model;

class OpcrTemplate extends Model
{
    protected $table = 'opcr_templates';

    protected $fillable = ['ipcr_rating_period_v2_id', 'is_current'];

    protected $casts = ['is_current' => 'boolean'];

    public function period()
    {
        return $this->belongsTo(IpcrRatingPeriodV2::class, 'ipcr_rating_period_v2_id');
    }

    public function items()
    {
        return $this->hasMany(OpcrTemplateItem::class, 'opcr_template_id')->orderBy('sort_order');
    }
}
