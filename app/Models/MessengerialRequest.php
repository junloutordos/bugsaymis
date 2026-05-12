<?php

namespace App\Models;

use App\Traits\HasApprovalSnapshots;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MessengerialRequest extends Model
{
    use HasFactory, HasApprovalSnapshots;

    protected $table = 'messengerial_requests';
    protected $casts = [
        'delivery_methods' => 'array',
        'messengerial_kinds' => 'array',
        'declined_at' => 'datetime',
        'division_chief_id' => 'integer',
        'completed_at' => 'datetime',
        'rated_at' => 'datetime',
        'date_received_by_courier' => 'datetime',
        'date_delivered' => 'datetime',
        'courier_cost' => 'decimal:2',
        'user_id' => 'integer',
    ];

    protected $dates = [
        'declined_at',
        'completed_at',
        'rated_at',
    ];

    // proof_of_delivery path stored in storage (public)
    protected $fillable = [
        'user_id', 'requestor', 'unit', 'purpose', 'destination', 'reference_no', 'email', 'status', 'decline_reason', 'declined_at', 'delivery_methods', 'consignee_name', 'consignee_contact', 'consignee_email', 'messengerial_kinds', 'division_chief_id', 'proof_of_delivery', 'completed_at', 'rated_at',
        'rfsf_reference_no', 'courier_service_provider', 'courier_cost', 'date_received_by_courier', 'date_delivered', 'proof_remarks'
    ];

}
