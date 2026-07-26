<?php

namespace Tests\Feature;

use App\Models\ComputerLabBooking;
use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\ClassSchedule;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\FacultyLoading\Subject;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Room;
use App\Models\User;
use App\Services\ComputerLabSchedulingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ComputerLabSchedulingTest extends TestCase
{
    use RefreshDatabase;

    private AcademicTerm $term;

    private Subject $subject;

    private User $faculty;

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

        $this->faculty = User::factory()->create();
        $this->subject = Subject::create([
            'school_year_id' => $schoolYear->id,
            'code' => 'TEST-CS',
            'name' => 'Computer Science Test',
            'credit_units' => 3,
            'lecture_hours' => 3,
            'lab_hours' => 0,
            'load_units' => 3,
            'subject_type' => 'lecture',
            'grade_level' => 7,
            'semester' => 'both',
            'sessions_per_week' => 1,
            'minutes_per_session' => 60,
            'is_active' => true,
            'requires_computer_lab' => true,
        ]);

        foreach (range(1, 4) as $number) {
            Room::create([
                'name' => "Computer Laboratory {$number}",
                'code' => "TEST-COMLAB-{$number}",
                'room_type' => 'Computer Laboratory',
                'capacity' => 30,
            ]);
        }
    }

    private function userWithPermission(string $permissionName): User
    {
        $permission = Permission::firstOrCreate(
            ['name' => $permissionName],
            ['module' => 'Computer Laboratories', 'description' => $permissionName],
        );
        $role = Role::create(['name' => 'ComputerLabManager_'.uniqid()]);
        $role->permissions()->attach($permission->id);
        $user = User::factory()->create();
        $user->roles()->attach($role->id);

        return $user;
    }

    public function test_fifth_simultaneous_priority_class_is_unassigned_without_changing_class_schedule(): void
    {
        foreach (range(1, 5) as $number) {
            ClassSchedule::create([
                'user_id' => $this->faculty->id,
                'subject_id' => $this->subject->id,
                'section_id' => null,
                'classroom_id' => null,
                'school_year_id' => $this->term->school_year_id,
                'academic_term_id' => $this->term->id,
                'entry_type' => 'class',
                'day_of_week' => 'Monday',
                'start_time' => '08:00',
                'end_time' => '09:00',
                'status' => 'active',
            ]);
        }

        $result = app(ComputerLabSchedulingService::class)->synchronizeTerm($this->term->id);

        $this->assertSame(4, $result['confirmed']);
        $this->assertSame(1, $result['unassigned']);
        $this->assertSame(5, ClassSchedule::where('academic_term_id', $this->term->id)->count());
        $this->assertSame(1, ComputerLabBooking::where('status', 'unassigned')->count());
    }

    public function test_priority_assignment_is_stable_across_repeated_synchronization(): void
    {
        $schedule = ClassSchedule::create([
            'user_id' => $this->faculty->id,
            'subject_id' => $this->subject->id,
            'section_id' => null,
            'classroom_id' => null,
            'school_year_id' => $this->term->school_year_id,
            'academic_term_id' => $this->term->id,
            'entry_type' => 'class',
            'day_of_week' => 'Tuesday',
            'start_time' => '09:00',
            'end_time' => '10:00',
            'status' => 'active',
        ]);

        $service = app(ComputerLabSchedulingService::class);
        $service->synchronizeTerm($this->term->id);
        $firstRoom = ComputerLabBooking::where('class_schedule_id', $schedule->id)->value('room_id');
        $service->synchronizeTerm($this->term->id);

        $this->assertSame($firstRoom, ComputerLabBooking::where('class_schedule_id', $schedule->id)->value('room_id'));
    }

    public function test_priority_class_can_move_to_another_lab_and_remains_there_after_sync(): void
    {
        $schedule = ClassSchedule::create([
            'user_id' => $this->faculty->id,
            'subject_id' => $this->subject->id,
            'section_id' => null,
            'classroom_id' => null,
            'school_year_id' => $this->term->school_year_id,
            'academic_term_id' => $this->term->id,
            'entry_type' => 'class',
            'day_of_week' => 'Thursday',
            'start_time' => '08:00',
            'end_time' => '09:00',
            'status' => 'active',
        ]);

        $service = app(ComputerLabSchedulingService::class);
        $service->synchronizeTerm($this->term->id);
        $booking = ComputerLabBooking::where('class_schedule_id', $schedule->id)->firstOrFail();
        $targetRoom = Room::where('room_type', 'Computer Laboratory')
            ->whereKeyNot($booking->room_id)
            ->orderByDesc('name')
            ->firstOrFail();

        $service->moveToRoom($booking, $targetRoom->id);
        $this->assertSame($targetRoom->id, $booking->refresh()->room_id);

        $service->synchronizeTerm($this->term->id);
        $this->assertSame($targetRoom->id, $booking->refresh()->room_id);
    }

    public function test_move_rejects_an_occupied_destination_lab(): void
    {
        foreach (range(1, 2) as $number) {
            ClassSchedule::create([
                'user_id' => $this->faculty->id,
                'subject_id' => $this->subject->id,
                'section_id' => null,
                'classroom_id' => null,
                'school_year_id' => $this->term->school_year_id,
                'academic_term_id' => $this->term->id,
                'entry_type' => 'class',
                'day_of_week' => 'Friday',
                'start_time' => '09:00',
                'end_time' => '10:00',
                'status' => 'active',
            ]);
        }

        $service = app(ComputerLabSchedulingService::class);
        $service->synchronizeTerm($this->term->id);
        [$first, $second] = ComputerLabBooking::where('booking_type', 'priority_class')->orderBy('id')->get();

        $this->expectException(ValidationException::class);
        $service->moveToRoom($first, $second->room_id);
    }

    public function test_approved_booking_can_move_to_an_available_lab(): void
    {
        $rooms = Room::where('room_type', 'Computer Laboratory')->orderBy('id')->get();
        $booking = ComputerLabBooking::create([
            'room_id' => $rooms[0]->id,
            'academic_term_id' => $this->term->id,
            'booking_date' => '2098-06-03',
            'day_of_week' => 'Tuesday',
            'start_time' => '13:00',
            'end_time' => '14:00',
            'booking_type' => 'other',
            'title' => 'Approved training',
            'purpose' => 'Training',
            'requested_by' => $this->faculty->id,
            'status' => 'approved',
        ]);

        app(ComputerLabSchedulingService::class)->moveToRoom($booking, $rooms[3]->id);

        $this->assertSame($rooms[3]->id, $booking->refresh()->room_id);
        $this->assertSame('approved', $booking->status);
    }

    public function test_recurring_priority_move_checks_approved_bookings_across_the_term(): void
    {
        $schedule = ClassSchedule::create([
            'user_id' => $this->faculty->id,
            'subject_id' => $this->subject->id,
            'section_id' => null,
            'classroom_id' => null,
            'school_year_id' => $this->term->school_year_id,
            'academic_term_id' => $this->term->id,
            'entry_type' => 'class',
            'day_of_week' => 'Monday',
            'start_time' => '10:00',
            'end_time' => '11:00',
            'status' => 'active',
        ]);

        $service = app(ComputerLabSchedulingService::class);
        $service->synchronizeTerm($this->term->id);
        $priority = ComputerLabBooking::where('class_schedule_id', $schedule->id)->firstOrFail();
        $targetRoom = Room::where('room_type', 'Computer Laboratory')->whereKeyNot($priority->room_id)->firstOrFail();

        ComputerLabBooking::create([
            'room_id' => $targetRoom->id,
            'academic_term_id' => $this->term->id,
            'booking_date' => '2098-07-07',
            'day_of_week' => 'Monday',
            'start_time' => '10:30',
            'end_time' => '11:30',
            'booking_type' => 'other',
            'title' => 'Future approved booking',
            'purpose' => 'Training',
            'requested_by' => $this->faculty->id,
            'status' => 'approved',
        ]);

        $this->expectException(ValidationException::class);
        $service->moveToRoom($priority, $targetRoom->id);
    }

    public function test_swap_exchanges_rooms_between_two_approved_ad_hoc_bookings(): void
    {
        $rooms = Room::where('room_type', 'Computer Laboratory')->orderBy('id')->get();

        $bookingA = ComputerLabBooking::create([
            'room_id' => $rooms[0]->id,
            'academic_term_id' => $this->term->id,
            'booking_date' => '2098-06-03',
            'day_of_week' => 'Tuesday',
            'start_time' => '13:00',
            'end_time' => '14:00',
            'booking_type' => 'other',
            'title' => 'Robotics Club',
            'purpose' => 'Training',
            'requested_by' => $this->faculty->id,
            'status' => 'approved',
        ]);

        $bookingB = ComputerLabBooking::create([
            'room_id' => $rooms[1]->id,
            'academic_term_id' => $this->term->id,
            'booking_date' => '2098-06-03',
            'day_of_week' => 'Tuesday',
            'start_time' => '13:00',
            'end_time' => '14:00',
            'booking_type' => 'other',
            'title' => 'Chess Club',
            'purpose' => 'Practice',
            'requested_by' => $this->faculty->id,
            'status' => 'approved',
        ]);

        $roomA = $bookingA->room_id;
        $roomB = $bookingB->room_id;

        $swapped = app(ComputerLabSchedulingService::class)->moveToRoom($bookingA, $roomB, true);

        $this->assertTrue($swapped);
        $this->assertSame($roomB, $bookingA->refresh()->room_id);
        $this->assertSame($roomA, $bookingB->refresh()->room_id);
        $this->assertSame('approved', $bookingA->status);
        $this->assertSame('approved', $bookingB->status);
    }

    public function test_swap_exchanges_rooms_between_two_recurring_priority_classes(): void
    {
        foreach (range(1, 2) as $number) {
            ClassSchedule::create([
                'user_id' => $this->faculty->id,
                'subject_id' => $this->subject->id,
                'section_id' => null,
                'classroom_id' => null,
                'school_year_id' => $this->term->school_year_id,
                'academic_term_id' => $this->term->id,
                'entry_type' => 'class',
                'day_of_week' => 'Friday',
                'start_time' => '09:00',
                'end_time' => '10:00',
                'status' => 'active',
            ]);
        }

        $service = app(ComputerLabSchedulingService::class);
        $service->synchronizeTerm($this->term->id);
        [$first, $second] = ComputerLabBooking::where('booking_type', 'priority_class')->orderBy('id')->get();
        $firstOriginalRoom = $first->room_id;
        $secondOriginalRoom = $second->room_id;

        $swapped = $service->moveToRoom($first, $secondOriginalRoom, true);

        $this->assertTrue($swapped);
        $this->assertSame($secondOriginalRoom, $first->refresh()->room_id);
        $this->assertSame($firstOriginalRoom, $second->refresh()->room_id);
    }

    public function test_swap_is_not_offered_when_multiple_bookings_conflict_in_target_room(): void
    {
        $rooms = Room::where('room_type', 'Computer Laboratory')->orderBy('id')->get();

        $moving = ComputerLabBooking::create([
            'room_id' => $rooms[0]->id,
            'academic_term_id' => $this->term->id,
            'booking_date' => '2098-06-03',
            'day_of_week' => 'Tuesday',
            'start_time' => '13:00',
            'end_time' => '14:00',
            'booking_type' => 'other',
            'title' => 'Robotics Club',
            'purpose' => 'Training',
            'requested_by' => $this->faculty->id,
            'status' => 'approved',
        ]);

        // Two overlapping bookings placed directly in the target room to force
        // an ambiguous conflict. This state can't arise through the service
        // itself (it always checks before writing), only through direct
        // seeding — but the "exactly one conflict" guard must still hold.
        foreach ([['13:00', '14:00', 'Chess Club'], ['13:30', '14:30', 'Debate Society']] as [$start, $end, $title]) {
            ComputerLabBooking::create([
                'room_id' => $rooms[1]->id,
                'academic_term_id' => $this->term->id,
                'booking_date' => '2098-06-03',
                'day_of_week' => 'Tuesday',
                'start_time' => $start,
                'end_time' => $end,
                'booking_type' => 'other',
                'title' => $title,
                'purpose' => 'Practice',
                'requested_by' => $this->faculty->id,
                'status' => 'approved',
            ]);
        }

        try {
            app(ComputerLabSchedulingService::class)->moveToRoom($moving, $rooms[1]->id, true);
            $this->fail('Expected a ValidationException.');
        } catch (ValidationException $e) {
            $this->assertArrayNotHasKey('can_swap', $e->errors());
        }

        $this->assertSame($rooms[0]->id, $moving->refresh()->room_id);
    }

    public function test_swap_fails_when_reverse_room_is_not_actually_free(): void
    {
        $schedule = ClassSchedule::create([
            'user_id' => $this->faculty->id,
            'subject_id' => $this->subject->id,
            'section_id' => null,
            'classroom_id' => null,
            'school_year_id' => $this->term->school_year_id,
            'academic_term_id' => $this->term->id,
            'entry_type' => 'class',
            'day_of_week' => 'Monday',
            'start_time' => '08:00',
            'end_time' => '09:00',
            'status' => 'active',
        ]);

        $service = app(ComputerLabSchedulingService::class);
        $service->synchronizeTerm($this->term->id);
        $priority = ComputerLabBooking::where('class_schedule_id', $schedule->id)->firstOrFail();
        $priorityOriginalRoom = $priority->room_id;

        $targetRoom = Room::where('room_type', 'Computer Laboratory')->whereKeyNot($priorityOriginalRoom)->firstOrFail();
        $conflict = ComputerLabBooking::create([
            'room_id' => $targetRoom->id,
            'academic_term_id' => $this->term->id,
            'booking_date' => '2098-07-07', // a Monday within the term
            'day_of_week' => 'Monday',
            'start_time' => '08:00',
            'end_time' => '09:00',
            'booking_type' => 'other',
            'title' => 'One-off robotics session',
            'purpose' => 'Training',
            'requested_by' => $this->faculty->id,
            'status' => 'approved',
        ]);

        // Force an inconsistent seed: another booking already sits in the
        // priority class's own room on the exact date the conflicting
        // booking would need to move to, so the reverse fit must fail even
        // though the forward conflict looks like a clean 1-for-1 swap.
        ComputerLabBooking::create([
            'room_id' => $priorityOriginalRoom,
            'academic_term_id' => $this->term->id,
            'booking_date' => '2098-07-07',
            'day_of_week' => 'Monday',
            'start_time' => '08:00',
            'end_time' => '09:00',
            'booking_type' => 'other',
            'title' => 'Pre-existing session',
            'purpose' => 'Blocks the reverse swap',
            'requested_by' => $this->faculty->id,
            'status' => 'approved',
        ]);

        try {
            $service->moveToRoom($priority, $targetRoom->id, true);
            $this->fail('Expected a ValidationException.');
        } catch (ValidationException $e) {
            $this->assertArrayNotHasKey('can_swap', $e->errors());
        }

        $this->assertSame($priorityOriginalRoom, $priority->refresh()->room_id);
        $this->assertSame($targetRoom->id, $conflict->refresh()->room_id);
    }

    public function test_conflict_error_exposes_swap_metadata_when_swap_is_offered(): void
    {
        foreach (range(1, 2) as $number) {
            ClassSchedule::create([
                'user_id' => $this->faculty->id,
                'subject_id' => $this->subject->id,
                'section_id' => null,
                'classroom_id' => null,
                'school_year_id' => $this->term->school_year_id,
                'academic_term_id' => $this->term->id,
                'entry_type' => 'class',
                'day_of_week' => 'Friday',
                'start_time' => '09:00',
                'end_time' => '10:00',
                'status' => 'active',
            ]);
        }

        $service = app(ComputerLabSchedulingService::class);
        $service->synchronizeTerm($this->term->id);
        [$first, $second] = ComputerLabBooking::where('booking_type', 'priority_class')->orderBy('id')->get();

        try {
            $service->moveToRoom($first, $second->room_id);
            $this->fail('Expected a ValidationException.');
        } catch (ValidationException $e) {
            $errors = $e->errors();
            $this->assertSame((string) $second->id, $errors['conflict_booking_id'][0]);
            $this->assertSame($second->title, $errors['conflict_title'][0]);
            $this->assertSame('1', $errors['can_swap'][0]);
        }
    }

    public function test_move_returns_false_and_swap_flag_is_a_no_op_when_destination_is_free(): void
    {
        $rooms = Room::where('room_type', 'Computer Laboratory')->orderBy('id')->get();
        $booking = ComputerLabBooking::create([
            'room_id' => $rooms[0]->id,
            'academic_term_id' => $this->term->id,
            'booking_date' => '2098-06-03',
            'day_of_week' => 'Tuesday',
            'start_time' => '13:00',
            'end_time' => '14:00',
            'booking_type' => 'other',
            'title' => 'Approved training',
            'purpose' => 'Training',
            'requested_by' => $this->faculty->id,
            'status' => 'approved',
        ]);

        $swapped = app(ComputerLabSchedulingService::class)->moveToRoom($booking, $rooms[3]->id, true);

        $this->assertFalse($swapped);
        $this->assertSame($rooms[3]->id, $booking->refresh()->room_id);
    }

    public function test_manager_can_move_a_booking_through_the_room_endpoint(): void
    {
        $schedule = ClassSchedule::create([
            'user_id' => $this->faculty->id,
            'subject_id' => $this->subject->id,
            'section_id' => null,
            'classroom_id' => null,
            'school_year_id' => $this->term->school_year_id,
            'academic_term_id' => $this->term->id,
            'entry_type' => 'class',
            'day_of_week' => 'Wednesday',
            'start_time' => '13:00',
            'end_time' => '14:00',
            'status' => 'active',
        ]);

        app(ComputerLabSchedulingService::class)->synchronizeTerm($this->term->id);
        $booking = ComputerLabBooking::where('class_schedule_id', $schedule->id)->firstOrFail();
        $targetRoom = Room::where('room_type', 'Computer Laboratory')->whereKeyNot($booking->room_id)->firstOrFail();

        $this->actingAs($this->userWithPermission('computer_labs.manage'))
            ->patch(route('computer-labs.bookings.move', $booking), ['room_id' => $targetRoom->id])
            ->assertSessionHasNoErrors();

        $this->assertSame($targetRoom->id, $booking->refresh()->room_id);
    }

    public function test_user_without_management_permission_cannot_move_a_booking(): void
    {
        $booking = ComputerLabBooking::create([
            'room_id' => Room::where('room_type', 'Computer Laboratory')->value('id'),
            'academic_term_id' => $this->term->id,
            'booking_date' => '2098-06-03',
            'day_of_week' => 'Tuesday',
            'start_time' => '13:00',
            'end_time' => '14:00',
            'booking_type' => 'other',
            'title' => 'Approved training',
            'purpose' => 'Training',
            'requested_by' => $this->faculty->id,
            'status' => 'approved',
        ]);
        $targetRoom = Room::where('room_type', 'Computer Laboratory')->whereKeyNot($booking->room_id)->firstOrFail();

        $this->actingAs(User::factory()->create())
            ->patch(route('computer-labs.bookings.move', $booking), ['room_id' => $targetRoom->id])
            ->assertForbidden();

        $this->assertNotSame($targetRoom->id, $booking->refresh()->room_id);
    }

    public function test_calendar_payload_contains_monday_through_friday_only(): void
    {
        $this->actingAs($this->userWithPermission('computer_labs.book'))
            ->get(route('computer-labs.index', [
                'term_id' => $this->term->id,
                'week_start' => '2098-06-02',
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('ITJobRequests/ComputerLabs/Index')
                ->has('days', 5)
                ->where('days.0.name', 'Monday')
                ->where('days.4.name', 'Friday'));
    }

    public function test_weekend_computer_lab_booking_is_rejected(): void
    {
        $roomId = Room::where('room_type', 'Computer Laboratory')->value('id');
        $saturday = $this->term->start_date->copy()->next(Carbon::SATURDAY);

        $this->actingAs($this->userWithPermission('computer_labs.book'))
            ->post(route('computer-labs.bookings.store'), [
                'room_id' => $roomId,
                'academic_term_id' => $this->term->id,
                'booking_date' => $saturday->toDateString(),
                'start_time' => '08:00',
                'end_time' => '09:00',
                'title' => 'Weekend activity',
                'purpose' => 'This must not be hidden outside the weekday calendar.',
            ])
            ->assertSessionHasErrors('booking_date');

        $this->assertDatabaseMissing('computer_lab_bookings', [
            'booking_type' => 'other',
            'booking_date' => $saturday->toDateString(),
        ]);
    }

    public function test_other_booking_cannot_be_approved_over_a_priority_class(): void
    {
        ClassSchedule::create([
            'user_id' => $this->faculty->id,
            'subject_id' => $this->subject->id,
            'section_id' => null,
            'classroom_id' => null,
            'school_year_id' => $this->term->school_year_id,
            'academic_term_id' => $this->term->id,
            'entry_type' => 'class',
            'day_of_week' => 'Wednesday',
            'start_time' => '10:00',
            'end_time' => '11:00',
            'status' => 'active',
        ]);

        $service = app(ComputerLabSchedulingService::class);
        $service->synchronizeTerm($this->term->id);
        $roomId = ComputerLabBooking::where('booking_type', 'priority_class')->value('room_id');

        $conflicts = $service->conflictsFor(
            $roomId,
            Carbon::parse('2098-06-04'), // Wednesday
            '10:15',
            '10:45',
        );

        $this->assertCount(1, $conflicts);
        $this->assertSame('priority_class', $conflicts->first()->booking_type);
    }

    // ── Print schedule ───────────────────────────────────────────────────────

    public function test_print_schedule_renders_successfully_for_all_labs(): void
    {
        $user = $this->userWithPermission('computer_labs.manage');

        $this->actingAs($user)
            ->get(route('computer-labs.print', ['term_id' => $this->term->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('ITJobRequests/ComputerLabs/Print'));
    }

    public function test_print_schedule_renders_successfully_for_a_single_lab(): void
    {
        $user = $this->userWithPermission('computer_labs.manage');
        $room = Room::where('room_type', 'Computer Laboratory')->first();

        $this->actingAs($user)
            ->get(route('computer-labs.print', ['term_id' => $this->term->id, 'lab_id' => $room->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('ITJobRequests/ComputerLabs/Print')
                ->has('labs', 1));
    }

    public function test_print_schedule_falls_back_to_role_based_signatories_before_any_approval_submission(): void
    {
        $user = $this->userWithPermission('computer_labs.manage');
        $cidChief = User::factory()->create(['name' => 'Chief Person']);
        $role = Role::create(['name' => 'CID Chief']);
        $cidChief->roles()->attach($role->id);

        $this->actingAs($user)
            ->get(route('computer-labs.print', ['term_id' => $this->term->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('signatories.approved.name', 'Chief Person'));
    }
}
