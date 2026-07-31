<?php

namespace App\Models\ALP;

use App\Models\User;
use App\Traits\HasApprovalSnapshots;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AlpReport extends Model
{
    use HasApprovalSnapshots;

    protected $table = 'alp_reports';

    protected $fillable = ['alp_program_cycle_id', 'report_type', 'period', 'form_code', 'version_no', 'revision_no', 'data', 'status', 'pdf_file_id', 'prepared_by', 'reviewed_by', 'submitted_at', 'approved_at'];

    protected $casts = ['data' => 'array', 'submitted_at' => 'datetime', 'approved_at' => 'datetime'];

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(AlpProgramCycle::class, 'alp_program_cycle_id');
    }

    public function preparer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
