<?php

namespace Tests\Feature\SPMS;

use App\Models\SPMS\Ipcr;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IpcrModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_defaults_to_draft_target_status(): void
    {
        $ipcr = Ipcr::factory()->create();

        $this->assertSame(Ipcr::STATUS_DRAFT_TARGET, $ipcr->status);
    }

    public function test_belongs_to_user(): void
    {
        $user = User::factory()->create();
        $ipcr = Ipcr::factory()->create(['user_id' => $user->id]);

        $this->assertSame($user->id, $ipcr->user->id);
    }

    public function test_dpcr_id_is_nullable(): void
    {
        $ipcr = Ipcr::factory()->create();

        $this->assertNull($ipcr->dpcr_id);
    }

    public function test_belongs_to_dpcr_once_assigned(): void
    {
        $dpcr = \App\Models\SPMS\Dpcr::factory()->create();
        $ipcr = Ipcr::factory()->create(['dpcr_id' => $dpcr->id]);

        $this->assertSame($dpcr->id, $ipcr->dpcr->id);
    }
}
