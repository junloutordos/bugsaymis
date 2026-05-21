<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DocumentType extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'description',
        'lead_time_hours',
        'is_active',
        'routing_type',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function documents()
    {
        return $this->hasMany(Document::class);
    }

    public function routingSteps()
    {
        return $this->hasMany(DocumentTypeRoutingStep::class)->orderBy('step_order');
    }
}
