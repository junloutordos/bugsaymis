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
}
