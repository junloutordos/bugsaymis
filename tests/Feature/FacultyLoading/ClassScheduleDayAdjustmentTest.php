<?php

namespace Tests\Feature\FacultyLoading;

use App\Models\ClassRecord\ClassRecord;
use App\Models\ClassRecord\ClassRecordAssessment;
use App\Models\ClassRecord\ClassRecordQuarter;
use App\Models\ClassRecord\GradingCategory;
use App\Models\ClassRecord\GradingOption;
use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\Classroom;
use App\Models\FacultyLoading\ClassSchedule;
use App\Models\FacultyLoading\ClassScheduleDayAdjustment;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\FacultyLoading\Section;
use App\Models\FacultyLoading\Subject;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SchoolCalendarEvent;
use App\Models\User;
use App\Services\SchoolCalendarService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClassScheduleDayAdjustmentTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private AcademicTerm $term;

    private ClassSchedule $tuesdayClass;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::create(['name' => 'AdjustmentManager_'.uniqid()]);
        $permission = Permission::firstOrCreate(
            ['name' => 'faculty_loading.manage'],
            ['module' => 'FacultyLoading', 'description' => 'Manage faculty loading'],
        );
        $role->permissions()->attach($permission->id);
        $this->manager = User::factory()->create(['email_verified_at' => now()]);
        $this->manager->roles()->attach($role->id);

        $schoolYear = SchoolYear::create([
            'name' => '2026-2027',
            'start_date' => '2026-08-01',
            'end_date' => '2027-06-30',
            'is_current' => true,
            'status' => 'active',
        ]);
        $this->term = AcademicTerm::create([
            'school_year_id' => $schoolYear->id,
            'name' => '1st Semester',
            'term_type' => '1st_semester',
            'start_date' => '2026-08-01',
            'end_date' => '2026-12-31',
            'is_current' => true,
        ]);

        $section = Section::create([
            'levelid' => 7,
            'sectionname' => 'Aquamarine',
            'syid' => $schoolYear->id,
            'school_year_id' => $schoolYear->id,
            'is_active' => true,
        ]);
        $faculty = User::factory()->create(['email_verified_at' => now()]);
        $subject = Subject::create([
            'school_year_id' => $schoolYear->id,
            'code' => 'MATH7',
            'name' => 'Mathematics 1',
            'credit_units' => 4,
            'lecture_hours' => 4,
            'load_units' => 4,
            'subject_type' => 'lecture',
            'grade_level' => 7,
            'sessions_per_week' => 4,
            'minutes_per_session' => 50,
            'is_active' => true,
        ]);
        $room = Classroom::create([
            'school_year_id' => $schoolYear->id,
            'name' => 'Room 101',
            'code' => 'R101',
            'classroom_type' => 'lecture',
            'capacity' => 40,
            'is_available' => true,
        ]);
        $this->tuesdayClass = ClassSchedule::create([
            'user_id' => $faculty->id,
            'subject_id' => $subject->id,
            'section_id' => $section->id,
            'classroom_id' => $room->id,
            'school_year_id' => $schoolYear->id,
            'academic_term_id' => $this->term->id,
            'day_of_week' => 'Tuesday',
            'start_time' => '07:30',
            'end_time' => '08:20',
            'status' => 'active',
        ]);

        SchoolCalendarEvent::create([
            'date' => '2026-08-03',
            'event_type' => 'holiday',
            'grade_level' => null,
            'title' => 'Campus Holiday',
            'created_by' => $this->manager->id,
        ]);
    }

    public function test_next_school_day_skips_consecutive_campus_closures(): void
    {
        SchoolCalendarEvent::create([
            'date' => '2026-08-04',
            'event_type' => 'suspension',
            'grade_level' => null,
            'title' => 'Classes Suspended',
            'created_by' => $this->manager->id,
        ]);

        $this->assertSame(
            '2026-08-05',
            app(SchoolCalendarService::class)->nextSchoolDayAfter('2026-08-03'),
        );
    }

    public function test_draft_and_publish_freeze_a_shifted_copy_without_changing_weekly_schedule(): void
    {
        $this->manager->update([
            'name' => 'Patacsil, Melba Cruz',
            'position' => 'Chief, Curriculum and Instruction Division',
            'postnominal_title' => 'Ph.D.',
        ]);
        User::factory()->create([
            'name' => 'Dela Cruz, Juan Santos',
            'position' => 'Campus Director',
            'postnominal_title' => 'CESO III',
            'status' => 'active',
        ]);

        $this->actingAs($this->manager)->post(route('faculty-loading.schedules.day-adjustments.store'), [
            'academic_term_id' => $this->term->id,
            'grade_levels' => [7, 8, 9, 10, 11, 12],
            'postponed_from_date' => '2026-08-03',
            'effective_date' => '2026-08-04',
            'reason' => 'Monday campus holiday',
        ])->assertRedirect();

        $adjustment = ClassScheduleDayAdjustment::firstOrFail();
        $this->assertSame('draft', $adjustment->status);
        $this->assertSame('07:30:00', $this->tuesdayClass->fresh()->start_time);

        $this->actingAs($this->manager)
            ->post(route('faculty-loading.schedules.day-adjustments.publish', $adjustment))
            ->assertRedirect();

        $adjustment->refresh();
        $entry = collect($adjustment->schedule_snapshot['grades'])
            ->firstWhere('grade_level', 7)['sections'][0]['entries'][0];

        $this->assertSame('published', $adjustment->status);
        $this->assertSame('08:00', $entry['start_time']);
        $this->assertSame('08:50', $entry['end_time']);
        $this->assertSame('07:30:00', $this->tuesdayClass->fresh()->start_time);

        $this->tuesdayClass->update(['start_time' => '09:00', 'end_time' => '09:50']);

        $this->actingAs($this->manager)
            ->get(route('faculty-loading.schedules.day-adjustments.print', $adjustment))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('FacultyLoading/Schedules/PrintAdjustedDay')
                ->has('snapshot.grades', 6)
                ->where('snapshot.grades.0.sections.0.entries.0.start_time', '08:00')
                ->where('signatories.prepared.name', 'MELBA C. PATACSIL, Ph.D.')
                ->where('signatories.approved.name', 'JUAN S. DELA CRUZ, CESO III')
            );

        $this->actingAs($this->manager)
            ->post(route('faculty-loading.schedules.day-adjustments.cancel', $adjustment))
            ->assertRedirect();

        $adjustment->refresh();
        $this->assertSame('cancelled', $adjustment->status);
        $this->assertSame($this->manager->id, $adjustment->cancelled_by);
        $this->assertNotNull($adjustment->cancelled_at);
    }

    public function test_date_without_adjustment_leaves_regular_schedule_unchanged(): void
    {
        $this->assertDatabaseCount('class_schedule_day_adjustments', 0);
        $this->assertSame('Tuesday', $this->tuesdayClass->fresh()->day_of_week);
        $this->assertSame('07:30:00', $this->tuesdayClass->fresh()->start_time);
    }

    public function test_official_activity_compresses_classes_and_preserves_break_sequence(): void
    {
        $secondClass = $this->tuesdayClass->replicate();
        $secondClass->start_time = '08:40';
        $secondClass->end_time = '09:30';
        $secondClass->save();

        $this->actingAs($this->manager)->post(route('faculty-loading.schedules.day-adjustments.store'), [
            'academic_term_id' => $this->term->id,
            'grade_levels' => [7, 8, 9, 10, 11, 12],
            'adjustment_type' => 'shortened_classes',
            'effective_date' => '2026-08-04',
            'activity_title' => 'Research Congress',
            'activity_start_time' => '13:00',
            'activity_end_time' => '17:00',
            'reason' => 'Official afternoon activity',
        ])->assertRedirect();

        $adjustment = ClassScheduleDayAdjustment::firstOrFail();
        $this->assertNull($adjustment->postponed_from_date);
        $this->assertSame(30, $adjustment->class_duration_minutes);

        $this->actingAs($this->manager)
            ->post(route('faculty-loading.schedules.day-adjustments.publish', $adjustment))
            ->assertRedirect();

        $adjustment->refresh();
        $section = collect($adjustment->schedule_snapshot['grades'])
            ->firstWhere('grade_level', 7)['sections'][0];

        $this->assertSame('07:30', $section['entries'][0]['start_time']);
        $this->assertSame('08:00', $section['entries'][0]['end_time']);
        $this->assertSame('08:20', $section['entries'][1]['start_time']);
        $this->assertSame('08:50', $section['entries'][1]['end_time']);
        $this->assertContains('Research Congress', array_column($section['bands'], 'label'));
        $this->assertSame('07:30:00', $this->tuesdayClass->fresh()->start_time);
    }

    public function test_shortened_classes_must_finish_before_the_activity(): void
    {
        $this->actingAs($this->manager)->post(route('faculty-loading.schedules.day-adjustments.store'), [
            'academic_term_id' => $this->term->id,
            'grade_levels' => [7, 8, 9, 10, 11, 12],
            'adjustment_type' => 'shortened_classes',
            'effective_date' => '2026-08-04',
            'activity_title' => 'Early Assembly',
            'activity_start_time' => '07:45',
            'activity_end_time' => '17:00',
            'reason' => 'Official activity',
        ])->assertSessionHasErrors('activity_start_time');

        $this->assertDatabaseCount('class_schedule_day_adjustments', 0);
    }

    public function test_transferred_flag_ceremony_can_be_combined_with_shortened_classes(): void
    {
        $this->actingAs($this->manager)->post(route('faculty-loading.schedules.day-adjustments.store'), [
            'academic_term_id' => $this->term->id,
            'grade_levels' => [7, 8, 9, 10, 11, 12],
            'adjustment_type' => 'flag_ceremony_shortened_classes',
            'postponed_from_date' => '2026-08-03',
            'effective_date' => '2026-08-04',
            'activity_title' => 'Foundation Day Program',
            'activity_start_time' => '13:00',
            'activity_end_time' => '17:00',
            'reason' => 'Holiday transfer and official afternoon activity',
        ])->assertRedirect();

        $adjustment = ClassScheduleDayAdjustment::firstOrFail();

        $this->actingAs($this->manager)
            ->post(route('faculty-loading.schedules.day-adjustments.publish', $adjustment))
            ->assertRedirect();

        $snapshot = $adjustment->fresh()->schedule_snapshot;
        $section = collect($snapshot['grades'])->firstWhere('grade_level', 7)['sections'][0];

        $this->assertTrue($snapshot['has_flag_ceremony']);
        $this->assertTrue($snapshot['has_shortened_classes']);
        $this->assertSame('08:00', $section['entries'][0]['start_time']);
        $this->assertSame('08:30', $section['entries'][0]['end_time']);
        $this->assertSame('07:30', $snapshot['ceremony']['start']);
        $this->assertContains('Foundation Day Program', array_column($section['bands'], 'label'));
        $this->assertSame('07:30:00', $this->tuesdayClass->fresh()->start_time);
    }

    public function test_cross_grade_room_overlap_after_compression_is_a_warning_not_a_blocking_error(): void
    {
        $room = Classroom::where('code', 'R101')->firstOrFail();
        $otherRoom = Classroom::create([
            'school_year_id' => $this->term->school_year_id,
            'name' => 'Room 102',
            'code' => 'R102',
            'classroom_type' => 'lecture',
            'capacity' => 40,
            'is_available' => true,
        ]);
        $grade7Section = Section::where('sectionname', 'Aquamarine')->firstOrFail();
        $grade7Subject = Subject::where('code', 'MATH7')->firstOrFail();

        $grade8Section = Section::create([
            'levelid' => 8,
            'sectionname' => 'Beryl',
            'syid' => $this->term->school_year_id,
            'school_year_id' => $this->term->school_year_id,
            'is_active' => true,
        ]);
        $grade8Subject = Subject::create([
            'school_year_id' => $this->term->school_year_id,
            'code' => 'MATH8',
            'name' => 'Mathematics 2',
            'credit_units' => 4,
            'lecture_hours' => 4,
            'load_units' => 4,
            'subject_type' => 'lecture',
            'grade_level' => 8,
            'sessions_per_week' => 4,
            'minutes_per_session' => 50,
            'is_active' => true,
        ]);

        // Grade 7 Period 1 (10:00-10:50) and Grade 8 Period 3 (10:50-11:40)
        // share Room 101 back-to-back on Monday — zero real gap, not a real
        // conflict. Grade 8's section genuinely has two real periods earlier
        // in the day (08:50-09:40, 10:00-10:50) that Grade 7's section
        // doesn't, so it banks more real compression savings by 10:50,
        // inverting their order after 30-minute compression — a real,
        // data-driven difference (not fabricated from unused canonical
        // slots the section never actually has classes in).
        ClassSchedule::create([
            'user_id' => User::factory()->create(['email_verified_at' => now()])->id,
            'subject_id' => $grade7Subject->id,
            'section_id' => $grade7Section->id,
            'classroom_id' => $room->id,
            'school_year_id' => $this->term->school_year_id,
            'academic_term_id' => $this->term->id,
            'day_of_week' => 'Monday',
            'start_time' => '10:00',
            'end_time' => '10:50',
            'status' => 'active',
        ]);
        ClassSchedule::create([
            'user_id' => User::factory()->create(['email_verified_at' => now()])->id,
            'subject_id' => $grade8Subject->id,
            'section_id' => $grade8Section->id,
            'classroom_id' => $otherRoom->id,
            'school_year_id' => $this->term->school_year_id,
            'academic_term_id' => $this->term->id,
            'day_of_week' => 'Monday',
            'start_time' => '08:50',
            'end_time' => '09:40',
            'status' => 'active',
        ]);
        ClassSchedule::create([
            'user_id' => User::factory()->create(['email_verified_at' => now()])->id,
            'subject_id' => $grade8Subject->id,
            'section_id' => $grade8Section->id,
            'classroom_id' => $otherRoom->id,
            'school_year_id' => $this->term->school_year_id,
            'academic_term_id' => $this->term->id,
            'day_of_week' => 'Monday',
            'start_time' => '10:00',
            'end_time' => '10:50',
            'status' => 'active',
        ]);
        ClassSchedule::create([
            'user_id' => User::factory()->create(['email_verified_at' => now()])->id,
            'subject_id' => $grade8Subject->id,
            'section_id' => $grade8Section->id,
            'classroom_id' => $room->id,
            'school_year_id' => $this->term->school_year_id,
            'academic_term_id' => $this->term->id,
            'day_of_week' => 'Monday',
            'start_time' => '10:50',
            'end_time' => '11:40',
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->manager)->post(route('faculty-loading.schedules.day-adjustments.store'), [
            'academic_term_id' => $this->term->id,
            'grade_levels' => [7, 8, 9, 10, 11, 12],
            'adjustment_type' => 'shortened_classes',
            'effective_date' => '2026-08-10',
            'activity_title' => 'Heat Index Early Dismissal',
            'activity_start_time' => '13:00',
            'activity_end_time' => '17:00',
            'reason' => 'Due to high heat index',
        ]);

        $response->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseCount('class_schedule_day_adjustments', 1);
        $response->assertSessionHas('warning');
        $this->assertStringContainsString('Grade 7', session('warning'));
        $this->assertStringContainsString('Grade 8', session('warning'));
    }

    public function test_same_section_period_drift_does_not_false_positive_room_conflict(): void
    {
        // Real production bug: a section's own periods can drift a bit from
        // the canonical bell-schedule grid (e.g. Period 1 actually running
        // 07:20-08:10 instead of 07:30-08:20). Two classes for the SAME
        // section/room, genuinely 10 minutes apart and never double-booked,
        // can land on opposite sides of a canonical period boundary and get
        // compressed by different amounts — appearing to overlap even though
        // the section (and therefore its own fixed room) was never actually
        // double-booked.
        $this->tuesdayClass->update(['start_time' => '07:20', 'end_time' => '08:10']);

        $room = Classroom::where('code', 'R101')->firstOrFail();
        $section = Section::where('sectionname', 'Aquamarine')->firstOrFail();
        $subject = Subject::where('code', 'MATH7')->firstOrFail();

        ClassSchedule::create([
            'user_id' => User::factory()->create(['email_verified_at' => now()])->id,
            'subject_id' => $subject->id,
            'section_id' => $section->id,
            'classroom_id' => $room->id,
            'school_year_id' => $this->term->school_year_id,
            'academic_term_id' => $this->term->id,
            'day_of_week' => 'Tuesday',
            'start_time' => '08:20',
            'end_time' => '09:10',
            'status' => 'active',
        ]);

        $this->actingAs($this->manager)->post(route('faculty-loading.schedules.day-adjustments.store'), [
            'academic_term_id' => $this->term->id,
            'grade_levels' => [7, 8, 9, 10, 11, 12],
            'adjustment_type' => 'shortened_classes',
            'effective_date' => '2026-08-04',
            'activity_title' => 'Heat Index Early Dismissal',
            'activity_start_time' => '13:00',
            'activity_end_time' => '17:00',
            'reason' => 'Due to high heat index',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseCount('class_schedule_day_adjustments', 1);
    }

    public function test_same_grade_room_double_booking_still_blocks_save(): void
    {
        $room = Classroom::where('code', 'R101')->firstOrFail();
        $grade7Section = Section::where('sectionname', 'Aquamarine')->firstOrFail();
        $grade7Subject = Subject::where('code', 'MATH7')->firstOrFail();

        $otherGrade7Section = Section::create([
            'levelid' => 7,
            'sectionname' => 'Citrine',
            'syid' => $this->term->school_year_id,
            'school_year_id' => $this->term->school_year_id,
            'is_active' => true,
        ]);

        // Two different Grade 7 sections genuinely double-booked into Room 101
        // at overlapping original times (10:00-10:50 vs 10:20-11:10) — a real
        // conflict the base scheduler should never have allowed, unrelated to
        // compression. Same grade => identical compression shift for both =>
        // still overlapping after compression => must still block.
        ClassSchedule::create([
            'user_id' => User::factory()->create(['email_verified_at' => now()])->id,
            'subject_id' => $grade7Subject->id,
            'section_id' => $grade7Section->id,
            'classroom_id' => $room->id,
            'school_year_id' => $this->term->school_year_id,
            'academic_term_id' => $this->term->id,
            'day_of_week' => 'Monday',
            'start_time' => '10:00',
            'end_time' => '10:50',
            'status' => 'active',
        ]);
        ClassSchedule::create([
            'user_id' => User::factory()->create(['email_verified_at' => now()])->id,
            'subject_id' => $grade7Subject->id,
            'section_id' => $otherGrade7Section->id,
            'classroom_id' => $room->id,
            'school_year_id' => $this->term->school_year_id,
            'academic_term_id' => $this->term->id,
            'day_of_week' => 'Monday',
            'start_time' => '10:20',
            'end_time' => '11:10',
            'status' => 'active',
        ]);

        $this->actingAs($this->manager)->post(route('faculty-loading.schedules.day-adjustments.store'), [
            'academic_term_id' => $this->term->id,
            'grade_levels' => [7, 8, 9, 10, 11, 12],
            'adjustment_type' => 'shortened_classes',
            'effective_date' => '2026-08-10',
            'activity_title' => 'Heat Index Early Dismissal',
            'activity_start_time' => '13:00',
            'activity_end_time' => '17:00',
            'reason' => 'Due to high heat index',
        ])->assertSessionHasErrors('activity_start_time');

        $this->assertDatabaseCount('class_schedule_day_adjustments', 0);
    }

    public function test_non_teaching_blocks_are_excluded_and_off_grid_classes_compress_correctly(): void
    {
        // Real production bug (adjustments published for 2026-08-25/26/27):
        // a short entry_type=non_teaching block sandwiched between two real
        // classes was rendered as if it were a compressible 30-minute class
        // (collapsing toward zero minutes), and its presence knocked every
        // class after it out of alignment with the idealized canonical grid
        // used for compression — corrupting even genuine, correctly-sized
        // classes down to 10 minutes.
        $period2Subject = Subject::create([
            'school_year_id' => $this->term->school_year_id,
            'code' => 'AT1-G7',
            'name' => 'Araling Panlipunan 1',
            'credit_units' => 4,
            'lecture_hours' => 4,
            'load_units' => 4,
            'subject_type' => 'lecture',
            'grade_level' => 7,
            'sessions_per_week' => 4,
            'minutes_per_session' => 50,
            'is_active' => true,
        ]);
        $period3Subject = Subject::create([
            'school_year_id' => $this->term->school_year_id,
            'code' => 'EN1-G7',
            'name' => 'English 1',
            'credit_units' => 4,
            'lecture_hours' => 4,
            'load_units' => 4,
            'subject_type' => 'lecture',
            'grade_level' => 7,
            'sessions_per_week' => 4,
            'minutes_per_session' => 50,
            'is_active' => true,
        ]);
        $section = Section::where('sectionname', 'Aquamarine')->firstOrFail();
        $room = Classroom::where('code', 'R101')->firstOrFail();

        ClassSchedule::create([
            'user_id' => User::factory()->create(['email_verified_at' => now()])->id,
            'subject_id' => $period2Subject->id,
            'section_id' => $section->id,
            'classroom_id' => $room->id,
            'school_year_id' => $this->term->school_year_id,
            'academic_term_id' => $this->term->id,
            'day_of_week' => 'Tuesday',
            'start_time' => '08:40',
            'end_time' => '09:30',
            'status' => 'active',
        ]);
        ClassSchedule::create([
            'user_id' => $this->manager->id,
            'section_id' => $section->id,
            'school_year_id' => $this->term->school_year_id,
            'academic_term_id' => $this->term->id,
            'day_of_week' => 'Tuesday',
            'start_time' => '09:30',
            'end_time' => '09:40',
            'status' => 'active',
            'entry_type' => 'non_teaching',
            'title' => 'Advisory',
        ]);
        ClassSchedule::create([
            'user_id' => User::factory()->create(['email_verified_at' => now()])->id,
            'subject_id' => $period3Subject->id,
            'section_id' => $section->id,
            'classroom_id' => $room->id,
            'school_year_id' => $this->term->school_year_id,
            'academic_term_id' => $this->term->id,
            'day_of_week' => 'Tuesday',
            'start_time' => '09:40',
            'end_time' => '10:30',
            'status' => 'active',
        ]);

        $this->actingAs($this->manager)->post(route('faculty-loading.schedules.day-adjustments.store'), [
            'academic_term_id' => $this->term->id,
            'grade_levels' => [7, 8, 9, 10, 11, 12],
            'adjustment_type' => 'shortened_classes',
            'effective_date' => '2026-08-04',
            'activity_title' => 'Heat Index Early Dismissal',
            'activity_start_time' => '13:00',
            'activity_end_time' => '17:00',
            'reason' => 'Due to high heat index',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $adjustment = ClassScheduleDayAdjustment::firstOrFail();
        $this->actingAs($this->manager)
            ->post(route('faculty-loading.schedules.day-adjustments.publish', $adjustment))
            ->assertRedirect();

        $entries = collect($adjustment->fresh()->schedule_snapshot['grades'])
            ->firstWhere('grade_level', 7)['sections'][0]['entries'];

        // The non-teaching block must not appear at all.
        $this->assertCount(3, $entries);

        $this->assertSame('07:30', $entries[0]['start_time']);
        $this->assertSame('08:00', $entries[0]['end_time']);
        $this->assertSame('08:20', $entries[1]['start_time']);
        $this->assertSame('08:50', $entries[1]['end_time']);
        // Previously corrupted to 09:30-09:40 (10 minutes) by the
        // non-teaching block throwing off the canonical-grid compression.
        $this->assertSame('09:00', $entries[2]['start_time']);
        $this->assertSame('09:30', $entries[2]['end_time']);
    }

    public function test_protect_assessments_keeps_major_assessment_period_full_length_and_compresses_the_rest(): void
    {
        $section = Section::where('sectionname', 'Aquamarine')->firstOrFail();
        $room = Classroom::where('code', 'R101')->firstOrFail();

        $formativeOnlySubject = Subject::create([
            'school_year_id' => $this->term->school_year_id,
            'code' => 'EN1-G7',
            'name' => 'English 1',
            'credit_units' => 4,
            'lecture_hours' => 4,
            'load_units' => 4,
            'subject_type' => 'lecture',
            'grade_level' => 7,
            'sessions_per_week' => 4,
            'minutes_per_session' => 50,
            'is_active' => true,
        ]);
        $noAssessmentSubject = Subject::create([
            'school_year_id' => $this->term->school_year_id,
            'code' => 'AT1-G7',
            'name' => 'Araling Panlipunan 1',
            'credit_units' => 4,
            'lecture_hours' => 4,
            'load_units' => 4,
            'subject_type' => 'lecture',
            'grade_level' => 7,
            'sessions_per_week' => 4,
            'minutes_per_session' => 50,
            'is_active' => true,
        ]);

        $formativeClass = ClassSchedule::create([
            'user_id' => User::factory()->create(['email_verified_at' => now()])->id,
            'subject_id' => $formativeOnlySubject->id,
            'section_id' => $section->id,
            'classroom_id' => $room->id,
            'school_year_id' => $this->term->school_year_id,
            'academic_term_id' => $this->term->id,
            'day_of_week' => 'Tuesday',
            'start_time' => '08:20',
            'end_time' => '09:10',
            'status' => 'active',
        ]);
        ClassSchedule::create([
            'user_id' => User::factory()->create(['email_verified_at' => now()])->id,
            'subject_id' => $noAssessmentSubject->id,
            'section_id' => $section->id,
            'classroom_id' => $room->id,
            'school_year_id' => $this->term->school_year_id,
            'academic_term_id' => $this->term->id,
            'day_of_week' => 'Tuesday',
            'start_time' => '09:10',
            'end_time' => '10:00',
            'status' => 'active',
        ]);

        // MATH7 (07:30-08:20) has a MAJOR assessment today -> must stay 50 min.
        $this->plotAssessment($this->tuesdayClass, '2026-08-04', isMajor: true);
        // English (08:20-09:10) only has a FORMATIVE assessment today -> must still compress.
        $this->plotAssessment($formativeClass, '2026-08-04', isMajor: false);

        $this->actingAs($this->manager)->post(route('faculty-loading.schedules.day-adjustments.store'), [
            'academic_term_id' => $this->term->id,
            'grade_levels' => [7, 8, 9, 10, 11, 12],
            'adjustment_type' => 'shortened_classes_protect_assessments',
            'effective_date' => '2026-08-04',
            'activity_title' => 'Heat Index Early Dismissal',
            'activity_start_time' => '13:00',
            'activity_end_time' => '17:00',
            'reason' => 'Due to high heat index',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $adjustment = ClassScheduleDayAdjustment::firstOrFail();
        $this->assertSame(30, $adjustment->class_duration_minutes);

        $this->actingAs($this->manager)
            ->post(route('faculty-loading.schedules.day-adjustments.publish', $adjustment))
            ->assertRedirect();

        $entries = collect($adjustment->fresh()->schedule_snapshot['grades'])
            ->firstWhere('grade_level', 7)['sections'][0]['entries'];

        // MATH7: protected by its major assessment, untouched 50-minute period.
        $this->assertSame('07:30', $entries[0]['start_time']);
        $this->assertSame('08:20', $entries[0]['end_time']);
        // English: only a formative assessment, still compresses to 30 minutes.
        $this->assertSame('08:20', $entries[1]['start_time']);
        $this->assertSame('08:50', $entries[1]['end_time']);
        // Araling Panlipunan: no assessment at all, compresses to 30 minutes,
        // shifted left only by savings banked from the (non-protected) English period.
        $this->assertSame('08:50', $entries[2]['start_time']);
        $this->assertSame('09:20', $entries[2]['end_time']);
    }

    public function test_protect_assessments_ignores_assessments_plotted_on_other_dates(): void
    {
        $this->plotAssessment($this->tuesdayClass, '2026-08-11', isMajor: true);

        $this->actingAs($this->manager)->post(route('faculty-loading.schedules.day-adjustments.store'), [
            'academic_term_id' => $this->term->id,
            'grade_levels' => [7, 8, 9, 10, 11, 12],
            'adjustment_type' => 'shortened_classes_protect_assessments',
            'effective_date' => '2026-08-04',
            'activity_title' => 'Heat Index Early Dismissal',
            'activity_start_time' => '13:00',
            'activity_end_time' => '17:00',
            'reason' => 'Due to high heat index',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $adjustment = ClassScheduleDayAdjustment::firstOrFail();
        $this->actingAs($this->manager)
            ->post(route('faculty-loading.schedules.day-adjustments.publish', $adjustment))
            ->assertRedirect();

        $entry = collect($adjustment->fresh()->schedule_snapshot['grades'])
            ->firstWhere('grade_level', 7)['sections'][0]['entries'][0];

        $this->assertSame('07:30', $entry['start_time']);
        $this->assertSame('08:00', $entry['end_time']);
    }

    public function test_early_start_stem_split_anchors_first_period_and_splits_duration_by_subject(): void
    {
        Subject::where('code', 'MATH7')->update(['is_stem' => true]);

        $section = Section::where('sectionname', 'Aquamarine')->firstOrFail();
        $room = Classroom::where('code', 'R101')->firstOrFail();
        $filipino = Subject::create([
            'school_year_id' => $this->term->school_year_id,
            'code' => 'FIL1-G7',
            'name' => 'Filipino 1',
            'credit_units' => 4,
            'lecture_hours' => 4,
            'load_units' => 4,
            'subject_type' => 'lecture',
            'grade_level' => 7,
            'sessions_per_week' => 4,
            'minutes_per_session' => 50,
            'is_active' => true,
        ]);
        ClassSchedule::create([
            'user_id' => User::factory()->create(['email_verified_at' => now()])->id,
            'subject_id' => $filipino->id,
            'section_id' => $section->id,
            'classroom_id' => $room->id,
            'school_year_id' => $this->term->school_year_id,
            'academic_term_id' => $this->term->id,
            'day_of_week' => 'Tuesday',
            'start_time' => '08:20',
            'end_time' => '09:10',
            'status' => 'active',
        ]);

        $this->actingAs($this->manager)->post(route('faculty-loading.schedules.day-adjustments.store'), [
            'academic_term_id' => $this->term->id,
            'grade_levels' => [7, 8, 9, 10, 11, 12],
            'adjustment_type' => 'early_start_stem_split',
            'effective_date' => '2026-08-04',
            'health_break_title' => 'Snack Break',
            'health_break_start_time' => '09:20',
            'health_break_end_time' => '09:30',
            'reason' => 'Heat advisory early start',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $adjustment = ClassScheduleDayAdjustment::firstOrFail();
        $this->assertSame('07:00:00', $adjustment->day_start_time);
        $this->assertSame(50, $adjustment->stem_class_duration_minutes);
        $this->assertSame(30, $adjustment->non_stem_class_duration_minutes);

        $this->actingAs($this->manager)
            ->post(route('faculty-loading.schedules.day-adjustments.publish', $adjustment))
            ->assertRedirect();

        $section = collect($adjustment->fresh()->schedule_snapshot['grades'])
            ->firstWhere('grade_level', 7)['sections'][0];

        // MATH7 is STEM: keeps its full 50-minute length, anchored to 07:00.
        $this->assertSame('07:00', $section['entries'][0]['start_time']);
        $this->assertSame('07:50', $section['entries'][0]['end_time']);
        // Filipino is non-STEM: compresses to 30 minutes, immediately after.
        $this->assertSame('07:50', $section['entries'][1]['start_time']);
        $this->assertSame('08:20', $section['entries'][1]['end_time']);

        // Lunch is dropped for this type; Health Break is added.
        $this->assertNotContains('LUNCH', array_column($section['bands'], 'type'));
        $this->assertContains('Snack Break', array_column($section['bands'], 'label'));

        $this->assertSame('07:00', $adjustment->fresh()->schedule_snapshot['calendar_start']);

        // The underlying weekly schedule is untouched.
        $this->assertSame('07:30:00', $this->tuesdayClass->fresh()->start_time);
    }

    public function test_early_start_stem_split_still_blocks_a_genuine_double_booking_with_no_activity_declared(): void
    {
        $room = Classroom::where('code', 'R101')->firstOrFail();
        $grade7Section = Section::where('sectionname', 'Aquamarine')->firstOrFail();
        $grade7Subject = Subject::where('code', 'MATH7')->firstOrFail();

        $otherGrade7Section = Section::create([
            'levelid' => 7,
            'sectionname' => 'Citrine',
            'syid' => $this->term->school_year_id,
            'school_year_id' => $this->term->school_year_id,
            'is_active' => true,
        ]);

        // Two sections genuinely double-booked into Room 101 at overlapping
        // original times — a real conflict unrelated to compression. This
        // must still block even though early_start_stem_split declares no
        // Official Activity at all (blocking is based on raw-time overlap,
        // which is unaffected by each section's own individual anchor shift).
        ClassSchedule::create([
            'user_id' => User::factory()->create(['email_verified_at' => now()])->id,
            'subject_id' => $grade7Subject->id,
            'section_id' => $grade7Section->id,
            'classroom_id' => $room->id,
            'school_year_id' => $this->term->school_year_id,
            'academic_term_id' => $this->term->id,
            'day_of_week' => 'Monday',
            'start_time' => '10:00',
            'end_time' => '10:50',
            'status' => 'active',
        ]);
        ClassSchedule::create([
            'user_id' => User::factory()->create(['email_verified_at' => now()])->id,
            'subject_id' => $grade7Subject->id,
            'section_id' => $otherGrade7Section->id,
            'classroom_id' => $room->id,
            'school_year_id' => $this->term->school_year_id,
            'academic_term_id' => $this->term->id,
            'day_of_week' => 'Monday',
            'start_time' => '10:20',
            'end_time' => '11:10',
            'status' => 'active',
        ]);

        $this->actingAs($this->manager)->post(route('faculty-loading.schedules.day-adjustments.store'), [
            'academic_term_id' => $this->term->id,
            'grade_levels' => [7, 8, 9, 10, 11, 12],
            'adjustment_type' => 'early_start_stem_split',
            'effective_date' => '2026-08-10',
            'reason' => 'Heat advisory early start',
        ])->assertSessionHasErrors('activity_start_time');

        $this->assertDatabaseCount('class_schedule_day_adjustments', 0);
    }

    public function test_early_start_stem_split_fully_collapses_a_gap_that_exactly_matches_lunch(): void
    {
        $section = Section::where('sectionname', 'Aquamarine')->firstOrFail();
        $room = Classroom::where('code', 'R101')->firstOrFail();

        // Grade 7 Tuesday-Friday lunch is 10:20-11:20 (SchedulingConstants::SECTION_LUNCH['G7_TueFri']).
        $periodA = Subject::create([
            'school_year_id' => $this->term->school_year_id, 'code' => 'AT1-G7', 'name' => 'Araling Panlipunan 1',
            'credit_units' => 4, 'lecture_hours' => 4, 'load_units' => 4, 'subject_type' => 'lecture',
            'grade_level' => 7, 'sessions_per_week' => 4, 'minutes_per_session' => 50, 'is_active' => true,
        ]);
        $periodB = Subject::create([
            'school_year_id' => $this->term->school_year_id, 'code' => 'FIL1-G7', 'name' => 'Filipino 1',
            'credit_units' => 4, 'lecture_hours' => 4, 'load_units' => 4, 'subject_type' => 'lecture',
            'grade_level' => 7, 'sessions_per_week' => 4, 'minutes_per_session' => 50, 'is_active' => true,
        ]);
        ClassSchedule::create([
            'user_id' => User::factory()->create(['email_verified_at' => now()])->id,
            'subject_id' => $periodA->id, 'section_id' => $section->id, 'classroom_id' => $room->id,
            'school_year_id' => $this->term->school_year_id, 'academic_term_id' => $this->term->id,
            'day_of_week' => 'Tuesday', 'start_time' => '09:30', 'end_time' => '10:20', 'status' => 'active',
        ]);
        ClassSchedule::create([
            'user_id' => User::factory()->create(['email_verified_at' => now()])->id,
            'subject_id' => $periodB->id, 'section_id' => $section->id, 'classroom_id' => $room->id,
            'school_year_id' => $this->term->school_year_id, 'academic_term_id' => $this->term->id,
            'day_of_week' => 'Tuesday', 'start_time' => '11:20', 'end_time' => '12:10', 'status' => 'active',
        ]);

        $this->actingAs($this->manager)->post(route('faculty-loading.schedules.day-adjustments.store'), [
            'academic_term_id' => $this->term->id,
            'grade_levels' => [7, 8, 9, 10, 11, 12],
            'adjustment_type' => 'early_start_stem_split',
            'effective_date' => '2026-08-04',
            'reason' => 'Heat advisory early start',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $adjustment = ClassScheduleDayAdjustment::firstOrFail();
        $this->actingAs($this->manager)
            ->post(route('faculty-loading.schedules.day-adjustments.publish', $adjustment))
            ->assertRedirect();

        $entries = collect($adjustment->fresh()->schedule_snapshot['grades'])
            ->firstWhere('grade_level', 7)['sections'][0]['entries'];

        $this->assertSame('07:00', $entries[0]['start_time']);
        $this->assertSame('07:30', $entries[0]['end_time']);
        // The two periods straddling lunch (10:20-11:20) are now back-to-back —
        // the gap that used to be Lunch has fully collapsed, not just hidden.
        $this->assertSame('08:40', $entries[1]['start_time']);
        $this->assertSame('09:10', $entries[1]['end_time']);
        $this->assertSame('09:10', $entries[2]['start_time']);
        $this->assertSame('09:40', $entries[2]['end_time']);
    }

    public function test_early_start_stem_split_does_not_fabricate_a_collapse_when_no_real_gap_overlaps_lunch(): void
    {
        $section = Section::where('sectionname', 'Aquamarine')->firstOrFail();
        $room = Classroom::where('code', 'R101')->firstOrFail();

        $second = Subject::create([
            'school_year_id' => $this->term->school_year_id, 'code' => 'AT1-G7', 'name' => 'Araling Panlipunan 1',
            'credit_units' => 4, 'lecture_hours' => 4, 'load_units' => 4, 'subject_type' => 'lecture',
            'grade_level' => 7, 'sessions_per_week' => 4, 'minutes_per_session' => 50, 'is_active' => true,
        ]);
        // 09:00-09:50 — a normal 40-minute gap after the 07:30-08:20 period,
        // nowhere near the 10:20-11:20 lunch window.
        ClassSchedule::create([
            'user_id' => User::factory()->create(['email_verified_at' => now()])->id,
            'subject_id' => $second->id, 'section_id' => $section->id, 'classroom_id' => $room->id,
            'school_year_id' => $this->term->school_year_id, 'academic_term_id' => $this->term->id,
            'day_of_week' => 'Tuesday', 'start_time' => '09:00', 'end_time' => '09:50', 'status' => 'active',
        ]);

        $this->actingAs($this->manager)->post(route('faculty-loading.schedules.day-adjustments.store'), [
            'academic_term_id' => $this->term->id,
            'grade_levels' => [7, 8, 9, 10, 11, 12],
            'adjustment_type' => 'early_start_stem_split',
            'effective_date' => '2026-08-04',
            'reason' => 'Heat advisory early start',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $adjustment = ClassScheduleDayAdjustment::firstOrFail();
        $this->actingAs($this->manager)
            ->post(route('faculty-loading.schedules.day-adjustments.publish', $adjustment))
            ->assertRedirect();

        $entries = collect($adjustment->fresh()->schedule_snapshot['grades'])
            ->firstWhere('grade_level', 7)['sections'][0]['entries'];

        $this->assertSame('07:00', $entries[0]['start_time']);
        $this->assertSame('07:30', $entries[0]['end_time']);
        // Nothing to collapse here — the original 40-minute gap (08:20-09:00,
        // now 07:30-08:10) survives intact, exactly as it did before the fix.
        $this->assertSame('08:10', $entries[1]['start_time']);
        $this->assertSame('08:40', $entries[1]['end_time']);
    }

    public function test_early_start_stem_split_only_collapses_the_portion_of_a_gap_overlapping_lunch(): void
    {
        $section = Section::where('sectionname', 'Aquamarine')->firstOrFail();
        $room = Classroom::where('code', 'R101')->firstOrFail();

        $periodA = Subject::create([
            'school_year_id' => $this->term->school_year_id, 'code' => 'AT1-G7', 'name' => 'Araling Panlipunan 1',
            'credit_units' => 4, 'lecture_hours' => 4, 'load_units' => 4, 'subject_type' => 'lecture',
            'grade_level' => 7, 'sessions_per_week' => 4, 'minutes_per_session' => 50, 'is_active' => true,
        ]);
        $periodB = Subject::create([
            'school_year_id' => $this->term->school_year_id, 'code' => 'FIL1-G7', 'name' => 'Filipino 1',
            'credit_units' => 4, 'lecture_hours' => 4, 'load_units' => 4, 'subject_type' => 'lecture',
            'grade_level' => 7, 'sessions_per_week' => 4, 'minutes_per_session' => 50, 'is_active' => true,
        ]);
        // Gap is wider (09:10-10:00, then 11:40-12:30) than lunch (10:20-11:20)
        // on both sides — only the lunch-overlapping middle should collapse.
        ClassSchedule::create([
            'user_id' => User::factory()->create(['email_verified_at' => now()])->id,
            'subject_id' => $periodA->id, 'section_id' => $section->id, 'classroom_id' => $room->id,
            'school_year_id' => $this->term->school_year_id, 'academic_term_id' => $this->term->id,
            'day_of_week' => 'Tuesday', 'start_time' => '09:10', 'end_time' => '10:00', 'status' => 'active',
        ]);
        ClassSchedule::create([
            'user_id' => User::factory()->create(['email_verified_at' => now()])->id,
            'subject_id' => $periodB->id, 'section_id' => $section->id, 'classroom_id' => $room->id,
            'school_year_id' => $this->term->school_year_id, 'academic_term_id' => $this->term->id,
            'day_of_week' => 'Tuesday', 'start_time' => '11:40', 'end_time' => '12:30', 'status' => 'active',
        ]);

        $this->actingAs($this->manager)->post(route('faculty-loading.schedules.day-adjustments.store'), [
            'academic_term_id' => $this->term->id,
            'grade_levels' => [7, 8, 9, 10, 11, 12],
            'adjustment_type' => 'early_start_stem_split',
            'effective_date' => '2026-08-04',
            'reason' => 'Heat advisory early start',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $adjustment = ClassScheduleDayAdjustment::firstOrFail();
        $this->actingAs($this->manager)
            ->post(route('faculty-loading.schedules.day-adjustments.publish', $adjustment))
            ->assertRedirect();

        $entries = collect($adjustment->fresh()->schedule_snapshot['grades'])
            ->firstWhere('grade_level', 7)['sections'][0]['entries'];

        // Only the 60-minute lunch overlap collapses out of the 100-minute
        // original gap (10:00-11:40) — 40 minutes of genuine gap remain.
        $this->assertSame('08:50', $entries[1]['end_time']);
        $this->assertSame('09:30', $entries[2]['start_time']);
    }

    public function test_band_override_repositions_a_recess_band_and_can_be_removed(): void
    {
        $this->actingAs($this->manager)->post(route('faculty-loading.schedules.day-adjustments.store'), [
            'academic_term_id' => $this->term->id,
            'grade_levels' => [7, 8, 9, 10, 11, 12],
            'postponed_from_date' => '2026-08-03',
            'effective_date' => '2026-08-04',
            'reason' => 'Monday campus holiday',
        ])->assertRedirect();

        $adjustment = ClassScheduleDayAdjustment::firstOrFail();
        $section = Section::where('sectionname', 'Aquamarine')->firstOrFail();

        $response = $this->actingAs($this->manager)
            ->postJson(route('faculty-loading.schedules.day-adjustments.band-overrides.store', $adjustment), [
                'section_id' => $section->id,
                'band_type' => 'RECESS',
                'override_start_time' => '09:00',
                'override_end_time' => '09:15',
            ])
            ->assertOk();

        $this->assertDatabaseCount('class_schedule_day_adjustment_band_overrides', 1);

        $bands = collect($response->json('grades'))->firstWhere('grade_level', 7)['sections'][0]['bands'];
        $recess = collect($bands)->firstWhere('type', 'RECESS');

        $this->assertSame('09:00', $recess['start']);
        $this->assertSame('09:15', $recess['end']);
        $this->assertTrue($recess['manually_adjusted']);

        $this->actingAs($this->manager)
            ->deleteJson(route('faculty-loading.schedules.day-adjustments.band-overrides.destroy', [$adjustment, $section->id, 'RECESS']))
            ->assertOk();

        $this->assertDatabaseCount('class_schedule_day_adjustment_band_overrides', 0);
    }

    public function test_band_override_rejects_an_invalid_band_type(): void
    {
        $this->actingAs($this->manager)->post(route('faculty-loading.schedules.day-adjustments.store'), [
            'academic_term_id' => $this->term->id,
            'grade_levels' => [7, 8, 9, 10, 11, 12],
            'postponed_from_date' => '2026-08-03',
            'effective_date' => '2026-08-04',
            'reason' => 'Monday campus holiday',
        ])->assertRedirect();

        $adjustment = ClassScheduleDayAdjustment::firstOrFail();
        $section = Section::where('sectionname', 'Aquamarine')->firstOrFail();

        $this->actingAs($this->manager)
            ->postJson(route('faculty-loading.schedules.day-adjustments.band-overrides.store', $adjustment), [
                'section_id' => $section->id,
                'band_type' => 'LUNCH',
                'override_start_time' => '09:00',
                'override_end_time' => '09:15',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('band_type');
    }

    public function test_band_override_endpoint_is_draft_only(): void
    {
        $this->actingAs($this->manager)->post(route('faculty-loading.schedules.day-adjustments.store'), [
            'academic_term_id' => $this->term->id,
            'grade_levels' => [7, 8, 9, 10, 11, 12],
            'postponed_from_date' => '2026-08-03',
            'effective_date' => '2026-08-04',
            'reason' => 'Monday campus holiday',
        ])->assertRedirect();

        $adjustment = ClassScheduleDayAdjustment::firstOrFail();
        $section = Section::where('sectionname', 'Aquamarine')->firstOrFail();

        $this->actingAs($this->manager)
            ->post(route('faculty-loading.schedules.day-adjustments.publish', $adjustment))
            ->assertRedirect();

        $this->actingAs($this->manager)
            ->postJson(route('faculty-loading.schedules.day-adjustments.band-overrides.store', $adjustment), [
                'section_id' => $section->id,
                'band_type' => 'RECESS',
                'override_start_time' => '09:00',
                'override_end_time' => '09:15',
            ])
            ->assertStatus(422);
    }

    public function test_band_override_repositions_a_wellness_band_and_can_be_removed(): void
    {
        $this->actingAs($this->manager)->post(route('faculty-loading.schedules.day-adjustments.store'), [
            'academic_term_id' => $this->term->id,
            'grade_levels' => [7, 8, 9, 10, 11, 12],
            'adjustment_type' => 'shortened_classes',
            'effective_date' => '2026-08-05', // Wednesday — campus Wellness window applies
            'activity_title' => 'Assembly',
            'activity_start_time' => '13:00',
            'activity_end_time' => '17:00',
            'reason' => 'Wednesday assembly',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $adjustment = ClassScheduleDayAdjustment::firstOrFail();
        $section = Section::where('sectionname', 'Aquamarine')->firstOrFail();

        $response = $this->actingAs($this->manager)
            ->postJson(route('faculty-loading.schedules.day-adjustments.band-overrides.store', $adjustment), [
                'section_id' => $section->id,
                'band_type' => 'WELLNESS',
                'override_start_time' => '10:30',
                'override_end_time' => '10:45',
            ])
            ->assertOk();

        $this->assertDatabaseCount('class_schedule_day_adjustment_band_overrides', 1);

        $bands = collect($response->json('grades'))->firstWhere('grade_level', 7)['sections'][0]['bands'];
        $wellness = collect($bands)->firstWhere('type', 'WELLNESS');

        $this->assertSame('10:30', $wellness['start']);
        $this->assertSame('10:45', $wellness['end']);
        $this->assertTrue($wellness['manually_adjusted']);

        $this->actingAs($this->manager)
            ->deleteJson(route('faculty-loading.schedules.day-adjustments.band-overrides.destroy', [$adjustment, $section->id, 'WELLNESS']))
            ->assertOk();

        $this->assertDatabaseCount('class_schedule_day_adjustment_band_overrides', 0);
    }

    public function test_health_break_band_override_updates_the_shared_adjustment_fields_and_can_be_removed(): void
    {
        $this->actingAs($this->manager)->post(route('faculty-loading.schedules.day-adjustments.store'), [
            'academic_term_id' => $this->term->id,
            'grade_levels' => [7, 8, 9, 10, 11, 12],
            'adjustment_type' => 'early_start_stem_split',
            'effective_date' => '2026-08-04',
            'health_break_title' => 'Snack Break',
            'health_break_start_time' => '09:20',
            'health_break_end_time' => '09:30',
            'reason' => 'Heat advisory early start',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $adjustment = ClassScheduleDayAdjustment::firstOrFail();
        $section = Section::where('sectionname', 'Aquamarine')->firstOrFail();

        // Health Break is a campus-wide value on the adjustment itself, not
        // a per-section band — dragging it in one section's column must not
        // create a row in the per-section override table.
        $response = $this->actingAs($this->manager)
            ->postJson(route('faculty-loading.schedules.day-adjustments.band-overrides.store', $adjustment), [
                'section_id' => $section->id,
                'band_type' => 'HEALTH_BREAK',
                'override_start_time' => '10:00',
                'override_end_time' => '10:15',
            ])
            ->assertOk();

        $this->assertDatabaseCount('class_schedule_day_adjustment_band_overrides', 0);
        $this->assertSame('10:00:00', $adjustment->fresh()->health_break_start_time);
        $this->assertSame('10:15:00', $adjustment->fresh()->health_break_end_time);

        $sectionBands = collect($response->json('grades'))->firstWhere('grade_level', 7)['sections'][0]['bands'];
        $healthBreak = collect($sectionBands)->firstWhere('type', 'HEALTH_BREAK');
        $this->assertNotNull($healthBreak);
        $this->assertSame('10:00', $healthBreak['start']);
        $this->assertSame('10:15', $healthBreak['end']);
        $this->assertTrue($healthBreak['manually_adjusted']);

        $this->actingAs($this->manager)
            ->deleteJson(route('faculty-loading.schedules.day-adjustments.band-overrides.destroy', [$adjustment, $section->id, 'HEALTH_BREAK']))
            ->assertOk();

        $adjustment->refresh();
        $this->assertNull($adjustment->health_break_title);
        $this->assertNull($adjustment->health_break_start_time);
        $this->assertNull($adjustment->health_break_end_time);
        $this->assertFalse($adjustment->hasHealthBreak());
    }

    public function test_white_space_band_override_creates_a_new_band_when_no_default_exists(): void
    {
        $this->actingAs($this->manager)->post(route('faculty-loading.schedules.day-adjustments.store'), [
            'academic_term_id' => $this->term->id,
            'grade_levels' => [7, 8, 9, 10, 11, 12],
            'postponed_from_date' => '2026-08-03',
            'effective_date' => '2026-08-04',
            'reason' => 'Monday campus holiday',
        ])->assertRedirect();

        $adjustment = ClassScheduleDayAdjustment::firstOrFail();
        $section = Section::where('sectionname', 'Aquamarine')->firstOrFail();

        // White Space has no campus/grade default at all (SchedulingConstants::
        // WHITE_SPACE_CAMPUS/WHITE_SPACE_BY_GRADE are empty) — confirm this
        // grade/day genuinely shows none before adding one.
        $preview = $this->actingAs($this->manager)
            ->getJson(route('faculty-loading.schedules.day-adjustments.preview', $adjustment))
            ->assertOk();
        $bandsBefore = collect($preview->json('grades'))->firstWhere('grade_level', 7)['sections'][0]['bands'];
        $this->assertNull(collect($bandsBefore)->firstWhere('type', 'WHITE_SPACE'));

        $response = $this->actingAs($this->manager)
            ->postJson(route('faculty-loading.schedules.day-adjustments.band-overrides.store', $adjustment), [
                'section_id' => $section->id,
                'band_type' => 'WHITE_SPACE',
                'override_start_time' => '10:00',
                'override_end_time' => '10:15',
            ])
            ->assertOk();

        $bandsAfter = collect($response->json('grades'))->firstWhere('grade_level', 7)['sections'][0]['bands'];
        $whiteSpace = collect($bandsAfter)->firstWhere('type', 'WHITE_SPACE');

        $this->assertNotNull($whiteSpace);
        $this->assertSame('10:00', $whiteSpace['start']);
        $this->assertSame('10:15', $whiteSpace['end']);
        $this->assertTrue($whiteSpace['manually_adjusted']);
        $this->assertSame('White Space', $whiteSpace['label']);
    }

    public function test_health_break_band_override_defaults_a_title_when_none_declared_yet(): void
    {
        $this->actingAs($this->manager)->post(route('faculty-loading.schedules.day-adjustments.store'), [
            'academic_term_id' => $this->term->id,
            'grade_levels' => [7, 8, 9, 10, 11, 12],
            'adjustment_type' => 'early_start_stem_split',
            'effective_date' => '2026-08-04',
            'reason' => 'Heat advisory early start',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $adjustment = ClassScheduleDayAdjustment::firstOrFail();
        $this->assertNull($adjustment->health_break_title);

        $section = Section::where('sectionname', 'Aquamarine')->firstOrFail();

        $response = $this->actingAs($this->manager)
            ->postJson(route('faculty-loading.schedules.day-adjustments.band-overrides.store', $adjustment), [
                'section_id' => $section->id,
                'band_type' => 'HEALTH_BREAK',
                'override_start_time' => '09:20',
                'override_end_time' => '09:30',
            ])
            ->assertOk();

        $this->assertSame('Health Break', $adjustment->fresh()->health_break_title);

        $bands = collect($response->json('grades'))->firstWhere('grade_level', 7)['sections'][0]['bands'];
        $healthBreak = collect($bands)->firstWhere('type', 'HEALTH_BREAK');
        $this->assertSame('Health Break', $healthBreak['label']);
    }

    public function test_every_homeroom_section_gets_the_same_elective_and_science_core_block_no_separate_column(): void
    {
        // Grade 11's canonical Tuesday timetable has a real "Electives"
        // period (13:50-15:30) — unlike Grade 7, which has none at all.
        $sectionA = Section::create([
            'levelid' => 11,
            'sectionname' => 'Diamond',
            'syid' => $this->term->school_year_id,
            'school_year_id' => $this->term->school_year_id,
            'is_active' => true,
        ]);
        $sectionB = Section::create([
            'levelid' => 11,
            'sectionname' => 'Emerald',
            'syid' => $this->term->school_year_id,
            'school_year_id' => $this->term->school_year_id,
            'is_active' => true,
        ]);

        $room = Classroom::where('code', 'R101')->firstOrFail();

        // Synthetic Science Core section (cross-homeroom group) — its real
        // class time is what ScienceCoreService reads back for the window.
        $sciSection = Section::create([
            'levelid' => 11,
            'sectionname' => 'SCI-G11-BIO',
            'syid' => $this->term->school_year_id,
            'school_year_id' => $this->term->school_year_id,
            'is_active' => true,
        ]);
        $sciSubject = Subject::create([
            'school_year_id' => $this->term->school_year_id,
            'code' => 'BIO3L2-G11',
            'name' => 'Biology 3 Level 2',
            'credit_units' => 4,
            'lecture_hours' => 4,
            'load_units' => 4,
            'subject_type' => 'science_core',
            'grade_level' => 11,
            'sessions_per_week' => 4,
            'minutes_per_session' => 50,
            'is_active' => true,
        ]);
        ClassSchedule::create([
            'user_id' => User::factory()->create(['email_verified_at' => now()])->id,
            'subject_id' => $sciSubject->id,
            'section_id' => $sciSection->id,
            'classroom_id' => $room->id,
            'school_year_id' => $this->term->school_year_id,
            'academic_term_id' => $this->term->id,
            'day_of_week' => 'Tuesday',
            'start_time' => '10:00',
            'end_time' => '10:50',
            'status' => 'active',
        ]);

        // early_start_stem_split with no flag ceremony/shortened-classes
        // component and no periods of their own on these two sections means
        // zero shift and zero compression — bands pass through at their raw
        // canonical/actual times, making the assertions below exact.
        $this->actingAs($this->manager)->post(route('faculty-loading.schedules.day-adjustments.store'), [
            'academic_term_id' => $this->term->id,
            'grade_levels' => [7, 8, 9, 10, 11, 12],
            'adjustment_type' => 'early_start_stem_split',
            'effective_date' => '2026-08-04',
            'reason' => 'Heat advisory early start',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $adjustment = ClassScheduleDayAdjustment::firstOrFail();
        $response = $this->actingAs($this->manager)
            ->getJson(route('faculty-loading.schedules.day-adjustments.preview', $adjustment))
            ->assertOk();

        $sections = collect($response->json('grades'))->firstWhere('grade_level', 11)['sections'];

        // No separate column for the synthetic Science Core section — only
        // the two real homeroom sections appear.
        $sectionNames = collect($sections)->pluck('name');
        $this->assertCount(2, $sections);
        $this->assertContains('Diamond', $sectionNames);
        $this->assertContains('Emerald', $sectionNames);
        $this->assertNull(collect($sections)->firstWhere('name', 'SCI-G11-BIO'));

        // Both homerooms carry the exact same ELECTIVE and SCIENCE_CORE
        // blocks, at the same times — the shared grade-wide windows, not
        // anything specific to either homeroom. Grade 11/12 Tuesday has two
        // separate Elective periods (Period 4, and Period 7+8 merged).
        $diamondBands = collect($sections)->firstWhere('name', 'Diamond')['bands'];
        $emeraldBands = collect($sections)->firstWhere('name', 'Emerald')['bands'];

        $diamondElectives = collect($diamondBands)->where('type', 'ELECTIVE')->values();
        $emeraldElectives = collect($emeraldBands)->where('type', 'ELECTIVE')->values();
        $this->assertCount(2, $diamondElectives);
        $this->assertSame($diamondElectives->all(), $emeraldElectives->all());
        $this->assertSame(['10:20', '13:50'], $diamondElectives->pluck('start')->all());
        $this->assertSame(['11:10', '15:30'], $diamondElectives->pluck('end')->all());

        $diamondScienceCore = collect($diamondBands)->firstWhere('type', 'SCIENCE_CORE');
        $emeraldScienceCore = collect($emeraldBands)->firstWhere('type', 'SCIENCE_CORE');
        $this->assertNotNull($diamondScienceCore);
        $this->assertSame($diamondScienceCore, $emeraldScienceCore);
        $this->assertSame('10:00', $diamondScienceCore['start']);
        $this->assertSame('10:50', $diamondScienceCore['end']);
    }

    public function test_early_start_stem_split_treats_science_core_and_elective_subjects_as_stem_even_without_is_stem_flag(): void
    {
        $sciSubject = Subject::create([
            'school_year_id' => $this->term->school_year_id,
            'code' => 'BIO3L2-G7',
            'name' => 'Biology 3 Level 2',
            'credit_units' => 4,
            'lecture_hours' => 4,
            'load_units' => 4,
            'subject_type' => 'science_core',
            'grade_level' => 7,
            'sessions_per_week' => 4,
            'minutes_per_session' => 50,
            'is_active' => true,
        ]);
        $this->assertFalse((bool) $sciSubject->is_stem);

        // Swap the default MATH7 class for a Science Core one so the
        // compression-target behavior can be isolated.
        $this->tuesdayClass->update(['subject_id' => $sciSubject->id]);

        $this->actingAs($this->manager)->post(route('faculty-loading.schedules.day-adjustments.store'), [
            'academic_term_id' => $this->term->id,
            'grade_levels' => [7, 8, 9, 10, 11, 12],
            'adjustment_type' => 'early_start_stem_split',
            'effective_date' => '2026-08-04',
            'reason' => 'Heat advisory early start',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $adjustment = ClassScheduleDayAdjustment::firstOrFail();
        $this->actingAs($this->manager)
            ->post(route('faculty-loading.schedules.day-adjustments.publish', $adjustment))
            ->assertRedirect();

        $entries = collect($adjustment->fresh()->schedule_snapshot['grades'])
            ->firstWhere('grade_level', 7)['sections'][0]['entries'];

        // Science Core keeps its full 50-minute length despite is_stem=false,
        // anchored to the 07:00 day start — same treatment as a real STEM subject.
        $this->assertSame('07:00', $entries[0]['start_time']);
        $this->assertSame('07:50', $entries[0]['end_time']);
    }

    public function test_unplaced_class_entry_is_excluded_from_entries_and_appears_in_unplaced_entries(): void
    {
        $section = Section::where('sectionname', 'Aquamarine')->firstOrFail();
        $room = Classroom::where('code', 'R101')->firstOrFail();
        $filipino = Subject::create([
            'school_year_id' => $this->term->school_year_id, 'code' => 'FIL1-G7', 'name' => 'Filipino 1',
            'credit_units' => 4, 'lecture_hours' => 4, 'load_units' => 4, 'subject_type' => 'lecture',
            'grade_level' => 7, 'sessions_per_week' => 4, 'minutes_per_session' => 50, 'is_active' => true,
        ]);
        $filipinoClass = ClassSchedule::create([
            'user_id' => User::factory()->create(['email_verified_at' => now()])->id,
            'subject_id' => $filipino->id, 'section_id' => $section->id, 'classroom_id' => $room->id,
            'school_year_id' => $this->term->school_year_id, 'academic_term_id' => $this->term->id,
            'day_of_week' => 'Tuesday', 'start_time' => '08:20', 'end_time' => '08:50', 'status' => 'active',
        ]);
        $advisory = ClassSchedule::create([
            'user_id' => $this->manager->id, 'section_id' => $section->id,
            'school_year_id' => $this->term->school_year_id, 'academic_term_id' => $this->term->id,
            'day_of_week' => 'Tuesday', 'start_time' => '08:50', 'end_time' => '09:00', 'status' => 'active',
            'entry_type' => 'non_teaching', 'title' => 'Advisory',
        ]);

        $this->actingAs($this->manager)->post(route('faculty-loading.schedules.day-adjustments.store'), [
            'academic_term_id' => $this->term->id,
            'grade_levels' => [7, 8, 9, 10, 11, 12],
            'postponed_from_date' => '2026-08-03',
            'effective_date' => '2026-08-04',
            'reason' => 'Monday campus holiday',
        ])->assertRedirect();

        $adjustment = ClassScheduleDayAdjustment::firstOrFail();
        $adjustment->unplacedEntries()->create(['class_schedule_id' => $filipinoClass->id]);
        $adjustment->unplacedEntries()->create(['class_schedule_id' => $advisory->id]);

        $response = $this->actingAs($this->manager)
            ->getJson(route('faculty-loading.schedules.day-adjustments.preview', $adjustment))
            ->assertOk();

        $aquamarine = collect($response->json('grades'))->firstWhere('grade_level', 7)['sections'][0];
        $entryIds = collect($aquamarine['entries'])->pluck('id');
        $unplacedIds = collect($aquamarine['unplaced_entries'])->pluck('id');

        $this->assertNotContains($filipinoClass->id, $entryIds);
        $this->assertNotContains($advisory->id, $entryIds);
        $this->assertContains($filipinoClass->id, $unplacedIds);
        $this->assertNotContains($advisory->id, $unplacedIds);

        $unplacedFilipino = collect($aquamarine['unplaced_entries'])->firstWhere('id', $filipinoClass->id);
        $this->assertSame('Filipino 1', $unplacedFilipino['subject']['name']);
        $this->assertSame(30, $unplacedFilipino['duration_minutes']);
    }

    private function plotAssessment(ClassSchedule $classSchedule, string $activityDate, bool $isMajor): void
    {
        $classSchedule->loadMissing('subject');
        $gradingOption = GradingOption::create(['name' => 'Standard '.uniqid(), 'is_active' => true]);
        $category = GradingCategory::create([
            'grading_option_id' => $gradingOption->id,
            'name' => 'Formative Assessment',
            'code' => 'FA',
            'weight' => 0.3000,
            'max_assessments' => 5,
            'sort_order' => 1,
        ]);
        $classRecord = ClassRecord::create([
            'subject_id' => $classSchedule->subject_id,
            'section_id' => $classSchedule->section_id,
            'teacher_id' => $classSchedule->user_id,
            'grading_option_id' => $gradingOption->id,
            'school_year_id' => $this->term->school_year_id,
            'school_year' => '2026-2027',
            'subject_name' => $classSchedule->subject->name,
            'year_level_section' => 'G-7 Aquamarine',
            'status' => 'draft',
        ]);
        $quarter = ClassRecordQuarter::create([
            'class_record_id' => $classRecord->id,
            'quarter' => 1,
        ]);
        ClassRecordAssessment::create([
            'class_record_quarter_id' => $quarter->id,
            'grading_category_id' => $category->id,
            'assessment_number' => 1,
            'title' => 'Assessment',
            'activity_date' => $activityDate,
            'max_score' => 50,
            'is_major' => $isMajor,
        ]);
    }

    // ── Cancellation frees up the date for a new adjustment ───────────────────

    public function test_a_new_adjustment_can_be_created_for_a_date_whose_previous_adjustment_was_cancelled(): void
    {
        $this->actingAs($this->manager)->post(route('faculty-loading.schedules.day-adjustments.store'), [
            'academic_term_id' => $this->term->id,
            'grade_levels' => [7, 8, 9, 10, 11, 12],
            'postponed_from_date' => '2026-08-03',
            'effective_date' => '2026-08-04',
            'reason' => 'Monday campus holiday',
        ])->assertRedirect();

        $first = ClassScheduleDayAdjustment::firstOrFail();

        $this->actingAs($this->manager)
            ->post(route('faculty-loading.schedules.day-adjustments.cancel', $first))
            ->assertRedirect();

        $this->assertSame('cancelled', $first->fresh()->status);

        // Same term + same date, previously blocked forever by a DB-level
        // unique constraint that didn't exclude cancelled rows.
        $this->actingAs($this->manager)->post(route('faculty-loading.schedules.day-adjustments.store'), [
            'academic_term_id' => $this->term->id,
            'grade_levels' => [7, 8, 9, 10, 11, 12],
            'adjustment_type' => 'shortened_classes',
            'effective_date' => '2026-08-04',
            'activity_title' => 'Rescheduled Assembly',
            'activity_start_time' => '13:00',
            'activity_end_time' => '17:00',
            'reason' => 'New adjustment replacing the cancelled one',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseCount('class_schedule_day_adjustments', 2);
        $second = ClassScheduleDayAdjustment::where('id', '<>', $first->id)->firstOrFail();
        $this->assertSame('draft', $second->status);
        $this->assertSame('2026-08-04', $second->effective_date->toDateString());
    }

    public function test_a_second_active_adjustment_for_the_same_date_is_still_rejected(): void
    {
        $this->actingAs($this->manager)->post(route('faculty-loading.schedules.day-adjustments.store'), [
            'academic_term_id' => $this->term->id,
            'grade_levels' => [7, 8, 9, 10, 11, 12],
            'postponed_from_date' => '2026-08-03',
            'effective_date' => '2026-08-04',
            'reason' => 'Monday campus holiday',
        ])->assertRedirect();

        $this->assertDatabaseCount('class_schedule_day_adjustments', 1);

        // Still draft (not cancelled) — a second adjustment for the exact
        // same term/date must still be rejected.
        $this->actingAs($this->manager)->post(route('faculty-loading.schedules.day-adjustments.store'), [
            'academic_term_id' => $this->term->id,
            'grade_levels' => [7, 8, 9, 10, 11, 12],
            'adjustment_type' => 'shortened_classes',
            'effective_date' => '2026-08-04',
            'activity_title' => 'Conflicting Assembly',
            'activity_start_time' => '13:00',
            'activity_end_time' => '17:00',
            'reason' => 'Should be rejected',
        ])->assertSessionHasErrors('effective_date');

        $this->assertDatabaseCount('class_schedule_day_adjustments', 1);
    }

    // ── Grade-level publish scope ─────────────────────────────────────────────

    public function test_store_requires_at_least_one_grade_level(): void
    {
        $this->actingAs($this->manager)->post(route('faculty-loading.schedules.day-adjustments.store'), [
            'academic_term_id' => $this->term->id,
            'grade_levels' => [],
            'postponed_from_date' => '2026-08-03',
            'effective_date' => '2026-08-04',
            'reason' => 'Monday campus holiday',
        ])->assertSessionHasErrors('grade_levels');

        $this->assertDatabaseCount('class_schedule_day_adjustments', 0);
    }

    public function test_partial_grade_selection_only_generates_selected_grades_others_keep_regular_schedule(): void
    {
        $this->actingAs($this->manager)->post(route('faculty-loading.schedules.day-adjustments.store'), [
            'academic_term_id' => $this->term->id,
            'grade_levels' => [7],
            'adjustment_type' => 'shortened_classes',
            'effective_date' => '2026-08-04',
            'activity_title' => 'Grade 7 Only Assembly',
            'activity_start_time' => '13:00',
            'activity_end_time' => '17:00',
            'reason' => 'Grade 7 exclusive activity',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $adjustment = ClassScheduleDayAdjustment::firstOrFail();
        $this->assertSame([7], $adjustment->gradeLevels());

        $this->actingAs($this->manager)
            ->post(route('faculty-loading.schedules.day-adjustments.publish', $adjustment))
            ->assertRedirect();

        $grades = collect($adjustment->fresh()->schedule_snapshot['grades'])->keyBy('grade_level');

        $grade7 = $grades->get(7);
        $this->assertArrayNotHasKey('regular_schedule_applies', $grade7);
        $this->assertNotEmpty($grade7['sections']);
        $this->assertSame('07:30', $grade7['sections'][0]['entries'][0]['start_time']);
        $this->assertSame('08:00', $grade7['sections'][0]['entries'][0]['end_time']);

        foreach ([8, 9, 10, 11, 12] as $unselectedGrade) {
            $grade = $grades->get($unselectedGrade);
            $this->assertTrue($grade['regular_schedule_applies']);
            $this->assertEmpty($grade['sections']);
        }
    }

    public function test_grade_levels_stores_null_when_every_grade_is_selected(): void
    {
        $this->actingAs($this->manager)->post(route('faculty-loading.schedules.day-adjustments.store'), [
            'academic_term_id' => $this->term->id,
            'grade_levels' => [7, 8, 9, 10, 11, 12],
            'postponed_from_date' => '2026-08-03',
            'effective_date' => '2026-08-04',
            'reason' => 'Monday campus holiday',
        ])->assertRedirect();

        $adjustment = ClassScheduleDayAdjustment::firstOrFail();
        $this->assertNull($adjustment->grade_levels);
        $this->assertSame([7, 8, 9, 10, 11, 12], $adjustment->gradeLevels());
    }

    public function test_grades_can_be_edited_after_publishing_and_snapshot_is_refrozen(): void
    {
        $this->actingAs($this->manager)->post(route('faculty-loading.schedules.day-adjustments.store'), [
            'academic_term_id' => $this->term->id,
            'grade_levels' => [7, 8, 9, 10, 11, 12],
            'postponed_from_date' => '2026-08-03',
            'effective_date' => '2026-08-04',
            'reason' => 'Monday campus holiday',
        ])->assertRedirect();

        $adjustment = ClassScheduleDayAdjustment::firstOrFail();

        $this->actingAs($this->manager)
            ->post(route('faculty-loading.schedules.day-adjustments.publish', $adjustment))
            ->assertRedirect();

        $adjustment->refresh();
        $this->assertSame('published', $adjustment->status);
        $publishedGrades = collect($adjustment->schedule_snapshot['grades'])->keyBy('grade_level');
        $this->assertArrayNotHasKey('regular_schedule_applies', $publishedGrades->get(7));

        // Narrow to Grade 7 only, even though the adjustment is already published.
        $this->actingAs($this->manager)
            ->put(route('faculty-loading.schedules.day-adjustments.update-grades', $adjustment), [
                'grade_levels' => [7],
            ])
            ->assertRedirect();

        $adjustment->refresh();
        $this->assertSame('published', $adjustment->status, 'Status must stay published, only grade scope narrows.');
        $this->assertSame([7], $adjustment->gradeLevels());

        $refrozenGrades = collect($adjustment->schedule_snapshot['grades'])->keyBy('grade_level');
        $this->assertArrayNotHasKey('regular_schedule_applies', $refrozenGrades->get(7));
        $this->assertTrue($refrozenGrades->get(8)['regular_schedule_applies']);
    }

    public function test_update_grades_rejects_empty_selection(): void
    {
        $this->actingAs($this->manager)->post(route('faculty-loading.schedules.day-adjustments.store'), [
            'academic_term_id' => $this->term->id,
            'grade_levels' => [7, 8, 9, 10, 11, 12],
            'postponed_from_date' => '2026-08-03',
            'effective_date' => '2026-08-04',
            'reason' => 'Monday campus holiday',
        ])->assertRedirect();

        $adjustment = ClassScheduleDayAdjustment::firstOrFail();

        $this->actingAs($this->manager)
            ->put(route('faculty-loading.schedules.day-adjustments.update-grades', $adjustment), [
                'grade_levels' => [],
            ])
            ->assertSessionHasErrors('grade_levels');

        $this->assertNull($adjustment->fresh()->grade_levels);
    }

    // ── Manual time-only conflict resolution (overrides) ──────────────────────

    public function test_override_resolves_a_flagged_cross_grade_warning(): void
    {
        $room = Classroom::where('code', 'R101')->firstOrFail();
        $otherRoom = Classroom::create([
            'school_year_id' => $this->term->school_year_id,
            'name' => 'Room 102',
            'code' => 'R102',
            'classroom_type' => 'lecture',
            'capacity' => 40,
            'is_available' => true,
        ]);
        $grade7Section = Section::where('sectionname', 'Aquamarine')->firstOrFail();
        $grade7Subject = Subject::where('code', 'MATH7')->firstOrFail();

        $grade8Section = Section::create([
            'levelid' => 8,
            'sectionname' => 'Beryl',
            'syid' => $this->term->school_year_id,
            'school_year_id' => $this->term->school_year_id,
            'is_active' => true,
        ]);
        $grade8Subject = Subject::create([
            'school_year_id' => $this->term->school_year_id,
            'code' => 'MATH8',
            'name' => 'Mathematics 2',
            'credit_units' => 4,
            'lecture_hours' => 4,
            'load_units' => 4,
            'subject_type' => 'lecture',
            'grade_level' => 8,
            'sessions_per_week' => 4,
            'minutes_per_session' => 50,
            'is_active' => true,
        ]);

        $grade7Class = ClassSchedule::create([
            'user_id' => User::factory()->create(['email_verified_at' => now()])->id,
            'subject_id' => $grade7Subject->id,
            'section_id' => $grade7Section->id,
            'classroom_id' => $room->id,
            'school_year_id' => $this->term->school_year_id,
            'academic_term_id' => $this->term->id,
            'day_of_week' => 'Monday',
            'start_time' => '10:00',
            'end_time' => '10:50',
            'status' => 'active',
        ]);
        ClassSchedule::create([
            'user_id' => User::factory()->create(['email_verified_at' => now()])->id,
            'subject_id' => $grade8Subject->id,
            'section_id' => $grade8Section->id,
            'classroom_id' => $otherRoom->id,
            'school_year_id' => $this->term->school_year_id,
            'academic_term_id' => $this->term->id,
            'day_of_week' => 'Monday',
            'start_time' => '08:50',
            'end_time' => '09:40',
            'status' => 'active',
        ]);
        ClassSchedule::create([
            'user_id' => User::factory()->create(['email_verified_at' => now()])->id,
            'subject_id' => $grade8Subject->id,
            'section_id' => $grade8Section->id,
            'classroom_id' => $otherRoom->id,
            'school_year_id' => $this->term->school_year_id,
            'academic_term_id' => $this->term->id,
            'day_of_week' => 'Monday',
            'start_time' => '10:00',
            'end_time' => '10:50',
            'status' => 'active',
        ]);
        ClassSchedule::create([
            'user_id' => User::factory()->create(['email_verified_at' => now()])->id,
            'subject_id' => $grade8Subject->id,
            'section_id' => $grade8Section->id,
            'classroom_id' => $room->id,
            'school_year_id' => $this->term->school_year_id,
            'academic_term_id' => $this->term->id,
            'day_of_week' => 'Monday',
            'start_time' => '10:50',
            'end_time' => '11:40',
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->manager)->post(route('faculty-loading.schedules.day-adjustments.store'), [
            'academic_term_id' => $this->term->id,
            'grade_levels' => [7, 8, 9, 10, 11, 12],
            'adjustment_type' => 'shortened_classes',
            'effective_date' => '2026-08-10',
            'activity_title' => 'Heat Index Early Dismissal',
            'activity_start_time' => '13:00',
            'activity_end_time' => '17:00',
            'reason' => 'Due to high heat index',
        ]);
        $response->assertSessionHas('warning');

        $adjustment = ClassScheduleDayAdjustment::firstOrFail();

        // Manually push the Grade 7 entry's displayed time later so it no
        // longer overlaps Grade 8's compressed 10:10-10:40 Room 101 booking.
        $preview = $this->actingAs($this->manager)
            ->postJson(route('faculty-loading.schedules.day-adjustments.overrides.store', $adjustment), [
                'class_schedule_id' => $grade7Class->id,
                'override_start_time' => '10:40',
                'override_end_time' => '11:10',
            ])
            ->assertOk()
            ->json();

        $this->assertEmpty($preview['conflict_warnings']);

        $overriddenEntry = collect($preview['grades'])
            ->firstWhere('grade_level', 7)['sections'][0]['entries'][0];
        $this->assertSame('10:40', $overriddenEntry['start_time']);
        $this->assertSame('11:10', $overriddenEntry['end_time']);
        $this->assertTrue($overriddenEntry['manually_adjusted']);

        // Publishing now freezes the override into the snapshot, with the audit flag intact.
        $this->actingAs($this->manager)
            ->post(route('faculty-loading.schedules.day-adjustments.publish', $adjustment))
            ->assertRedirect();

        $frozenEntry = collect($adjustment->fresh()->schedule_snapshot['grades'])
            ->firstWhere('grade_level', 7)['sections'][0]['entries'][0];
        $this->assertSame('10:40', $frozenEntry['start_time']);
        $this->assertTrue($frozenEntry['manually_adjusted']);
    }

    public function test_override_cannot_hide_a_genuine_same_grade_double_booking(): void
    {
        $room = Classroom::where('code', 'R101')->firstOrFail();
        $grade7Section = Section::where('sectionname', 'Aquamarine')->firstOrFail();
        $grade7Subject = Subject::where('code', 'MATH7')->firstOrFail();

        $otherGrade7Section = Section::create([
            'levelid' => 7,
            'sectionname' => 'Citrine',
            'syid' => $this->term->school_year_id,
            'school_year_id' => $this->term->school_year_id,
            'is_active' => true,
        ]);

        $firstClass = ClassSchedule::create([
            'user_id' => User::factory()->create(['email_verified_at' => now()])->id,
            'subject_id' => $grade7Subject->id,
            'section_id' => $grade7Section->id,
            'classroom_id' => $room->id,
            'school_year_id' => $this->term->school_year_id,
            'academic_term_id' => $this->term->id,
            'day_of_week' => 'Monday',
            'start_time' => '10:00',
            'end_time' => '10:50',
            'status' => 'active',
        ]);
        ClassSchedule::create([
            'user_id' => User::factory()->create(['email_verified_at' => now()])->id,
            'subject_id' => $grade7Subject->id,
            'section_id' => $otherGrade7Section->id,
            'classroom_id' => $room->id,
            'school_year_id' => $this->term->school_year_id,
            'academic_term_id' => $this->term->id,
            'day_of_week' => 'Monday',
            'start_time' => '10:20',
            'end_time' => '11:10',
            'status' => 'active',
        ]);

        $this->actingAs($this->manager)->post(route('faculty-loading.schedules.day-adjustments.store'), [
            'academic_term_id' => $this->term->id,
            'grade_levels' => [7, 8, 9, 10, 11, 12],
            'adjustment_type' => 'shortened_classes',
            'effective_date' => '2026-08-10',
            'activity_title' => 'Heat Index Early Dismissal',
            'activity_start_time' => '13:00',
            'activity_end_time' => '17:00',
            'reason' => 'Due to high heat index',
        ])->assertSessionHasErrors('activity_start_time');

        // Nothing was persisted (store() throws before creating the row), so
        // there's no adjustment to attach an override to — the resolution
        // path is only reachable once a draft exists in the first place.
        $this->assertDatabaseCount('class_schedule_day_adjustments', 0);
    }

    public function test_override_requires_end_time_after_start_time(): void
    {
        $this->actingAs($this->manager)->post(route('faculty-loading.schedules.day-adjustments.store'), [
            'academic_term_id' => $this->term->id,
            'grade_levels' => [7, 8, 9, 10, 11, 12],
            'postponed_from_date' => '2026-08-03',
            'effective_date' => '2026-08-04',
            'reason' => 'Monday campus holiday',
        ])->assertRedirect();

        $adjustment = ClassScheduleDayAdjustment::firstOrFail();

        $this->actingAs($this->manager)
            ->postJson(route('faculty-loading.schedules.day-adjustments.overrides.store', $adjustment), [
                'class_schedule_id' => $this->tuesdayClass->id,
                'override_start_time' => '09:00',
                'override_end_time' => '08:00',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('override_end_time');
    }

    public function test_override_can_be_removed(): void
    {
        $this->actingAs($this->manager)->post(route('faculty-loading.schedules.day-adjustments.store'), [
            'academic_term_id' => $this->term->id,
            'grade_levels' => [7, 8, 9, 10, 11, 12],
            'postponed_from_date' => '2026-08-03',
            'effective_date' => '2026-08-04',
            'reason' => 'Monday campus holiday',
        ])->assertRedirect();

        $adjustment = ClassScheduleDayAdjustment::firstOrFail();

        $this->actingAs($this->manager)
            ->postJson(route('faculty-loading.schedules.day-adjustments.overrides.store', $adjustment), [
                'class_schedule_id' => $this->tuesdayClass->id,
                'override_start_time' => '09:00',
                'override_end_time' => '09:50',
            ])
            ->assertOk();

        $this->assertDatabaseCount('class_schedule_day_adjustment_overrides', 1);

        $this->actingAs($this->manager)
            ->deleteJson(route('faculty-loading.schedules.day-adjustments.overrides.destroy', [$adjustment, $this->tuesdayClass->id]))
            ->assertOk();

        $this->assertDatabaseCount('class_schedule_day_adjustment_overrides', 0);
    }

    public function test_override_endpoint_is_draft_only(): void
    {
        $this->actingAs($this->manager)->post(route('faculty-loading.schedules.day-adjustments.store'), [
            'academic_term_id' => $this->term->id,
            'grade_levels' => [7, 8, 9, 10, 11, 12],
            'postponed_from_date' => '2026-08-03',
            'effective_date' => '2026-08-04',
            'reason' => 'Monday campus holiday',
        ])->assertRedirect();

        $adjustment = ClassScheduleDayAdjustment::firstOrFail();
        $this->actingAs($this->manager)
            ->post(route('faculty-loading.schedules.day-adjustments.publish', $adjustment))
            ->assertRedirect();

        $this->actingAs($this->manager)
            ->postJson(route('faculty-loading.schedules.day-adjustments.overrides.store', $adjustment), [
                'class_schedule_id' => $this->tuesdayClass->id,
                'override_start_time' => '09:00',
                'override_end_time' => '09:50',
            ])
            ->assertStatus(422);
    }
}
