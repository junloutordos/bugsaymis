<?php

namespace App\Models\ALP;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AlpFinancialEntry extends Model
{
    protected $table = 'alp_financial_entries';

    protected $fillable = ['alp_program_cycle_id', 'alp_activity_id', 'transaction_date', 'entry_type', 'category', 'description', 'amount', 'source', 'receipt_file_id', 'status', 'recorded_by'];

    protected $casts = ['transaction_date' => 'date:Y-m-d', 'amount' => 'decimal:2'];

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(AlpProgramCycle::class, 'alp_program_cycle_id');
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(AlpActivity::class, 'alp_activity_id');
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
