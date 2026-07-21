<?php

namespace Tests\Feature;

use App\Models\ComputerLabBooking;
use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\ClassSchedule;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\FacultyLoading\Subject;
use App\Models\Room;
use App\Models\User;
use App\Services\ComputerLabSchedulingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
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
}
