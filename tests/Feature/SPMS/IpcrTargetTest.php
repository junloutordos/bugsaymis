<?php

namespace Tests\Feature\SPMS;

use App\Models\SPMS\Ipcr;
use App\Models\SPMS\IpcrTarget;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IpcrTargetTest extends TestCase
{
    use RefreshDatabase;

    public function test_belongs_to_ipcr(): void
    {
        $ipcr = Ipcr::factory()->create();
        $target = IpcrTarget::factory()->create(['ipcr_id' => $ipcr->id]);

        $this->assertSame($ipcr->id, $target->ipcr->id);
        $this->assertTrue($ipcr->targets->contains('id', $target->id));
    }

    public function test_function_type_and_weight_are_stored(): void
    {
        $target = IpcrTarget::factory()->create([
            'function_type' => 'core',
            'weight_pct' => 12.5,
            'rubric_text' => '5: 96-100%, 4: 91-95%, 3: 86-90%, 2: 81-85%, 1: below 81%',
        ]);

        $this->assertSame('core', $target->function_type);
        $this->assertSame('12.50', $target->weight_pct);
        $this->assertStringContainsString('96-100%', $target->rubric_text);
    }
}
