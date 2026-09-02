<?php

namespace App\Models\PM2;

use Illuminate\Database\Eloquent\Model;

class OpcrTemplateItem extends Model
{
    protected $table = 'opcr_template_items';

    protected $fillable = [
        'opcr_template_id', 'strategy_label', 'output_outcome', 'success_indicator', 'target',
        'rating_scale_quality', 'rating_scale_efficiency', 'rating_scale_timeliness',
        'weight_percent', 'sort_order',
    ];

    protected $casts = ['weight_percent' => 'decimal:2'];

    public function template()
    {
        return $this->belongsTo(OpcrTemplate::class, 'opcr_template_id');
    }
}
