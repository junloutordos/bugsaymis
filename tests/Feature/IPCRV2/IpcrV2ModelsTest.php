<?php

namespace Tests\Feature\IPCRV2;

use App\Models\EmployeeFunction;
use App\Models\IPCRV2\IpcrV2CoreItem;
use App\Models\IPCRV2\IpcrV2Record;
use App\Models\IPCRV2\IpcrV2SupportItem;
use App\Models\IPCRRatingPeriod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IpcrV2ModelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_record_relations_and_mutability(): void
    {
        $user = User::factory()->create();
        $period = IPCRRatingPeriod::create(['label' => 'Jan-Jun 2026', 'year' => 2026, 'semester' => 1, 'status' => 'open']);

        $record = IpcrV2Record::create(['user_id' => $user->id, 'rating_period_id' => $period->id]);

        $this->assertTrue($record->user->is($user));
        $this->assertTrue($record->period->is($period));
        $this->assertTrue($record->isMutable());

        $record->update(['status' => 'Director Signed']);
        $this->assertTrue($record->fresh()->isFinalized());
        $this->assertFalse($record->fresh()->isMutable());
    }

    public function test_core_item_belongs_to_record_and_optional_employee_function(): void
    {
        $user = User::factory()->create();
        $period = IPCRRatingPeriod::create(['label' => 'x', 'year' => 2026, 'semester' => 1, 'status' => 'open']);
        $record = IpcrV2Record::create(['user_id' => $user->id, 'rating_period_id' => $period->id]);
        $function = EmployeeFunction::create(['user_id' => $user->id, 'function_type' => 'core', 'source_type' => 'manual', 'label' => 'x']);

        $item = IpcrV2CoreItem::create([
            'ipcr_v2_id' => $record->id, 'employee_function_id' => $function->id, 'label' => 'Subject 1',
        ]);

        $this->assertTrue($item->ipcr->is($record));
        $this->assertTrue($item->employeeFunction->is($function));
        $this->assertCount(1, $record->fresh()->coreItems);
    }

    public function test_support_item_belongs_to_record(): void
    {
        $user = User::factory()->create();
        $period = IPCRRatingPeriod::create(['label' => 'x', 'year' => 2026, 'semester' => 1, 'status' => 'open']);
        $record = IpcrV2Record::create(['user_id' => $user->id, 'rating_period_id' => $period->id]);

        IpcrV2SupportItem::create(['ipcr_v2_id' => $record->id, 'label' => 'Committee w/o Load']);

        $this->assertCount(1, $record->fresh()->supportItems);
    }
}
