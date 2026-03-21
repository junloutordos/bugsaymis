<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApplicantDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'applicant_id',
        'document_type',
        'file_path',
        'original_name',
        'drive_file_id',
        'drive_url',
        'file_size',
        'mime_type',
        'status',
        'remarks',
    ];

    // ── Relationships ──────────────────────────────────────────────────────────

    public function applicant()
    {
        return $this->belongsTo(Applicant::class);
    }
}
