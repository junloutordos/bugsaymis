<?php

namespace App\Models\Payroll;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollEmail extends Model
{
    protected $table = 'payroll_emails';

    protected $fillable = [
        'payroll_item_id', 'send_type', 'to_email', 'bcc_email',
        'subject', 'message_id', 'status', 'attempts', 'last_error', 'sent_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(PayrollItem::class, 'payroll_item_id');
    }
}
