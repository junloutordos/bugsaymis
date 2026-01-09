<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MessengerialRequest extends Model
{
    use HasFactory;

    protected $table = 'messengerial_requests';
    protected $casts = [
        'delivery_methods' => 'array',
        'messengerial_kinds' => 'array',
        'declined_at' => 'datetime',
        'division_chief_id' => 'integer',
        'completed_at' => 'datetime',
    ];

    protected $dates = [
        'declined_at',
        'completed_at',
    ];

    // proof_of_delivery path stored in storage (public)
    protected $fillable = [
        'requestor', 'unit', 'purpose', 'destination', 'reference_no', 'email', 'status', 'decline_reason', 'declined_at', 'delivery_methods', 'consignee_name', 'consignee_contact', 'consignee_email', 'messengerial_kinds', 'division_chief_id', 'proof_of_delivery', 'completed_at'
    ];

}
