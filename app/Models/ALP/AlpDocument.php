<?php

namespace App\Models\ALP;

use App\Models\User;
use App\Traits\HasApprovalSnapshots;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AlpDocument extends Model
{
    use HasApprovalSnapshots;

    protected $table = 'alp_documents';

    protected $fillable = ['alp_program_cycle_id', 'document_type', 'form_code', 'version_no', 'revision_no', 'content', 'status', 'file_id', 'prepared_by', 'submitted_at', 'approved_at'];

    protected $casts = ['content' => 'array', 'submitted_at' => 'datetime', 'approved_at' => 'datetime'];

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(AlpProgramCycle::class, 'alp_program_cycle_id');
    }

    public function preparer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by');
    }
}
