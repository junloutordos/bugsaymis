<?php

namespace Tests\Feature;

use App\Models\ComputerLabBooking;
use App\Models\ComputerLabScheduleApproval;
use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\Role;
use App\Models\Room;
use App\Models\User;
use App\Services\ComputerLabScheduleApprovalService;
use App\Services\DigitalSignatureService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ComputerLabScheduleApprovalTest extends TestCase
{
    use RefreshDatabase;

    private AcademicTerm $term;
    private Room $room;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();

        $schoolYear = SchoolYear::create([
            'name' => '2098-2099',
            'start_date' => '2098-06-01',
            'end_date' => '2099-03-31',
            'is_current' => true,
            'status' => 'active',
        ]);

        $this->term = AcademicTerm::create([
            'school_year_id' => $schoolYear->id,
            'name' => 'Full Term',
            'term_type' => '1st_semester',
            'start_date' => '2098-06-01',
            'end_date' => '2099-03-31',
            'is_current' => true,
        ]);

        $this->room = Room::create([
            'name' => 'Computer Laboratory 1',
            'code' => 'TEST-COMLAB-1',
            'room_type' => 'Computer Laboratory',
            'capacity' => 30,
        ]);

        ComputerLabBooking::create([
            'room_id' => $this->room->id,
            'academic_term_id' => $this->term->id,
            'booking_date' => null,
            'day_of_week' => 'Monday',
            'start_time' => '08:00',
            'end_time' => '09:00',
            'booking_type' => 'priority_class',
            'title' => 'Computer Science 7',
            'status' => 'confirmed',
        ]);
    }

    /** The preparer account — id 5 — pinned to the Science Research Assistant assigned to the Computer Laboratory. */
    private function preparer(): User
    {
        $user = User::factory()->create([
            'id' => ComputerLabScheduleApprovalService::PREPARER_USER_ID,
            'electronic_signature' => 'signatures/sanmiguel.png',
            'signature_pin' => bcrypt('123456'),
        ]);

        return $user->fresh();
    }

    private function cidChief(): User
    {
        $role = Role::firstOrCreate(['name' => 'CID Chief']);
        $user = User::factory()->create([
            'electronic_signature' => 'signatures/cidchief.png',
            'signature_pin' => bcrypt('654321'),
        ]);
        $user->roles()->attach($role->id);

        return $user;
    }

    public function test_only_the_pinned_preparer_may_submit(): void
    {
        $someoneElse = User::factory()->create([
            'electronic_signature' => 'signatures/x.png',
            'signature_pin' => bcrypt('111111'),
        ]);

        $this->expectException(ValidationException::class);
        app(ComputerLabScheduleApprovalService::class)->submit($this->term, $someoneElse, '111111');
    }

    public function test_preparer_can_submit_and_cid_chief_can_approve(): void
    {
        $preparer = $this->preparer();
        $cidChief = $this->cidChief();

        $service = app(ComputerLabScheduleApprovalService::class);
        $approval = $service->submit($this->term, $preparer, '123456');

        $this->assertSame('pending_approval', $approval->status);
        $this->assertDatabaseHas('digital_signatures', [
            'signable_type' => ComputerLabScheduleApproval::class,
            'signable_id' => $approval->id,
            'signer_id' => $preparer->id,
        ]);

        $service->approve($approval, $cidChief, '654321');

        $this->assertSame('approved', $approval->fresh()->status);
        $this->assertSame($cidChief->id, $approval->fresh()->approved_by);
        $this->assertDatabaseHas('digital_signatures', [
            'signable_type' => ComputerLabScheduleApproval::class,
            'signable_id' => $approval->id,
            'signer_id' => $cidChief->id,
        ]);
    }

    public function test_non_cid_chief_cannot_approve(): void
    {
        $preparer = $this->preparer();
        $notCidChief = User::factory()->create([
            'electronic_signature' => 'signatures/y.png',
            'signature_pin' => bcrypt('222222'),
        ]);

        $approval = app(ComputerLabScheduleApprovalService::class)->submit($this->term, $preparer, '123456');

        $this->expectException(ValidationException::class);
        app(ComputerLabScheduleApprovalService::class)->approve($approval, $notCidChief, '222222');
    }

    public function test_second_submission_is_rejected_while_one_is_pending(): void
    {
        $preparer = $this->preparer();
        $service = app(ComputerLabScheduleApprovalService::class);
        $service->submit($this->term, $preparer, '123456');

        $this->expectException(ValidationException::class);
        $service->submit($this->term, $preparer, '123456');
    }

    public function test_schedule_drift_after_submission_blocks_approval(): void
    {
        $preparer = $this->preparer();
        $cidChief = $this->cidChief();
        $service = app(ComputerLabScheduleApprovalService::class);
        $approval = $service->submit($this->term, $preparer, '123456');

        // A new booking changes the term's snapshot after submission.
        ComputerLabBooking::create([
            'room_id' => $this->room->id,
            'academic_term_id' => $this->term->id,
            'booking_date' => '2098-06-08',
            'day_of_week' => 'Monday',
            'start_time' => '10:00',
            'end_time' => '11:00',
            'booking_type' => 'other',
            'title' => 'Ad-hoc booking',
            'status' => 'approved',
        ]);

        $this->expectException(ValidationException::class);
        $service->approve($approval, $cidChief, '654321');
    }

    public function test_cid_chief_can_return_for_revision(): void
    {
        $preparer = $this->preparer();
        $cidChief = $this->cidChief();
        $service = app(ComputerLabScheduleApprovalService::class);
        $approval = $service->submit($this->term, $preparer, '123456');

        $service->returnForRevision($approval, $cidChief, 'Please fix the Tuesday overlap.');

        $this->assertSame('returned', $approval->fresh()->status);
        $this->assertSame('Please fix the Tuesday overlap.', $approval->fresh()->return_remarks);
    }

    public function test_approval_inbox_shows_pending_submission_only_to_cid_chief(): void
    {
        $preparer = $this->preparer();
        $cidChief = $this->cidChief();
        app(ComputerLabScheduleApprovalService::class)->submit($this->term, $preparer, '123456');

        $inbox = new \App\Services\ApprovalInboxService($cidChief);
        $tabs = $inbox->getPendingItems();
        $labTab = collect($tabs)->firstWhere('type', 'computer_lab_schedules');

        $this->assertNotNull($labTab);
        $this->assertSame(1, $labTab['count']);

        $nonApprover = User::factory()->create();
        $emptyInbox = new \App\Services\ApprovalInboxService($nonApprover);
        $this->assertNull(collect($emptyInbox->getPendingItems())->firstWhere('type', 'computer_lab_schedules'));
    }

    public function test_controller_submit_route_rejects_non_preparer_user(): void
    {
        $someoneElse = User::factory()->create([
            'electronic_signature' => 'signatures/z.png',
            'signature_pin' => bcrypt('333333'),
        ]);

        $response = $this->actingAs($someoneElse)
            ->post(route('computer-labs.schedule-approvals.submit', $this->term->id), ['pin' => '333333']);

        $response->assertForbidden();
    }

    public function test_controller_submit_and_approve_routes_work_end_to_end(): void
    {
        $preparer = $this->preparer();
        $cidChief = $this->cidChief();

        $this->actingAs($preparer)
            ->post(route('computer-labs.schedule-approvals.submit', $this->term->id), ['pin' => '123456'])
            ->assertRedirect();

        $approval = ComputerLabScheduleApproval::where('academic_term_id', $this->term->id)->first();
        $this->assertNotNull($approval);

        $this->actingAs($cidChief)
            ->post(route('computer-labs.schedule-approvals.approve', $approval->id), ['pin' => '654321'])
            ->assertRedirect();

        $this->assertSame('approved', $approval->fresh()->status);
    }
}
