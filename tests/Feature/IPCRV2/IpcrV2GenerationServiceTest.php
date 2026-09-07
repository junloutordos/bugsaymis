<?php

namespace Tests\Feature\IPCRV2;

use App\Models\EmployeeFunction;
use App\Models\IPCRRatingPeriod;
use App\Models\User;
use App\Services\IPCRV2\IpcrV2GenerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class IpcrV2GenerationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_generates_record_and_snapshots_core_and_support_items(): void
    {
        $user = User::factory()->create();
        $period = IPCRRatingPeriod::create(['label' => 'x', 'year' => 2026, 'semester' => 1, 'status' => 'open']);

        EmployeeFunction::create(['user_id' => $user->id, 'function_type' => 'core', 'source_type' => 'manual', 'label' => 'Subject 1', 'weight_percent' => 60]);
        EmployeeFunction::create(['user_id' => $user->id, 'function_type' => 'core', 'source_type' => 'manual', 'label' => 'Subject 2', 'weight_percent' => 40]);
        EmployeeFunction::create(['user_id' => $user->id, 'function_type' => 'support', 'source_type' => 'manual', 'label' => 'Committee w/o Load']);

        $record = (new IpcrV2GenerationService())->generateTargets($user, $period);

        $this->assertCount(2, $record->coreItems);
        $this->assertCount(1, $record->supportItems);
        $this->assertSame('Subject 1', $record->coreItems->first()->label);
    }

    public function test_throws_when_core_weights_do_not_sum_to_100(): void
    {
        $user = User::factory()->create();
        $period = IPCRRatingPeriod::create(['label' => 'x', 'year' => 2026, 'semester' => 1, 'status' => 'open']);

        EmployeeFunction::create(['user_id' => $user->id, 'function_type' => 'core', 'source_type' => 'manual', 'label' => 'Subject 1', 'weight_percent' => 60]);

        $this->expectException(ValidationException::class);
        (new IpcrV2GenerationService())->generateTargets($user, $period);
    }

    public function test_frozen_label_survives_a_later_employee_function_edit(): void
    {
        $user = User::factory()->create();
        $period = IPCRRatingPeriod::create(['label' => 'x', 'year' => 2026, 'semester' => 1, 'status' => 'open']);
        $function = EmployeeFunction::create(['user_id' => $user->id, 'function_type' => 'core', 'source_type' => 'manual', 'label' => 'Subject 1', 'weight_percent' => 100]);

        $record = (new IpcrV2GenerationService())->generateTargets($user, $period);
        $function->update(['label' => 'Renamed Subject']);

        $this->assertSame('Subject 1', $record->fresh()->coreItems->first()->label);
    }

    public function test_sync_adds_functions_created_after_generation(): void
    {
        $user = User::factory()->create();
        $period = IPCRRatingPeriod::create(['label' => 'x', 'year' => 2026, 'semester' => 1, 'status' => 'open']);

        $record = (new IpcrV2GenerationService())->generateTargets($user, $period);
        $this->assertCount(0, $record->coreItems);
        $this->assertCount(0, $record->supportItems);

        EmployeeFunction::create(['user_id' => $user->id, 'function_type' => 'core', 'source_type' => 'manual', 'label' => 'Subject 1', 'weight_percent' => 100]);
        EmployeeFunction::create(['user_id' => $user->id, 'function_type' => 'support', 'source_type' => 'manual', 'label' => 'Discipline Committee']);

        $added = (new IpcrV2GenerationService())->syncNewFunctions($record);

        $this->assertSame(2, $added);
        $record->refresh();
        $this->assertCount(1, $record->coreItems);
        $this->assertCount(1, $record->supportItems);
        $this->assertSame('Subject 1', $record->coreItems->first()->label);
    }

    public function test_sync_never_touches_already_snapshotted_items(): void
    {
        $user = User::factory()->create();
        $period = IPCRRatingPeriod::create(['label' => 'x', 'year' => 2026, 'semester' => 1, 'status' => 'open']);
        $function = EmployeeFunction::create(['user_id' => $user->id, 'function_type' => 'core', 'source_type' => 'manual', 'label' => 'Subject 1', 'weight_percent' => 100]);

        $record = (new IpcrV2GenerationService())->generateTargets($user, $period);
        $record->coreItems->first()->update(['target' => 'Existing target text']);
        $function->update(['label' => 'Renamed Subject']);

        $added = (new IpcrV2GenerationService())->syncNewFunctions($record);

        $this->assertSame(0, $added);
        $record->refresh();
        $this->assertCount(1, $record->coreItems);
        $this->assertSame('Subject 1', $record->coreItems->first()->label);
        $this->assertSame('Existing target text', $record->coreItems->first()->target);
    }

    public function test_sync_throws_when_adding_a_core_function_breaks_the_100_percent_total(): void
    {
        $user = User::factory()->create();
        $period = IPCRRatingPeriod::create(['label' => 'x', 'year' => 2026, 'semester' => 1, 'status' => 'open']);
        EmployeeFunction::create(['user_id' => $user->id, 'function_type' => 'core', 'source_type' => 'manual', 'label' => 'Subject 1', 'weight_percent' => 100]);

        $record = (new IpcrV2GenerationService())->generateTargets($user, $period);
        EmployeeFunction::create(['user_id' => $user->id, 'function_type' => 'core', 'source_type' => 'manual', 'label' => 'Subject 2', 'weight_percent' => 40]);

        $this->expectException(ValidationException::class);
        (new IpcrV2GenerationService())->syncNewFunctions($record);
    }
}
