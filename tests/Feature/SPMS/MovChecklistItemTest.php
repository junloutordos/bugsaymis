<?php

namespace Tests\Feature\SPMS;

use App\Models\SPMS\IpcrTarget;
use App\Models\SPMS\MovChecklistItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MovChecklistItemTest extends TestCase
{
    use RefreshDatabase;

    public function test_defaults_to_pending_status(): void
    {
        $item = MovChecklistItem::factory()->create();

        $this->assertSame('pending', $item->status);
    }

    public function test_belongs_to_ipcr_target(): void
    {
        $target = IpcrTarget::factory()->create();
        $item = MovChecklistItem::factory()->create(['spms_ipcr_target_id' => $target->id]);

        $this->assertSame($target->id, $item->target->id);
        $this->assertTrue($target->movChecklistItems->contains('id', $item->id));
    }
}
