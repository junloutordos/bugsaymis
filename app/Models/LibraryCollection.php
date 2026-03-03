<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LibraryCollection extends Model
{
    protected $table = 'library_collections';
    // Avoid allowing all attributes to be mass-assignable.
    protected $guarded = ['id', 'created_at', 'updated_at'];
}
