<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Document extends Model
{
    use HasFactory;

    protected $fillable = [
        'tracking_no',
        'document_type_id',
        'subject',
        'description',
        'is_confidential',
        'urgency',
        'origin_type',
        'source_office',
        'sender_name',
        'date_of_document',
        'date_received',
        'document_number',
        'priority',
        'deadline_at',
        'completed_at',
        'created_by',
        'current_holder_id',
        'overall_status',
    ];

    protected $casts = [
        'is_confidential'  => 'boolean',
        'date_of_document' => 'date',
        'date_received'    => 'date',
        'deadline_at'      => 'datetime',
        'completed_at'     => 'datetime',
    ];

    protected static function booted(): void
    {
        // Auto-generate tracking_no after creation
        static::created(function (Document $document) {
            $document->tracking_no = 'DOC-' . now()->year . '-' . str_pad($document->id, 5, '0', STR_PAD_LEFT);
            $document->saveQuietly();
        });
    }

    public function documentType()
    {
        return $this->belongsTo(DocumentType::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function currentHolder()
    {
        return $this->belongsTo(User::class, 'current_holder_id');
    }

    public function attachments()
    {
        return $this->hasMany(DocumentAttachment::class);
    }

    public function routings()
    {
        return $this->hasMany(DocumentRouting::class)->orderBy('sequence');
    }

    public function latestRouting()
    {
        return $this->hasOne(DocumentRouting::class)->latestOfMany();
    }
}
