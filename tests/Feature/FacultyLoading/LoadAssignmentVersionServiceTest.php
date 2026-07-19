<?php

namespace Tests\Feature\FacultyLoading;

use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\ClassSchedule;
use App\Models\FacultyLoading\Classroom;
use App\Models\FacultyLoading\FacultyLoad;
use App\Models\FacultyLoading\LoadAssignment;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\FacultyLoading\Section;
use App\Models\FacultyLoading\Subject;
use App\Models\User;
use App\Services\FacultyLoading\LoadAssignmentVersionService;
use App\Services\FacultyLoading\LoadBalancingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers LoadAssignmentVersionService's smart, ID-preserving restore: in-place
 * updates, teaching-slot transfers-back (mirrored onto class_schedules),
 * create/delete reconciliation, locked-faculty skipping, and FacultyLoad
 * resync — the behaviors that distinguish it from a naive delete+recreate.
 */
class LoadAssignmentVersionServiceTest extends TestCase
{
    use RefreshDatabase;

    private LoadAssignmentVersionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(LoadAssignmentVersionService::class);
    }

    private function makeTerm(): array
    {
        $sy = SchoolYear::create([
            'name' => '2025-2026', 'start_date' => '2025-08-01', 'end_date' => '2026-06-30',
            'is_current' => true, 'status' => 'active',
        ]);
        $term = AcademicTerm::create([
            'school_year_id' => $sy->id, 'name' => '1st Semester', 'term_type' => '1st_semester',
            'start_date' => '2025-08-01', 'end_date' => '2025-12-31', 'is_current' => true,
        ]);

        return compact('sy', 'term');
    }

    private function makeFacultyWithLoad(SchoolYear $sy, AcademicTerm $term): array
    {
        $faculty = User::factory()->create(['email_verified_at' => now()]);
        $load = FacultyLoad::create([
            'user_id' => $faculty->id, 'school_year_id' => $sy->id, 'academic_term_id' => $term->id,
            'teaching_units' => 0, 'total_units' => 0, 'full_load_threshold' => 18, 'load_status' => 'underload',
        ]);

        return compact('faculty', 'load');
    }

    private function makeTeachingAssignment(SchoolYear $sy, AcademicTerm $term, array $facultyLoad, Subject $subject, Section $section, float $units = 3): LoadAssignment
    {
        return LoadAssignment::create([
            'faculty_load_id' => $facultyLoad['load']->id, 'user_id' => $facultyLoad['faculty']->id,
            'school_year_id' => $sy->id, 'academic_term_id' => $term->id,
            'assignment_type' => 'teaching', 'subject_id' => $subject->id, 'section_id' => $section->id,
            'load_units' => $units,
        ]);
    }

    public function test_restore_reverts_a_load_units_edit_in_place(): void
    {
        ['sy' => $sy, 'term' => $term] = $this->makeTerm();
        $fx = $this->makeFacultyWithLoad($sy, $term);
        $subject = Subject::create([
            'school_year_id' => $sy->id, 'code' => 'MATH1', 'name' => 'Math 1', 'credit_units' => 3,
            'lecture_hours' => 3, 'load_units' => 3, 'subject_type' => 'lecture', 'grade_level' => 9,
            'sessions_per_week' => 5, 'minutes_per_session' => 60, 'is_active' => true,
        ]);
        $section = Section::create(['levelid' => 9, 'sectionname' => 'Diamond', 'syid' => $sy->id, 'school_year_id' => $sy->id, 'is_active' => true]);
        $assignment = $this->makeTeachingAssignment($sy, $term, $fx, $subject, $section, 3);
        app(\App\Services\FacultyLoading\LoadComputationService::class)->syncLoad($fx['load']);

        $version = $this->service->save($term->id, 'Draft A', null, null);

        $assignment->update(['load_units' => 6]);

        $result = $this->service->restore($version);

        $this->assertSame(1, $result['restored']);
        $this->assertDatabaseHas('load_assignments', ['id' => $assignment->id, 'load_units' => 3.00]);
        $this->assertSame(3.0, (float) $fx['load']->fresh()->total_units);
    }

    public function test_restore_moves_a_transferred_teaching_slot_back_and_mirrors_schedules(): void
    {
        ['sy' => $sy, 'term' => $term] = $this->makeTerm();
        $fxA = $this->makeFacultyWithLoad($sy, $term);
        $fxB = $this->makeFacultyWithLoad($sy, $term);
        $subject = Subject::create([
            'school_year_id' => $sy->id, 'code' => 'MATH1', 'name' => 'Math 1', 'credit_units' => 3,
            'lecture_hours' => 3, 'load_units' => 3, 'subject_type' => 'lecture', 'grade_level' => 9,
            'sessions_per_week' => 5, 'minutes_per_session' => 60, 'is_active' => true,
        ]);
        $section = Section::create(['levelid' => 9, 'sectionname' => 'Diamond', 'syid' => $sy->id, 'school_year_id' => $sy->id, 'is_active' => true]);
        $room = Classroom::create(['school_year_id' => $sy->id, 'name' => 'Room 1', 'code' => 'R1', 'classroom_type' => 'lecture', 'capacity' => 40, 'is_available' => true]);
        $assignment = $this->makeTeachingAssignment($sy, $term, $fxA, $subject, $section, 3);
        $schedule = ClassSchedule::create([
            'load_assignment_id' => $assignment->id, 'user_id' => $fxA['faculty']->id, 'subject_id' => $subject->id,
            'section_id' => $section->id, 'classroom_id' => $room->id, 'school_year_id' => $sy->id, 'academic_term_id' => $term->id,
            'day_of_week' => 'Monday', 'start_time' => '08:00:00', 'end_time' => '09:00:00', 'status' => 'tentative',
        ]);

        $version = $this->service->save($term->id, 'Draft A', null, null);

        app(LoadBalancingService::class)->applyTransfer($assignment->id, $fxB['faculty']->id);

        $result = $this->service->restore($version);

        $this->assertSame(1, $result['restored']);
        $assignment->refresh();
        $this->assertSame($fxA['faculty']->id, $assignment->user_id);
        $this->assertSame($fxA['load']->id, $assignment->faculty_load_id);
        $this->assertSame($fxA['faculty']->id, $schedule->fresh()->user_id);
    }

    public function test_restore_recreates_a_deleted_assignment_and_deletes_a_new_one(): void
    {
        ['sy' => $sy, 'term' => $term] = $this->makeTerm();
        $fx = $this->makeFacultyWithLoad($sy, $term);
        $subjectA = Subject::create([
            'school_year_id' => $sy->id, 'code' => 'MATH1', 'name' => 'Math 1', 'credit_units' => 3,
            'lecture_hours' => 3, 'load_units' => 3, 'subject_type' => 'lecture', 'grade_level' => 9,
            'sessions_per_week' => 5, 'minutes_per_session' => 60, 'is_active' => true,
        ]);
        $subjectB = Subject::create([
            'school_year_id' => $sy->id, 'code' => 'SCI1', 'name' => 'Science 1', 'credit_units' => 3,
            'lecture_hours' => 3, 'load_units' => 3, 'subject_type' => 'lecture', 'grade_level' => 9,
            'sessions_per_week' => 5, 'minutes_per_session' => 60, 'is_active' => true,
        ]);
        $section = Section::create(['levelid' => 9, 'sectionname' => 'Diamond', 'syid' => $sy->id, 'school_year_id' => $sy->id, 'is_active' => true]);
        $section2 = Section::create(['levelid' => 9, 'sectionname' => 'Opal', 'syid' => $sy->id, 'school_year_id' => $sy->id, 'is_active' => true]);
        $keep = $this->makeTeachingAssignment($sy, $term, $fx, $subjectA, $section, 3);

        $version = $this->service->save($term->id, 'Draft A', null, null);

        $keep->delete();
        $newOne = $this->makeTeachingAssignment($sy, $term, $fx, $subjectB, $section2, 4);

        $result = $this->service->restore($version);

        $this->assertSame(2, $result['restored']);
        $this->assertDatabaseHas('load_assignments', ['subject_id' => $subjectA->id, 'section_id' => $section->id, 'user_id' => $fx['faculty']->id]);
        $this->assertDatabaseMissing('load_assignments', ['id' => $newOne->id]);
    }

    public function test_restore_skips_a_locked_faculty_load_and_reports_it(): void
    {
        ['sy' => $sy, 'term' => $term] = $this->makeTerm();
        $fx = $this->makeFacultyWithLoad($sy, $term);
        $subject = Subject::create([
            'school_year_id' => $sy->id, 'code' => 'MATH1', 'name' => 'Math 1', 'credit_units' => 3,
            'lecture_hours' => 3, 'load_units' => 3, 'subject_type' => 'lecture', 'grade_level' => 9,
            'sessions_per_week' => 5, 'minutes_per_session' => 60, 'is_active' => true,
        ]);
        $section = Section::create(['levelid' => 9, 'sectionname' => 'Diamond', 'syid' => $sy->id, 'school_year_id' => $sy->id, 'is_active' => true]);
        $assignment = $this->makeTeachingAssignment($sy, $term, $fx, $subject, $section, 3);

        $version = $this->service->save($term->id, 'Draft A', null, null);

        $assignment->update(['load_units' => 6]);
        $fx['load']->update(['is_locked' => true]);

        $result = $this->service->restore($version);

        $this->assertSame(0, $result['restored']);
        $this->assertSame([$fx['faculty']->name], $result['skipped_locked']);
        $this->assertDatabaseHas('load_assignments', ['id' => $assignment->id, 'load_units' => 6.00]);
    }

    public function test_compare_classifies_changed_added_and_removed_assignments(): void
    {
        ['sy' => $sy, 'term' => $term] = $this->makeTerm();
        $fxA = $this->makeFacultyWithLoad($sy, $term);
        $fxB = $this->makeFacultyWithLoad($sy, $term);
        $subjectA = Subject::create([
            'school_year_id' => $sy->id, 'code' => 'MATH1', 'name' => 'Math 1', 'credit_units' => 3,
            'lecture_hours' => 3, 'load_units' => 3, 'subject_type' => 'lecture', 'grade_level' => 9,
            'sessions_per_week' => 5, 'minutes_per_session' => 60, 'is_active' => true,
        ]);
        $subjectB = Subject::create([
            'school_year_id' => $sy->id, 'code' => 'SCI1', 'name' => 'Science 1', 'credit_units' => 3,
            'lecture_hours' => 3, 'load_units' => 3, 'subject_type' => 'lecture', 'grade_level' => 9,
            'sessions_per_week' => 5, 'minutes_per_session' => 60, 'is_active' => true,
        ]);
        $subjectC = Subject::create([
            'school_year_id' => $sy->id, 'code' => 'ENG1', 'name' => 'English 1', 'credit_units' => 3,
            'lecture_hours' => 3, 'load_units' => 3, 'subject_type' => 'lecture', 'grade_level' => 9,
            'sessions_per_week' => 5, 'minutes_per_session' => 60, 'is_active' => true,
        ]);
        $section  = Section::create(['levelid' => 9, 'sectionname' => 'Diamond', 'syid' => $sy->id, 'school_year_id' => $sy->id, 'is_active' => true]);
        $section2 = Section::create(['levelid' => 9, 'sectionname' => 'Opal', 'syid' => $sy->id, 'school_year_id' => $sy->id, 'is_active' => true]);
        $section3 = Section::create(['levelid' => 9, 'sectionname' => 'Turquoise', 'syid' => $sy->id, 'school_year_id' => $sy->id, 'is_active' => true]);
        $changed = $this->makeTeachingAssignment($sy, $term, $fxA, $subjectA, $section, 3);
        $removed = $this->makeTeachingAssignment($sy, $term, $fxA, $subjectB, $section2, 3);

        $version = $this->service->save($term->id, 'Draft A', null, null);

        $changed->update(['user_id' => $fxB['faculty']->id, 'faculty_load_id' => $fxB['load']->id]);
        $removed->delete();
        $this->makeTeachingAssignment($sy, $term, $fxA, $subjectC, $section3, 4);

        $result = $this->service->compare($version, null, $term->id);

        $statuses = collect($result['diff'])->keyBy(fn ($e) => $e['key']);
        $this->assertSame('changed', $statuses["teaching|{$subjectA->id}|{$section->id}"]['status']);
        $this->assertSame('removed', $statuses["teaching|{$subjectB->id}|{$section2->id}"]['status']);
        $this->assertSame('added', $statuses["teaching|{$subjectC->id}|{$section3->id}"]['status']);
    }
}
