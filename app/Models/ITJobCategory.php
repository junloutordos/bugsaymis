<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ITJobCategory extends Model
{
    use HasFactory;

    // ✅ Explicitly set the table name if it doesn’t follow Laravel’s naming convention
    protected $table = 'it_job_requests_categories';

    // ✅ Specify which fields are mass assignable
    protected $fillable = [
        'name',
        
    ];

    // ✅ (Optional) If you don’t want created_at and updated_at
    // public $timestamps = false;

    // ✅ (Optional) Relationships
    // Example: If a category has many IT Job Requests
    public function jobRequests()
    {
        return $this->hasMany(ITJobRequest::class, 'id');
    }
}

