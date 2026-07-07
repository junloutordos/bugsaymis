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

    /**
     * file_path marker for a document whose bytes sit in S3 staging awaiting
     * the queued Google Drive upload. Doubles as the ownership token that
     * lets a stale UploadApplicantDocumentsToDrive job detect it was
     * superseded by a re-submission.
     */
    public static function pendingPath(string $s3Key): string
    {
        return 's3-pending:' . $s3Key;
    }

    // ── Relationships ──────────────────────────────────────────────────────────

    public function applicant()
    {
        return $this->belongsTo(Applicant::class);
    }
}
