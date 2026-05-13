<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DigitalSignature extends Model
{
    protected $fillable = [
        'signable_type',
        'signable_id',
        'signer_id',
        'document_hash',
        'signature',
        'signature_type',
        'verification_token',
        'document_title',
        'metadata',
        'signed_at',
    ];

    protected $casts = [
        'metadata'  => 'array',
        'signed_at' => 'datetime',
    ];

    public function signer()
    {
        return $this->belongsTo(User::class, 'signer_id');
    }

    public function signable()
    {
        return $this->morphTo();
    }
}
