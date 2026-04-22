<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Borrowing extends Model
{
    protected $table = 'borrowings';
    // Avoid allowing all attributes to be mass-assignable.
    protected $guarded = ['id', 'created_at', 'updated_at'];

    public function collection()
    {
        return $this->belongsTo(LibraryCollection::class, 'collection_id');
    }

    public function processedBy()
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function borrower()
    {
        return $this->morphTo(__FUNCTION__, 'borrower_type', 'borrower_id');
    }
}
