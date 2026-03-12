<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccomplishmentPhoto extends Model
{
    protected $fillable = [
        'accomplishment_id',
        'google_drive_file_id',
        'google_drive_link',
        'file_name',
        'local_path',
    ];

    public function accomplishment()
    {
        return $this->belongsTo(Accomplishment::class);
    }
}
