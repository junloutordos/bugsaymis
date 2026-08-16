<?php

namespace Database\Factories\SPMS;

use App\Models\SPMS\IpcrTarget;
use App\Models\SPMS\MovChecklistItem;
use Illuminate\Database\Eloquent\Factories\Factory;

class MovChecklistItemFactory extends Factory
{
    protected $model = MovChecklistItem::class;

    public function definition(): array
    {
        return [
            'spms_ipcr_target_id' => IpcrTarget::factory(),
            'document_type' => 'SIP',
            'status' => 'pending',
        ];
    }
}
