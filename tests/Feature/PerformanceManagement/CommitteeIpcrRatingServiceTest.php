<?php

namespace Tests\Feature\PerformanceManagement;

use App\Models\Committee;
use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\FacultyCommitteeAssignment;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\IPCRRatingPeriod;
use App\Models\IPCRV2\IpcrV2Record;
use App\Models\User;
use App\Services\PerformanceManagement\CommitteeIpcrRatingService;
use App\Services\PerformanceManagement\CommitteeIpcrSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommitteeIpcrRatingServiceTest extends TestCase
{
    use RefreshDatabase;

    private function setUpAssignmentWithRecord(): array
    {
        $sy = SchoolYear::create(['name' => '2026-2027', 'is_current' => true, 'start_date' => '2026-06-01', 'end_date' => '2027-03-31']);
        $term = AcademicTerm::create(['school_year_id' => $sy->id, 'name' => 'Full Term', 'term_type' => 'full_term', 'is_current' => true]);
        $member = User::factory()->create();
        $committee = Committee::create(['name' => 'Grievance Committee']);
        $assignment = FacultyCommitteeAssignment::create([
            'user_id' => $member->id, 'school_year_id' => $term->school_year_id,
            'academic_term_id' => $term->id, 'committee_id' => $committee->id,
            'committee_name' => $committee->name, 'role' => 'chairperson', 'status' => 'active',
        ]);
        $period = IPCRRatingPeriod::create(['label' => 'FY2026-1', 'year' => 2026, 'semester' => 1, 'status' => 'open', 'is_current' => true]);
        $record = IpcrV2Record::create(['user_id' => $member->id, 'rating_period_id' => $period->id]);

        return [$assignment, $record];
    }

    public function test_resolves_the_materialized_support_item_for_the_current_period(): void
    {
        [$assignment, $record] = $this->setUpAssignmentWithRecord();
        (new CommitteeIpcrSyncService())->syncForUser($assignment->faculty);

        $items = (new CommitteeIpcrRatingService())->resolveSupportItems($assignment);

        $this->assertCount(1, $items);
        $this->assertSame('Grievance Committee', $items->first()->label);
        $this->assertSame($record->id, $items->first()->ipcr_v2_id);
    }

    public function test_resolves_nothing_when_no_current_period_record_exists(): void
    {
        [$assignment] = $this->setUpAssignmentWithRecord();
        \App\Models\IPCRV2\IpcrV2Record::query()->delete();

        $items = (new CommitteeIpcrRatingService())->resolveSupportItems($assignment);

        $this->assertCount(0, $items);
    }

    public function test_is_committee_sourced_true_for_a_committee_materialized_item(): void
    {
        [$assignment] = $this->setUpAssignmentWithRecord();
        (new CommitteeIpcrSyncService())->syncForUser($assignment->faculty);
        $item = (new CommitteeIpcrRatingService())->resolveSupportItems($assignment)->first();

        $this->assertTrue((new CommitteeIpcrRatingService())->isCommitteeSourced($item));
    }

    public function test_is_committee_sourced_false_for_a_manually_added_support_item(): void
    {
        [, $record] = $this->setUpAssignmentWithRecord();
        $item = $record->supportItems()->create(['label' => 'Manual item']);

        $this->assertFalse((new CommitteeIpcrRatingService())->isCommitteeSourced($item));
    }

    public function test_rate_writes_quality_efficiency_timeliness_and_computes_row_average(): void
    {
        [$assignment, $record] = $this->setUpAssignmentWithRecord();
        $record->update(['status' => \App\Services\IPCRV2\IpcrV2WorkflowService::STATUS_FOR_RATING]);
        (new CommitteeIpcrSyncService())->syncForUser($assignment->faculty);
        $item = (new CommitteeIpcrRatingService())->resolveSupportItems($assignment)->first();

        $rated = (new CommitteeIpcrRatingService())->rate($item, [
            'quality_rating' => 5, 'efficiency_rating' => 4, 'timeliness_rating' => 3,
            'accomplishment' => 'Organized the annual meet.', 'mov_link' => 'https://example.com/photos',
        ]);

        $this->assertSame(5, $rated->quality_rating);
        $this->assertSame(4, $rated->efficiency_rating);
        $this->assertSame(3, $rated->timeliness_rating);
        $this->assertEqualsWithDelta(4.0, (float) $rated->row_average, 0.01);
        $this->assertSame('Organized the annual meet.', $rated->actual_accomplishment);
        $this->assertSame('https://example.com/photos', $rated->mov_link);
    }

    public function test_rate_rejects_when_targets_not_yet_approved(): void
    {
        [$assignment, $record] = $this->setUpAssignmentWithRecord();
        // status defaults to STATUS_NEW_TARGET
        (new CommitteeIpcrSyncService())->syncForUser($assignment->faculty);
        $item = (new CommitteeIpcrRatingService())->resolveSupportItems($assignment)->first();

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        (new CommitteeIpcrRatingService())->rate($item, [
            'quality_rating' => 5, 'efficiency_rating' => 4, 'timeliness_rating' => 3,
        ]);
    }
}
