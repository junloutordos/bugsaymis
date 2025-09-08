<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ICTEquipment extends Model
{
    use HasFactory;

    protected $table = 'ict_equipments'; // explicit table name

    protected $fillable = [
        'property_no',
        'device_type',
        'description',
        'location',
        'date_entry',
        'unit',
        'owner',
        'status',
        'remarks',
        'encodedby',
        'amount',
        'date_acquired',
        'serialno',
        'category',
        'qr_code_path',
    ];

    protected $casts = [
        'date_entry'    => 'date',
        'date_acquired' => 'date',
        'amount'        => 'decimal:2',
    ];
}
