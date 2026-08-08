<?php

namespace App\Models\Learn;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class File extends Model
{
    protected $table = 'learn_files';

    protected $fillable = ['title', 's3_key', 'mime_type', 'size_bytes'];

    protected $casts = [
        'size_bytes' => 'integer',
    ];

    public function moduleItem(): MorphOne
    {
        return $this->morphOne(ModuleItem::class, 'itemable');
    }
}
