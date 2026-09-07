<?php

namespace Tests\Feature\IPCRV2;

use App\Models\IPCRV2\IpcrV2Record;
use App\Models\IPCRRatingPeriod;
use App\Models\User;
use App\Services\IPCRV2\IpcrV2WorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class IpcrV2WorkflowServiceTest extends TestCase
{
    use RefreshDatabase;

    private function period(string $status = 'open'): IPCRRatingPeriod
    {
        return IPCRRatingPeriod::create(['label' => 'x', 'year' => 2026, 'semester' => 1, 'status' => $status]);
    }

    public function test_transition_moves_new_target_to_for_review(): void
    {
        $user = User::factory()->create();
        $record = IpcrV2Record::create(['user_id' => $user->id, 'rating_period_id' => $this->period()->id]);
        $service = new IpcrV2WorkflowService();

        $service->transition($record, IpcrV2WorkflowService::STATUS_FOR_REVIEW);

        $this->assertSame(IpcrV2WorkflowService::STATUS_FOR_REVIEW, $record->fresh()->status);
    }

    public function test_transition_rejects_invalid_move(): void
    {
        $user = User::factory()->create();
        $record = IpcrV2Record::create(['user_id' => $user->id, 'rating_period_id' => $this->period()->id]);
        $service = new IpcrV2WorkflowService();

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $service->transition($record, IpcrV2WorkflowService::STATUS_DIRECTOR_SIGNED);
    }

    public function test_assert_mutable_blocks_a_finalized_record(): void
    {
        $user = User::factory()->create();
        $record = IpcrV2Record::create([
            'user_id' => $user->id, 'rating_period_id' => $this->period()->id,
            'status' => IpcrV2WorkflowService::STATUS_DIRECTOR_SIGNED,
        ]);
        $service = new IpcrV2WorkflowService();

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $service->assertMutable($record);
    }

    public function test_assert_no_duplicate_for_period_throws_on_second_record(): void
    {
        $user = User::factory()->create();
        $period = $this->period();
        IpcrV2Record::create(['user_id' => $user->id, 'rating_period_id' => $period->id]);
        $service = new IpcrV2WorkflowService();

        $this->expectException(ValidationException::class);
        $service->assertNoDuplicateForPeriod($user->id, $period->id);
    }

    public function test_finalize_computes_and_freezes_the_final_rating(): void
    {
        IPCRRatingPeriod::create(['label' => 'x', 'year' => 2026, 'semester' => 1, 'status' => 'open', 'is_current' => true]);
        \App\Models\AgencyOutcome::create(['outcome' => 'A']);
        \App\Models\OPCR\OpcrIndicator::create([
            'fiscal_year' => 2026,
            'agency_outcome_id' => \App\Models\AgencyOutcome::first()->id,
            'description' => 'x', 'rating_average' => 4.0,
        ]);

        $director = User::factory()->create(['electronic_signature' => 'sig.png']);
        $user = User::factory()->create();
        $record = IpcrV2Record::create([
            'user_id' => $user->id, 'rating_period_id' => IPCRRatingPeriod::first()->id,
            'status' => IpcrV2WorkflowService::STATUS_PMT_APPROVED,
        ]);
        $record->coreItems()->create(['label' => 'x', 'weight_percent' => 100, 'row_average' => 4.0]);

        $service = new IpcrV2WorkflowService();
        $service->finalize($record, $director);

        $fresh = $record->fresh();
        $this->assertSame(IpcrV2WorkflowService::STATUS_DIRECTOR_SIGNED, $fresh->status);
        $this->assertNotNull($fresh->final_numeric_rating);
        $this->assertNotNull($fresh->final_adjectival_rating);
    }
}
