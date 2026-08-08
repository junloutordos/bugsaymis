<?php

namespace App\Models\Learn;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class Page extends Model
{
    protected $table = 'learn_pages';

    protected $fillable = ['title', 'body', 'video_url'];

    public function moduleItem(): MorphOne
    {
        return $this->morphOne(ModuleItem::class, 'itemable');
    }
}
